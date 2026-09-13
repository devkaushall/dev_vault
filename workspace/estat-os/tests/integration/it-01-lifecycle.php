<?php
/**
 * Activation, schema, migration and listing CRUD against real WordPress.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Data\Listings;
use EstatOS\Data\PostTypes;
use EstatOS\Install\Schema;
use EstatOS\Search\SearchIndex;

estat_reset_world();
estat_login_owner();

/* ------------------------------------------------------------ Activation */

EstatTests::group( 'Integration: activation and schema' );

EstatTests::ok( Schema::healthy(), 'every custom table exists after activation' );

foreach ( array( 'estat_leads', 'estat_visits', 'estat_forms', 'estat_submissions', 'estat_index', 'estat_audit', 'estat_webhook_log', 'estat_import_jobs' ) as $table ) {
	EstatTests::ok(
		$GLOBALS['wpdb']->has_table( $GLOBALS['wpdb']->prefix . $table ),
		'table ' . $table . ' was created by real CREATE TABLE SQL'
	);
}

EstatTests::ok( post_type_exists( PostTypes::LISTING ), 'the listing post type is registered' );
EstatTests::ok( post_type_exists( PostTypes::PROJECT ), 'the project post type is registered' );
EstatTests::ok( post_type_exists( PostTypes::AGENT ), 'the agent post type is registered' );
EstatTests::ok( post_type_exists( PostTypes::AGENCY ), 'the agency post type is registered' );

/* -------------------------------------------------------- Listing create */

EstatTests::group( 'Integration: listing create, read, update' );

$listing_id = Listings::save(
	array(
		'title'         => '3 BHK apartment near the metro',
		'description'   => 'A bright corner flat with a park view and covered parking.',
		'offer'         => 'sale',
		'property_type' => 'apartment',
		'price'         => 8500000,
		'area'          => 1450,
		'area_unit'     => 'sqft',
		'bedrooms'      => 3,
		'bathrooms'     => 2,
		'locality'      => 'Green Park',
		'status'        => 'publish',
	)
);

EstatTests::ok( ! is_wp_error( $listing_id ), 'a well-formed listing saves without error' );
$listing_id = (int) $listing_id;
EstatTests::ok( $listing_id > 0, 'saving returns a real post ID' );
EstatTests::is( 'publish', get_post_status( $listing_id ), 'the listing is published' );
EstatTests::is( PostTypes::LISTING, get_post_type( $listing_id ), 'it has the listing post type' );

EstatTests::is( '8500000', (string) get_post_meta( $listing_id, '_estat_price', true ), 'the price is stored as a plain number' );
EstatTests::is( 'sale', (string) get_post_meta( $listing_id, '_estat_offer', true ), 'the offer is stored' );
EstatTests::is( '3', (string) get_post_meta( $listing_id, '_estat_bedrooms', true ), 'bedrooms are stored' );

/* ------------------------------------------------- Derived: area in sqft */

EstatTests::group( 'Integration: derived values' );

EstatTests::is(
	'1450',
	(string) (float) get_post_meta( $listing_id, '_estat_area_sqft', true ),
	'square feet input is normalised to 1450 sqft'
);

$yard_listing = (int) Listings::save(
	array(
		'title'     => 'Plot on the ring road with clear title',
		'offer'     => 'sale',
		'property_type' => 'plot',
		'area'      => 100,
		'area_unit' => 'sqyd',
		'price'     => 5000000,
		'locality'  => 'Ring Road',
		'status'    => 'publish',
	)
);
EstatTests::is(
	'900',
	(string) (float) get_post_meta( $yard_listing, '_estat_area_sqft', true ),
	'100 square yards is normalised to 900 sqft on save'
);

/* --------------------------------------------------------- Completeness */

EstatTests::group( 'Integration: completeness scoring' );

$score = Listings::completeness( $listing_id );
EstatTests::ok( is_array( $score ) && isset( $score['score'], $score['missing'] ), 'completeness returns a score and a missing list' );
EstatTests::ok( $score['score'] > 0 && $score['score'] <= 100, 'the score is within 0 to 100' );
EstatTests::ok( is_array( $score['missing'] ), 'missing items are reported as an array' );

$labels = array_column( $score['missing'], 'field' );
EstatTests::ok( in_array( 'cover', $labels, true ) || in_array( 'cover_id', $labels, true ), 'a listing with no photo is told the cover photo is missing' );

/* -------------------------------------------------------- Search index */

EstatTests::group( 'Integration: the search index stays in sync' );

$status = SearchIndex::status();
EstatTests::ok( (int) $status['indexed'] >= 2, 'saved listings were written to the index automatically' );

global $wpdb;
$row = $wpdb->get_row(
	$wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'estat_index' ) . ' WHERE listing_id = %d', $listing_id ),
	ARRAY_A
);
EstatTests::ok( is_array( $row ), 'the listing has an index row' );
EstatTests::is( '8500000', (string) (float) ( $row['price'] ?? 0 ), 'the index carries the numeric price' );
EstatTests::is( 'publish', (string) ( $row['post_status'] ?? '' ), 'the index carries the publication status' );

/* ------------------------------------------------------------ Validation */

EstatTests::group( 'Integration: validation refuses bad data' );

$bad = Listings::save( array( 'title' => '', 'offer' => 'sale' ) );
EstatTests::ok( is_wp_error( $bad ), 'a listing with no headline is refused' );

$bad_offer = Listings::save( array( 'title' => 'A perfectly fine headline here', 'offer' => 'burglary' ) );
EstatTests::ok( is_wp_error( $bad_offer ), 'an invalid offer type is refused' );

/* ------------------------------------------------- Publication gating */

EstatTests::group( 'Integration: publishing respects capability' );

estat_login_with( 2, array( 'estat_manage_listings' ) ); // no estat_publish_listings.
$gated = Listings::save(
	array(
		'title'    => 'Should not go live without permission',
		'offer'    => 'rent',
		'property_type' => 'apartment',
		'locality' => 'Somewhere',
		'rent'     => 25000,
		'status'   => 'publish',
	)
);
EstatTests::ok( ! is_wp_error( $gated ), 'the save itself succeeds' );
EstatTests::is(
	'draft',
	(string) get_post_status( (int) $gated ),
	'a user without publish permission silently gets a draft instead of a live listing'
);

estat_login_owner();

/* --------------------------------------------------------------- Delete */

EstatTests::group( 'Integration: deleting a listing cleans up' );

$doomed = (int) Listings::save(
	array(
		'title'    => 'This listing will be removed',
		'offer'    => 'sale',
		'property_type' => 'villa',
		'locality' => 'Elsewhere',
		'price'    => 1200000,
		'status'   => 'publish',
	)
);
$before = (int) SearchIndex::status()['indexed'];
wp_delete_post( $doomed, true );
$after = (int) SearchIndex::status()['indexed'];
EstatTests::ok( $after < $before, 'deleting a listing removes its search index row' );
