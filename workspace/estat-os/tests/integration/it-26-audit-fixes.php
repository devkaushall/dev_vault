<?php
/**
 * The eleven faults found by the sixty-pass audit, pinned so they cannot come
 * back.
 *
 * Three of them shared one shape, and it is worth naming because it is the
 * shape most likely to recur: the WRITE path and the READ path were built at
 * different times and nobody compared them. The editor collected a field, the
 * validator accepted it, the database stored it, and no reader ever asked for
 * it. Every individual piece worked. Only the join was missing.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Screens\ListingsScreen;
use EstatOS\Admin\Screens\Today;
use EstatOS\Csv\Spreadsheet;
use EstatOS\Data\Listings;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Projects;
use EstatOS\Data\Taxonomies;
use EstatOS\Leads\Leads;
use EstatOS\Leads\Visits;
use EstatOS\Settings\Settings;
use EstatOS\Support\Sanitize;

estat_reset_world();
estat_login_owner();

EstatTests::group( 'Fields the editor collects actually reach a reader' );

Settings::update( array( 'show_regulatory' => true ) );

$full = (int) Listings::save(
	array(
		'title'           => 'Every field filled',
		'offer'           => 'rent',
		'property_type'   => 'apartment',
		'locality'        => 'Saket',
		'price'           => 5000000,
		'rent'            => 45000,
		'deposit'         => 200000,
		'maintenance'     => 3000,
		'area'            => 1200,
		'area_unit'       => 'sqft',
		'area_type'       => 'carpet',
		'bedrooms'        => 3,
		'bathrooms'       => 2,
		'balconies'       => 2,
		'floor'           => 4,
		'total_floors'    => 12,
		'age'             => 5,
		'facing'          => 'north',
		'video_url'       => 'https://youtu.be/abc',
		'tour_url'        => 'https://tour.example/x',
		'developer'       => 'Acme Builders',
		'possession_date' => '2027-01-01',
		'regulatory_id'   => 'RERA-123',
		'status'          => 'publish',
	)
);

EstatTests::ok( $full > 0, 'a listing with every optional field saves' );

$public = Listings::to_array( $full, false );

/*
 * These twelve were stored and then shown to nobody: missing from to_array in
 * both modes, missing from REST, unread by the template. An office typed a
 * deposit and a video link into a field that went nowhere.
 */
foreach (
	array(
		'deposit'         => 200000.0,
		'maintenance'     => 3000.0,
		'balconies'       => 2,
		'total_floors'    => 12,
		'age'             => 5,
		'facing'          => 'north',
		'area_type'       => 'carpet',
		'video_url'       => 'https://youtu.be/abc',
		'tour_url'        => 'https://tour.example/x',
		'developer'       => 'Acme Builders',
		'possession_date' => '2027-01-01',
		'regulatory_id'   => 'RERA-123',
	) as $field => $expected
) {
	EstatTests::ok( array_key_exists( $field, $public ), 'to_array() exposes "' . $field . '"' );
	EstatTests::is( $expected, $public[ $field ] ?? null, 'and "' . $field . '" carries the value that was saved' );
}

// Attachment lists must be arrays of IDs, never a raw meta blob.
EstatTests::ok( is_array( $public['floor_plans'] ?? null ), 'floor plans come back as an array' );
EstatTests::ok( is_array( $public['brochures'] ?? null ), 'brochures come back as an array' );

// A registration number is only public when the office has switched it on.
Settings::update( array( 'show_regulatory' => false ) );

EstatTests::ok(
	! array_key_exists( 'regulatory_id', Listings::to_array( $full, false ) ),
	'with registration numbers switched off, the number is not exposed at all'
);

Settings::update( array( 'show_regulatory' => true ) );

EstatTests::group( 'A project can be read, not just written' );

$project = (int) Projects::save(
	array(
		'title'           => 'Readable society',
		'developer'       => 'Acme Builders',
		'project_status'  => 'ready',
		'price_min'       => 4000000,
		'price_max'       => 9000000,
		'total_units'     => 120,
		'available_units' => 30,
		'possession_date' => '2027-06-01',
		'unit_types'      => '2 BHK, 3 BHK',
		'regulatory_id'   => 'R-1',
		'status'          => 'publish',
	)
);

