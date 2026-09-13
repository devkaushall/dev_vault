<?php
/**
 * Query counts, upgrade safety and the uninstall contract.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Data\Listings;
use EstatOS\Frontend\Components;
use EstatOS\Install\Migrator;
use EstatOS\Install\Schema;
use EstatOS\Search\Query;
use EstatOS\Settings\Settings;

estat_reset_world();
estat_login_owner();

/* ------------------------------------------------------- Setup a catalogue */

for ( $i = 1; $i <= 30; $i++ ) {
	Listings::save(
		array(
			'title'         => 'Sample listing number ' . $i . ' in the catalogue',
			'offer'         => 0 === $i % 2 ? 'sale' : 'rent',
			'property_type' => 'apartment',
			'locality'      => 'Sector ' . ( $i % 5 ),
			'price'         => 1000000 * $i,
			'rent'          => 10000 * $i,
			'area'          => 500 + ( $i * 10 ),
			'area_unit'     => 'sqft',
			'bedrooms'      => 1 + ( $i % 4 ),
			'status'        => 'publish',
		)
	);
}

global $wpdb;

/* ------------------------------------------------------------ Search cost */

EstatTests::group( 'Integration: a search does not melt the database' );

$wpdb->queries = array();
$page          = Query::search( array( 'per_page' => 12 ) );
$search_cost   = count( $wpdb->queries );

EstatTests::ok( ! empty( $page['ids'] ), 'the search returns a page of listings' );
EstatTests::ok( count( $page['ids'] ) <= 12, 'it respects the page size' );
EstatTests::ok(
	$search_cost <= 3,
	'a paged search costs a count plus a page fetch, not one query per listing and not a table-health probe per call (took ' . $search_cost . ')'
);

/* ------------------------------------------------------- Listing page cost */

EstatTests::group( 'Integration: rendering a page of cards' );

$wpdb->queries = array();
$html          = '';
foreach ( array_slice( $page['ids'], 0, 12 ) as $listing_id ) {
	$html .= Components::card( (int) $listing_id );
}
$card_cost = count( $wpdb->queries );

EstatTests::ok( '' !== $html, 'twelve cards render' );
EstatTests::ok(
	$card_cost < 12 * 12,
	'rendering twelve cards stays well under a dozen queries each (took ' . $card_cost . ')'
);

/* ------------------------------------------------------------- Pagination */

EstatTests::group( 'Integration: paging works' );

$p1 = Query::search( array( 'per_page' => 10, 'page' => 1 ) );
$p2 = Query::search( array( 'per_page' => 10, 'page' => 2 ) );

EstatTests::ok( ! empty( $p1['ids'] ) && ! empty( $p2['ids'] ), 'both pages return results' );
EstatTests::ok( array() === array_intersect( $p1['ids'], $p2['ids'] ), 'page two does not repeat page one' );
EstatTests::ok( (int) $p1['pages'] >= 3, 'the page count reflects thirty listings at ten per page' );

/* ------------------------------------------------------------- Settings */

EstatTests::group( 'Integration: settings are cached, not re-read constantly' );

Settings::flush();
Settings::all();
$wpdb->queries = array();
for ( $i = 0; $i < 20; $i++ ) {
	Settings::get( 'office_name' );
}
EstatTests::ok(
	count( $wpdb->queries ) <= 1,
	'reading a setting twenty times does not hit the database twenty times'
);

/* ------------------------------------------------------- Upgrade safety */

EstatTests::group( 'Integration: upgrading is safe and repeatable' );

$listing_before = Listings::find_by_external_id( '' );
$count_before   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'estat_index' ) );

// Pretend an older version is installed and let the migrator run again.
update_option( 'estat_db_version', '0.0.1' );
Migrator::maybe_upgrade();

EstatTests::ok( Schema::healthy(), 'all tables still exist after an upgrade' );
EstatTests::is(
	ESTAT_DB_VERSION,
	(string) get_option( 'estat_db_version' ),
	'the stored database version is brought up to date'
);
EstatTests::is(
	$count_before,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'estat_index' ) ),
	'an upgrade does not lose indexed listings'
);

// Running it a second time must be a no-op rather than an error.
$ok = true;
try {
	Migrator::maybe_upgrade();
	Migrator::maybe_upgrade();
} catch ( Throwable $e ) {
	$ok = false;
	EstatTests::ok( false, 'a repeated upgrade threw: ' . $e->getMessage() );
}
if ( $ok ) {
	EstatTests::ok( true, 'running the upgrade repeatedly is harmless' );
}

/* ---------------------------------------------------------- Repair tools */

EstatTests::group( 'Integration: maintenance tools' );

$wpdb->query( 'DELETE FROM ' . Schema::table( 'estat_index' ) );
EstatTests::is( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'estat_index' ) ), 'the index is empty to begin with' );

$guard = 0;
do {
	$status = EstatOS\Search\SearchIndex::rebuild_batch( 50 );
	++$guard;
} while ( $guard < 20 && ! empty( $status['remaining'] ) );

EstatTests::ok(
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'estat_index' ) ) > 0,
	'rebuilding the index restores it'
);

/* ------------------------------------------------------------- Uninstall */

EstatTests::group( 'Integration: uninstall keeps data unless asked' );

$settings = Settings::all();
EstatTests::ok(
	empty( $settings['delete_data_on_uninstall'] ),
	'the destructive uninstall option is off by default'
);

$uninstall = ESTAT_DIR . 'uninstall.php';
EstatTests::ok( file_exists( $uninstall ), 'an uninstall script ships with the plugin' );

$source = (string) file_get_contents( $uninstall );
EstatTests::ok(
	false !== strpos( $source, 'WP_UNINSTALL_PLUGIN' ),
	'the uninstall script refuses to run unless WordPress called it'
);
EstatTests::ok(
	false !== strpos( $source, 'delete_data_on_uninstall' ),
	'the uninstall script checks the opt-in before deleting anything'
);
EstatTests::ok(
	Schema::healthy(),
	'merely loading the plugin never drops a table'
);
