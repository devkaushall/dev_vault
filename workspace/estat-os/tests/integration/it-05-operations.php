<?php
/**
 * Spreadsheet import and export, cron, privacy, practice mode, language and
 * the public-facing rendering.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Csv\Spreadsheet;
use EstatOS\Cron\Scheduler;
use EstatOS\Data\Listings;
use EstatOS\Frontend\Shortcodes;
use EstatOS\I18n\Language;
use EstatOS\Privacy\Privacy;
use EstatOS\Search\Query;
use EstatOS\Support\PracticeMode;

estat_reset_world();
estat_login_owner();

/* ------------------------------------------------------------- CSV shape */

EstatTests::group( 'Integration: spreadsheet columns' );

foreach ( array( 'listings', 'projects', 'agents', 'leads' ) as $kind ) {
	$columns = Spreadsheet::columns( $kind );
	EstatTests::ok( ! empty( $columns ), 'the ' . $kind . ' sheet defines columns' );
}

/* ------------------------------------------------------------ CSV import */

EstatTests::group( 'Integration: importing a spreadsheet' );

$csv = "external_id,title,offer,property_type,price,area,area_unit,bedrooms,locality\n"
	. "EXT-1,Two bedroom flat with balcony,sale,apartment,4500000,900,sqft,2,Saket\n"
	. "EXT-2,Three bedroom villa with garden,sale,villa,15000000,2200,sqft,3,Vasant Kunj\n";

// The importer only reads files inside the uploads folder, so write there.
$uploads = wp_get_upload_dir();
wp_mkdir_p( $uploads['basedir'] );
$path = $uploads['basedir'] . '/estat-import-test.csv';
file_put_contents( $path, $csv );

// Path traversal must be refused before anything is read.
$traversal = Spreadsheet::preview( '/etc/passwd' );
EstatTests::ok( is_wp_error( $traversal ), 'a file outside the uploads folder is refused' );
$traversal2 = Spreadsheet::preview( $uploads['basedir'] . '/../../../../etc/passwd' );
EstatTests::ok( is_wp_error( $traversal2 ), 'a directory traversal attempt is refused' );

$preview = Spreadsheet::preview( $path );
EstatTests::ok( ! empty( $preview['headers'] ), 'the preview reads the header row' );
EstatTests::ok( ! empty( $preview['rows'] ), 'the preview shows sample rows' );

$mapping = Spreadsheet::guess_mapping( $preview['headers'], 'listings' );
EstatTests::ok( ! empty( $mapping ), 'the importer guesses a column mapping' );
EstatTests::ok( in_array( 'title', $mapping, true ) || isset( $mapping['title'] ), 'the headline column is recognised' );

$job = Spreadsheet::create_job( $path, 'listings', $mapping );
EstatTests::ok( ! is_wp_error( $job ), 'an import job is created' );

$job_id = is_array( $job ) ? (int) ( $job['id'] ?? 0 ) : (int) $job;
$guard  = 0;
do {
	$state = Spreadsheet::process_batch( $job_id );
	++$guard;
} while ( $guard < 20 && is_array( $state ) && 'done' !== ( $state['state'] ?? 'done' ) );

$created = Listings::find_by_external_id( 'EXT-1' );
EstatTests::ok( $created > 0, 'the first spreadsheet row became a real listing' );
EstatTests::is(
	'draft',
	get_post_status( $created ),
	'imported listings arrive as drafts so somebody reviews them before they go live'
);
EstatTests::ok( Listings::find_by_external_id( 'EXT-2' ) > 0, 'the second row became a listing too' );

EstatTests::is(
	'4500000',
	(string) (float) get_post_meta( $created, '_estat_price', true ),
	'the imported price is stored as a number'
);

/* ------------------------------------------------- Import de-duplication */

EstatTests::group( 'Integration: re-importing the same sheet updates instead of duplicating' );

$path2  = $uploads['basedir'] . '/estat-import-test-2.csv';
file_put_contents( $path2, $csv );

$job2 = Spreadsheet::create_job( $path2, 'listings', $mapping );
$id2  = is_array( $job2 ) ? (int) ( $job2['id'] ?? 0 ) : (int) $job2;
$guard = 0;
do {
	$state2 = Spreadsheet::process_batch( $id2 );
	++$guard;
} while ( $guard < 20 && is_array( $state2 ) && 'done' !== ( $state2['state'] ?? 'done' ) );