$has_reader = method_exists( Projects::class, 'to_array' );

EstatTests::ok( $has_reader, 'Projects has a to_array(), like Listings and Agents' );

/*
 * Guard the calls below. Without this, removing to_array() fatals the whole
 * run at this line, which stops every later group from reporting - a real
 * failure would hide behind it.
 */
$read = $has_reader ? Projects::to_array( $project ) : array();

foreach (
	array(
		'developer'       => 'Acme Builders',
		'stage'           => 'ready',
		'price_min'       => 4000000.0,
		'price_max'       => 9000000.0,
		'total_units'     => 120,
		'available_units' => 30,
		'possession_date' => '2027-06-01',
		'unit_types'      => '2 BHK, 3 BHK',
		'regulatory_id'   => 'R-1',
	) as $field => $expected
) {
	EstatTests::is( $expected, $read[ $field ] ?? null, 'the project exposes "' . $field . '"' );
}

// A formatted price is only useful if the raw one is there too, so a caller
// can tell "not stated" from "zero".
EstatTests::ok( '' !== (string) $read['price_min_display'], 'a stated price range is formatted for display' );

$blank = $has_reader
	? Projects::to_array(
		(int) Projects::save(
			array(
				'title'  => 'Nothing filled in',
				'status' => 'publish',
			)
		)
	)
	: array(
		'price_min_display' => '',
		'price_min'         => 0.0,
	);

EstatTests::is( '', $blank['price_min_display'], 'an unstated price formats as nothing, not as zero rupees' );
EstatTests::is( 0.0, $blank['price_min'], 'and the raw value is zero, so a caller can tell the difference' );

if ( $has_reader ) {
	EstatTests::is( array(), Projects::to_array( 999999 ), 'an unknown id returns an empty array, not a broken one' );
	EstatTests::is( array(), Projects::to_array( $full ), 'a listing id is refused by the project reader' );
}

EstatTests::group( 'The single-listing and single-project templates read the new fields' );

$listing_tpl = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Frontend/templates/single-listing.php' );
$project_tpl = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Frontend/templates/single-project.php' );

foreach ( array( 'deposit', 'maintenance', 'video_url', 'tour_url', 'possession_date', 'developer', 'balconies', 'facing' ) as $field ) {
	EstatTests::ok(
		false !== strpos( $listing_tpl, "'" . $field . "'" ),
		'the property page shows "' . $field . '"'
	);
}

foreach ( array( 'developer', 'unit_types', 'total_units', 'possession_date' ) as $field ) {
	EstatTests::ok(
		false !== strpos( $project_tpl, "'" . $field . "'" ),
		'the project page shows "' . $field . '"'
	);
}

EstatTests::group( 'Erase everything really does erase everything' );

/*
 * uninstall.php cannot load the plugin, so its lists of post types and
 * taxonomies are hand-written. They drifted: Insights was added later and
 * never added there, so an office that ticked "erase everything" kept every
 * article for ever.
 */
$uninstall = (string) file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' );

preg_match( '/\$post_types = array\((.*?)\);/s', $uninstall, $pt_match );
preg_match( '/foreach \( array\((.*?)\) as \$taxonomy/s', $uninstall, $tx_match );

$deleted_types      = preg_match_all( "/'(estat_[a-z_]+)'/", (string) ( $pt_match[1] ?? '' ), $pt ) ? $pt[1] : array();
$deleted_taxonomies = preg_match_all( "/'(estat_[a-z_]+)'/", (string) ( $tx_match[1] ?? '' ), $tx ) ? $tx[1] : array();

$declared_types = array(
	PostTypes::LISTING,
	PostTypes::PROJECT,
	PostTypes::AGENT,
	PostTypes::AGENCY,
	PostTypes::DOCUMENT,
	PostTypes::INSIGHT,
);

foreach ( $declared_types as $type ) {
	EstatTests::ok(
		in_array( $type, $deleted_types, true ),
		'uninstall deletes the "' . $type . '" content'
	);
}

