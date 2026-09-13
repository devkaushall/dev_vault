<?php
/**
 * The property editor should feel short, without losing a single field.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Screens\ListingsScreen;
use EstatOS\Admin\Screens\Partials;
use EstatOS\Data\Listings;

estat_reset_world();
estat_login_owner();

/**
 * Render the property editor for one listing.
 *
 * @param int $listing_id Listing ID.
 * @return string
 */
function estat_editor_html( int $listing_id ): string {
	$_GET = array(
		'page' => 'estat-listings',
		'edit' => $listing_id,
	);
	ob_start();
	try {
		ListingsScreen::render();
	} catch ( \Throwable $e ) {
		$unused = $e;
	}
	$html = (string) ob_get_contents();
	ob_end_clean();
	return $html;
}

/**
 * A listing with only the basics filled in.
 *
 * @param string $title Title.
 * @param string $offer Offer type.
 * @return int
 */
function estat_plain_listing( string $title, string $offer = 'sale' ): int {
	return (int) Listings::save(
		array(
			'title'         => $title,
			'offer'         => $offer,
			'property_type' => 'apartment',
			'locality'      => 'Saket',
			'price'         => 2500000,
			'area'          => 1000,
			'area_unit'     => 'sqft',
			'status'        => 'publish',
		)
	);
}

/* ================================================= Nothing has been removed */

EstatTests::group( 'Simpler editor: every field is still there' );

$plain = estat_plain_listing( 'A plain flat with no extras at all' );
$html  = estat_editor_html( $plain );

// The exact set of fields the editor is contracted to offer. If a future tidy-up
// drops one of these, the office silently loses the ability to record it.
$expected = array(
	'title',
	'description',
	'locality',
	'amenities',
	'features',
	'cover_id',
	'_estat_offer',
	'_estat_property_type',
	'_estat_price_type',
	'_estat_price',
	'_estat_rent',
	'_estat_deposit',
	'_estat_maintenance',
	'_estat_area',
	'_estat_area_unit',
	'_estat_area_type',
	'_estat_bedrooms',
	'_estat_bathrooms',
	'_estat_balconies',
	'_estat_parking',
	'_estat_floor',
	'_estat_total_floors',
	'_estat_age',
	'_estat_gallery',
	'_estat_floor_plans',
	'_estat_brochures',
	'_estat_video_url',
	'_estat_tour_url',
	'_estat_address',
	'_estat_facing',
	'_estat_furnishing',
	'_estat_latitude',
	'_estat_longitude',
	'_estat_availability',
	'_estat_construction',
	'_estat_verification',
	'_estat_agent_id',
	'_estat_project_id',
	'_estat_developer',
	'_estat_possession_date',
	'_estat_featured',
	'_estat_investment',
	'_estat_expiry_date',
	'_estat_external_id',
	'_estat_internal_notes',
);

$missing = array();
foreach ( $expected as $field ) {
	if ( false === strpos( $html, 'name="' . $field . '"' ) && false === strpos( $html, 'name="' . $field . '[]"' ) ) {
		$missing[] = $field;
	}
}
EstatTests::is( array(), $missing, 'not one of the 45 fields was lost when the screen was tidied' );

/* ============================================== The everyday screen is short */

EstatTests::group( 'Simpler editor: the everyday fields come first' );

EstatTests::is( 5, substr_count( $html, '<details class="estat-extras"' ), 'the rarely used fields sit in five fold-away groups' );
EstatTests::is( 0, substr_count( $html, '<details class="estat-extras" open' ), 'and on a plain listing every one of them is shut' );

// The things an office fills in for nearly every property must not be buried.
// The screen is three chapters, and each has its own fold, so an everyday field
// must come before the first fold *of its own chapter* — not before the first
// fold on the page, which lives in chapter one.
$chapters = preg_split( '/<details class="estat-chapter"/', $html );
EstatTests::is( 3, count( $chapters ) - 1, 'the editor is still three chapters' );