EstatTests::is(
	$created,
	Listings::find_by_external_id( 'EXT-1' ),
	'a repeated reference number updates the same listing rather than creating a twin'
);

/* ------------------------------------------------------------ CSV export */

EstatTests::group( 'Integration: exporting a spreadsheet' );

$export = Spreadsheet::export( 'listings' );
EstatTests::ok( is_string( $export ) && '' !== $export, 'the export produces CSV text' );
EstatTests::ok( false !== strpos( $export, 'Two bedroom flat with balcony' ), 'the export contains a real listing' );

$lines = array_filter( explode( "\n", trim( $export ) ) );
EstatTests::ok( count( $lines ) >= 3, 'the export has a header row plus the listings' );

// A formula injection guard matters because these files open in Excel.
$evil_id = (int) Listings::save(
	array(
		'title'    => '=cmd|calc!A1 malicious headline text',
		'offer'    => 'sale',
		'property_type' => 'apartment',
		'locality' => 'Nowhere',
		'price'    => 100000,
		'status'   => 'publish',
	)
);
$export2 = Spreadsheet::export( 'listings' );
EstatTests::ok(
	false === strpos( $export2, "\n=cmd" ) && 0 !== strpos( $export2, '=cmd' ),
	'a cell starting with = is not written raw at the start of a line'
);

/* ---------------------------------------------------------------- Search */

EstatTests::group( 'Integration: searching the index' );

// Imports land as drafts, so publish one before searching the public index.
wp_update_post( array( 'ID' => $created, 'post_status' => 'publish' ) );
Listings::refresh_derived( $created );

$results = Query::search( array( 'keyword' => 'balcony' ) );
EstatTests::ok( isset( $results['ids'], $results['total'], $results['pages'] ), 'search returns ids, a total and a page count' );
EstatTests::ok( ! empty( $results['ids'] ), 'a keyword search finds a published listing' );

$draft_hidden = Query::search( array( 'keyword' => 'garden' ) );
EstatTests::ok( empty( $draft_hidden['ids'] ), 'a still-draft import does not show up in a public search' );

$by_offer = Query::search( array( 'offer' => 'sale' ) );
EstatTests::ok( ! empty( $by_offer['ids'] ), 'filtering by sale finds listings' );

$capped = Query::search( array( 'per_page' => 5000 ) );
EstatTests::ok(
	(int) $capped['per_page'] <= Query::MAX_PER_PAGE,
	'an absurd page size is capped so nobody can exhaust memory'
);

$price = Query::search( array( 'price_max' => 5000000 ) );
foreach ( $price['ids'] as $found_id ) {
	$found_price = (float) get_post_meta( (int) $found_id, '_estat_price', true );
	EstatTests::ok( $found_price <= 5000000.0, 'the price ceiling is respected' );
	break;
}

$drafts_hidden = Query::search( array() );
foreach ( $drafts_hidden['ids'] as $found_id ) {
	EstatTests::is( 'publish', get_post_status( (int) $found_id ), 'a public search only returns published listings' );
	break;
}

/* --------------------------------------------------------- Practice mode */

EstatTests::group( 'Integration: practice mode' );

$seeded = PracticeMode::seed();
EstatTests::ok( is_array( $seeded ), 'practice mode reports what it created' );
EstatTests::ok( (int) $seeded['listings'] > 0, 'practice mode adds sample listings' );

$cleared = PracticeMode::clear();
EstatTests::ok( is_array( $cleared ), 'clearing practice data reports what it removed' );
EstatTests::ok( (int) $cleared['posts'] > 0, 'the sample listings are removed again' );

EstatTests::ok(
	Listings::find_by_external_id( 'EXT-1' ) > 0,
	'clearing practice data leaves genuine imported listings untouched'
);

/* --------------------------------------------------------------- Cron */

EstatTests::group( 'Integration: scheduled housekeeping' );

Scheduler::schedule();
EstatTests::ok( (bool) wp_next_scheduled( 'estat_daily_maintenance' ), 'the daily job is scheduled' );
EstatTests::ok( (bool) wp_next_scheduled( 'estat_hourly_maintenance' ), 'the hourly job is scheduled' );