$declared_taxonomies = array(
	Taxonomies::LOCALITY,
	Taxonomies::FEATURE,
	Taxonomies::AMENITY,
	Taxonomies::INSIGHT_KIND,
	Taxonomies::INSIGHT_TOPIC,
);

foreach ( $declared_taxonomies as $taxonomy ) {
	EstatTests::ok(
		in_array( $taxonomy, $deleted_taxonomies, true ),
		'uninstall deletes the "' . $taxonomy . '" terms'
	);
}

// And nothing is dropped that was never declared, which would be its own bug.
EstatTests::is(
	array(),
	array_values( array_diff( $deleted_types, $declared_types ) ),
	'uninstall does not delete a content type the plugin never registered'
);

EstatTests::group( 'Exporting site visits gives site visits' );

$lead = (int) Leads::create(
	array(
		'name'  => 'Visiting person',
		'phone' => '9876500001',
	)
);

Visits::schedule(
	array(
		'lead_id'      => $lead,
		'listing_id'   => $full,
		'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
	)
);

$csv    = Spreadsheet::export( 'visits', 50 );
$header = (string) strtok( $csv, "\n" );

EstatTests::ok(
	false !== strpos( $header, 'scheduled_at' ),
	'the visits export really has visit columns'
);
EstatTests::ok(
	false === strpos( $header, 'property_type' ),
	'and not the listings columns it used to fall back to'
);
EstatTests::ok(
	false !== strpos( $csv, 'Visiting person' ),
	'the visitor appears in the file'
);
EstatTests::ok(
	substr_count( trim( $csv ), "\n" ) >= 1,
	'the file has at least one row, not just a header'
);

// The screen offers exactly the kinds the exporter supports.
$screen = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Admin/Screens/SpreadsheetScreen.php' );

preg_match_all( "/'(listings|projects|agents|leads|visits)'\s*=>\s*__\(\s*'Export/", $screen, $offered );

foreach ( array_unique( $offered[1] ) as $kind ) {
	$sample = Spreadsheet::export( $kind, 1 );
	$first  = (string) strtok( $sample, "\n" );
	$want   = array_keys( Spreadsheet::columns( $kind ) );

	EstatTests::ok(
		'' !== $first && false !== strpos( $first, (string) $want[0] ),
		'the "' . $kind . '" export button produces ' . $kind . ' columns'
	);
}

// A visit belongs to an enquiry, so it must not be importable.
EstatTests::ok(
	false === strpos( $screen, "'visits' => __( 'Import" ),
	'visits are exported but never imported, because a visit needs an enquiry'
);

EstatTests::group( 'One caller, one enquiry, however they type their number' );

estat_reset_world();
estat_login_owner();

$ids = array();

foreach ( array( '9876543210', '98765 43210', '+91-9876543210', '+919876543210', '0919876543210', '(0) 98765 43210' ) as $typed ) {
	$ids[] = (int) Leads::create(
		array(
			'name'  => 'Ravi',
			'phone' => $typed,
		)
	);
}

EstatTests::is( 1, count( array_unique( $ids ) ), 'six ways of typing one number make one enquiry, not six' );

// Two genuinely different people must stay apart.
$other = (int) Leads::create(
	array(
		'name'  => 'Someone else',
		'phone' => '9000000001',
	)
);

EstatTests::ok( $other !== $ids[0], 'a different number is still a different person' );

// A short number must not be padded or truncated into a collision.
$short_a = (int) Leads::create( array( 'name' => 'Short A', 'phone' => '123456789' ) );
$short_b = (int) Leads::create( array( 'name' => 'Short B', 'phone' => '987654321' ) );

EstatTests::ok( $short_a !== $short_b, 'two different short numbers stay two people' );

EstatTests::group( 'Every screen checks permission itself' );

/*
 * Twelve screens called current_user_can() before rendering. Today and the
 * Listings list did not, and leaned on the WordPress menu capability alone.
 * That is usually enough - but "usually" is not a permission check.
 */
estat_login_with( 9, array( 'read' ) );