$everyday = array(
	// chapter => fields that must not be folded away in it.
	1 => array( 'title', '_estat_price', '_estat_area', '_estat_bedrooms', '_estat_bathrooms', 'locality' ),
	2 => array( 'description', 'cover_id' ),
	3 => array( '_estat_availability', '_estat_agent_id' ),
);

$buried = array();
foreach ( $everyday as $chapter_number => $fields ) {
	$chapter    = (string) $chapters[ $chapter_number ];
	$first_fold = strpos( $chapter, '<details class="estat-extras"' );
	$first_fold = false === $first_fold ? strlen( $chapter ) : $first_fold;
	foreach ( $fields as $field ) {
		$at = strpos( $chapter, 'name="' . $field . '"' );
		if ( false === $at || $at > $first_fold ) {
			$buried[] = $field;
		}
	}
}
EstatTests::is( array(), $buried, 'in every chapter, the everyday fields come before that chapter\'s fold' );

/* ================================ A fold that holds something opens by itself */

EstatTests::group( 'Simpler editor: filled-in details are never hidden' );

$rental = estat_plain_listing( 'A rented flat with deposit details', 'rent' );
Listings::save(
	array(
		'_estat_rent'       => 45000,
		'_estat_deposit'    => 150000,
		'_estat_developer'  => 'DLF',
	),
	$rental
);
$rental_html = estat_editor_html( $rental );

EstatTests::is(
	2,
	substr_count( $rental_html, '<details class="estat-extras" open' ),
	'the two groups that hold real information open on their own'
);
EstatTests::ok(
	false !== strpos( $rental_html, 'value="45000"' ),
	'and the rent the office typed is visible without hunting for it'
);

EstatTests::ok( Partials::has_content( array( '', 0, 'something' ) ), 'a group with any real value counts as filled' );
EstatTests::ok( ! Partials::has_content( array( '', '0', 0, array() ) ), 'a group of blanks and zeroes counts as empty' );

/* ================================================ Folding away breaks nothing */

EstatTests::group( 'Simpler editor: a folded field still saves and comes back' );

$trip   = estat_plain_listing( 'A flat for the round trip check', 'rent' );
$hidden = array(
	'_estat_rent'            => 45000,
	'_estat_deposit'         => 150000,
	'_estat_maintenance'     => 3000,
	'_estat_balconies'       => 2,
	'_estat_parking'         => 1,
	'_estat_floor'           => 7,
	'_estat_total_floors'    => 14,
	'_estat_age'             => 5,
	'_estat_developer'       => 'DLF',
	'_estat_possession_date' => '2027-06-01',
	'_estat_external_id'     => 'REF-991',
	'_estat_latitude'        => '28.6139',
	'_estat_longitude'       => '77.2090',
	'_estat_facing'          => 'east',
	'_estat_video_url'       => 'https://youtu.be/abc',
);
Listings::save( $hidden, $trip );

$lost = array();
foreach ( $hidden as $key => $value ) {
	$stored = get_post_meta( $trip, $key, true );
	if ( (string) $stored !== (string) $value && (float) $stored !== (float) $value ) {
		$lost[] = $key;
	}
}
EstatTests::is( array(), $lost, 'every folded-away field saves to the database exactly as typed' );

$trip_html = estat_editor_html( $trip );
foreach ( array( '45000', '150000', 'DLF', 'REF-991', '28.6139', '2027-06-01' ) as $value ) {
	EstatTests::ok(
		false !== strpos( $trip_html, $value ),
		'the saved value ' . $value . ' comes back into the reopened form'
	);
}

/* =========================================================== Still accessible */

EstatTests::group( 'Simpler editor: the folds are usable' );

EstatTests::ok( false !== strpos( $html, 'estat-extras-title' ), 'each fold has a plain-language heading' );
EstatTests::ok( false !== strpos( $html, 'estat-extras-note' ), 'and a line saying it is safe to skip' );

$css = (string) file_get_contents( ESTAT_DIR . 'assets/css/admin.css' );
EstatTests::ok( false !== strpos( $css, '.estat-screen .estat-extras' ), 'the folds are styled' );
EstatTests::ok( false !== strpos( $css, '.estat-extras > summary:focus-visible' ), 'and can be reached with a keyboard' );