$daily_ok = true;
try {
	Scheduler::run_daily();
	Scheduler::run_hourly();
} catch ( Throwable $e ) {
	$daily_ok = false;
	EstatTests::ok( false, 'housekeeping threw: ' . $e->getMessage() );
}
if ( $daily_ok ) {
	EstatTests::ok( true, 'both housekeeping runs complete without error' );
}

Scheduler::unschedule();
EstatTests::ok( ! wp_next_scheduled( 'estat_daily_maintenance' ), 'deactivating clears the daily job' );

/* -------------------------------------------------------------- Privacy */

EstatTests::group( 'Integration: privacy export and erase' );

$pl = (int) EstatOS\Leads\Leads::create(
	array(
		'name'  => 'Privacy Person',
		'email' => 'privacy@example.test',
		'phone' => '+91 90000 55555',
	)
);

$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
EstatTests::ok( ! empty( $exporters ), 'the plugin registers a personal data exporter' );

$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
EstatTests::ok( ! empty( $erasers ), 'the plugin registers a personal data eraser' );

$export = Privacy::export( 'privacy@example.test', 1 );
EstatTests::ok( isset( $export['data'], $export['done'] ), 'the exporter returns the shape WordPress expects' );
EstatTests::ok( ! empty( $export['data'] ), 'the export found the enquiry for that address' );

$erase = Privacy::erase( 'privacy@example.test', 1 );
EstatTests::ok( isset( $erase['items_removed'], $erase['done'] ), 'the eraser returns the shape WordPress expects' );
EstatTests::ok( true === $erase['items_removed'] || (int) $erase['items_removed'] > 0, 'the eraser removed something' );

$after = EstatOS\Leads\Leads::get( $pl );
if ( is_array( $after ) ) {
	EstatTests::ok( false === strpos( (string) $after['email'], 'privacy@example.test' ), 'the address is gone after erasure' );
}

/* ------------------------------------------------------------- Language */

EstatTests::group( 'Integration: languages' );

$langs = Language::available();
EstatTests::ok( isset( $langs['en_US'] ), 'English is offered' );
EstatTests::ok( isset( $langs['en_IN_hinglish'] ), 'Hinglish is offered' );

foreach ( array_keys( $langs ) as $locale ) {
	$mo = ESTAT_DIR . 'languages/estat-os-' . $locale . '.mo';
	EstatTests::ok( file_exists( $mo ), 'a compiled translation file ships for ' . $locale );
}

/* ------------------------------------------------------------ Shortcodes */

EstatTests::group( 'Integration: shortcodes render without a theme' );

foreach ( array( 'estat_properties', 'estat_search', 'estat_team', 'estat_compare', 'estat_favorites', 'estat_language' ) as $tag ) {
	EstatTests::ok( shortcode_exists( $tag ), 'the shortcode ' . $tag . ' is registered' );
}

$rendered = do_shortcode( '[estat_properties per_page="3"]' );
EstatTests::ok( '' !== trim( $rendered ), 'the property list shortcode renders something' );
EstatTests::ok( false === strpos( $rendered, 'Fatal error' ), 'it does not emit an error' );

$search_html = do_shortcode( '[estat_search]' );
EstatTests::ok( false !== strpos( $search_html, '<form' ), 'the search shortcode renders a form' );

$team_html = do_shortcode( '[estat_team]' );
EstatTests::ok( is_string( $team_html ), 'the team shortcode returns markup' );

/* ------------------------------------------------- Price on request */

EstatTests::group( 'Integration: price on request stays private' );

$por = (int) Listings::save(
	array(
		'title'            => 'Discreet penthouse sale by appointment',
		'offer'            => 'sale',
		'property_type'    => 'apartment',
		'locality'         => 'Golf Links',
		'price'            => 250000000,
		'price_type'       => 'on_request',
		'status'           => 'publish',
	)
);
$card = EstatOS\Frontend\Components::card( $por );
EstatTests::ok( false === strpos( $card, '250000000' ), 'the raw number never reaches the page' );
EstatTests::ok( false === strpos( $card, '25 Cr' ), 'the formatted figure is withheld as well' );
EstatTests::ok(
	false !== stripos( $card, 'request' ),
	'the card says the price is on request instead'
);

// A normal listing must still show its price, otherwise the test above is vacuous.
$normal_card = EstatOS\Frontend\Components::card( $created );
EstatTests::ok( false !== strpos( $normal_card, 'Lakh' ) || false !== strpos( $normal_card, 'Cr' ), 'an ordinary listing does show its formatted price' );