foreach (
	array(
		'Today'          => array( Today::class, 'render' ),
		'ListingsScreen' => array( ListingsScreen::class, 'render' ),
	) as $name => $callable
) {
	$_GET     = array( 'page' => 'estat-listings' );
	$_REQUEST = $_GET;

	$refused = false;

	ob_start();
	try {
		call_user_func( $callable );
	} catch ( \Throwable $e ) {
		$refused = false !== strpos( get_class( $e ), 'Die' );
	}
	ob_end_clean();

	EstatTests::ok( $refused, $name . ' refuses a user without office permission' );
}

$_GET     = array();
$_REQUEST = array();

estat_login_owner();

EstatTests::group( 'Text that becomes a heading is bounded and clean' );

// Core's sanitize_text_field() leaves a NUL byte in place; a NUL truncates a
// title in anything that passes the string to a C-level function.
EstatTests::ok(
	false === strpos( Sanitize::text( "Null\x00byte" ), "\x00" ),
	'a NUL byte is stripped'
);
EstatTests::is( 'Badtext', Sanitize::text( "Bad\x07\x1btext" ), 'other control characters are stripped' );
EstatTests::ok( false !== strpos( Sanitize::text( "a\tb" ), 'b' ), 'ordinary whitespace still survives' );

// A 6,000-character headline saved happily and wrecked every table it hit.
$long  = str_repeat( 'Very long headline ', 400 );
$title = Sanitize::title( $long );

EstatTests::ok( strlen( $title ) <= 180, 'a runaway title is cut to a usable length (' . strlen( $title ) . ')' );
EstatTests::ok( strlen( $title ) > 100, 'but not cut so short it loses its meaning' );
EstatTests::ok( ' ' !== substr( $title, -1 ), 'and it does not end mid-space' );

EstatTests::is(
	'3 BHK apartment near the metro',
	Sanitize::title( '3 BHK apartment near the metro' ),
	'a normal title is returned untouched'
);

$saved = (int) Listings::save(
	array(
		'title'         => $long,
		'offer'         => 'sale',
		'property_type' => 'apartment',
		'locality'      => 'Saket',
		'price'         => 5000000,
		'area'          => 900,
		'area_unit'     => 'sqft',
		'status'        => 'draft',
	)
);

EstatTests::ok( strlen( (string) get_the_title( $saved ) ) <= 180, 'the cap is applied on the way into storage' );

EstatTests::group( 'Loose ends from the audit' );

// Schema::drop() kept a second copy of the table list that uninstall.php also
// holds, so two places had to be kept in step and one of them was dead.
EstatTests::ok(
	! method_exists( 'EstatOS\\Install\\Schema', 'drop' ),
	'the unused Schema::drop() and its duplicate table list are gone'
);

// One block read estatAdmin.strings while PHP localises estatAdmin.i18n.
$admin_js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/admin.js' );

EstatTests::ok(
	false === strpos( $admin_js, 'estatAdmin.strings' ),
	'no JavaScript reads a localisation key PHP does not provide'
);
EstatTests::ok(
	substr_count( $admin_js, 'estatAdmin.i18n' ) >= 4,
	'every block reads the key that is really there'
);

EstatTests::group( 'A page number never exceeds the number of pages' );

/*
 * search() used to hand back whatever page was asked for. Asking for page
 * 100000 of a two-page result answered page=100000, pages=2, so anything
 * printing "page X of Y" printed a page that cannot exist and paginate_links()
 * was given a current page outside its own range.
 */
estat_reset_world();
estat_login_owner();

for ( $i = 1; $i <= 3; $i++ ) {
	Listings::save(
		array(
			'title'         => 'Clamp home ' . $i,
			'offer'         => 'sale',
			'property_type' => 'apartment',
			'locality'      => 'Saket',
			'price'         => 5000000,
			'area'          => 900,
			'area_unit'     => 'sqft',
			'status'        => 'publish',
		)
	);
}

$page_one = \EstatOS\Search\Query::search( array( 'page' => 1, 'per_page' => 2 ) );
$page_two = \EstatOS\Search\Query::search( array( 'page' => 2, 'per_page' => 2 ) );
$beyond   = \EstatOS\Search\Query::search( array( 'page' => 100000, 'per_page' => 2 ) );

EstatTests::is( 2, $page_one['pages'], 'three listings at two a page is two pages' );
EstatTests::is( 1, $page_one['page'], 'page one is reported as page one' );
EstatTests::is( 2, $page_two['page'], 'page two is still reported as page two, not clamped away' );
EstatTests::is( 2, $beyond['page'], 'a page past the end is clamped to the last real page' );
EstatTests::ok(
	$beyond['page'] <= $beyond['pages'],
	'the page returned is never greater than the number of pages'
);

/*
 * An empty result set must still report a usable page number. Clamping to
 * $pages without a floor gives page=0 on an empty search, and paginate_links()
 * treats 0 as "no current page".
 *
 * This needs a genuinely empty index, not just a query that matches nothing:
 * an earlier version searched for a nonsense word while other tests' listings
 * were still indexed, so total was 0 but the clamp branch behaved differently
 * from a truly empty office.
 */
estat_reset_world();
estat_login_owner();

$nothing = \EstatOS\Search\Query::search( array( 'page' => 50, 'q' => 'zzzz-no-such-property' ) );

EstatTests::is( 0, $nothing['total'], 'the search really did match nothing' );
EstatTests::is( 1, $nothing['page'], 'an empty result reports page one, not page fifty' );
EstatTests::ok( $nothing['page'] >= 1, 'and never page zero, which would break paginate_links()' );

$no_index = \EstatOS\Search\Query::search( array( 'page' => 3 ) );

EstatTests::ok( $no_index['page'] >= 1, 'an empty office never reports page zero either' );

EstatTests::group( 'The plugin can actually be distributed' );

/*
 * Every plugin in the WordPress.org directory needs a readme.txt: the stable
 * tag, the tested-up-to version and the description are read from it. Without
 * one the plugin cannot be listed, and an installer has no standard place to
 * check what it was tested against.
 */
$readme_path = dirname( __DIR__, 2 ) . '/readme.txt';

EstatTests::ok( file_exists( $readme_path ), 'readme.txt exists' );

$readme = file_exists( $readme_path ) ? (string) file_get_contents( $readme_path ) : '';

foreach ( array( 'Stable tag', 'Requires at least', 'Requires PHP', 'License', 'Tags' ) as $header ) {
	EstatTests::ok( false !== strpos( $readme, $header . ':' ), 'readme.txt declares "' . $header . '"' );
}

// The three places a version is written must never disagree; a stale stable
// tag is the classic way a release ships and nobody can install it.
$plugin_file = (string) file_get_contents( dirname( __DIR__, 2 ) . '/estat-os.php' );
$changelog   = (string) file_get_contents( dirname( __DIR__, 2 ) . '/CHANGELOG.md' );

preg_match( '/Version:\s*([0-9.]+)/', $plugin_file, $header_version );
preg_match( "/define\(\s*'ESTAT_VERSION',\s*'([0-9.]+)'/", $plugin_file, $constant_version );
preg_match( '/Stable tag:\s*([0-9.]+)/', $readme, $stable_tag );
preg_match( '/## \[([0-9.]+)\]/', $changelog, $changelog_version );

EstatTests::is( $header_version[1] ?? 'a', $constant_version[1] ?? 'b', 'the plugin header and ESTAT_VERSION agree' );
EstatTests::is( $header_version[1] ?? 'a', $stable_tag[1] ?? 'c', 'the readme stable tag agrees with the plugin header' );
EstatTests::is( $header_version[1] ?? 'a', $changelog_version[1] ?? 'd', 'the changelog agrees too' );

// And the PHP requirement must be the same in both places, or the installer
// and the directory disagree about who can run it.
preg_match( '/Requires PHP:\s*([0-9.]+)/', $plugin_file, $php_header );
preg_match( '/Requires PHP:\s*([0-9.]+)/', $readme, $php_readme );

EstatTests::is( $php_header[1] ?? 'a', $php_readme[1] ?? 'b', 'the PHP requirement matches in both files' );
