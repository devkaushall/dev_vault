<?php
/**
 * Admin screens, form handlers and the Elementor bridge.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Actions;
use EstatOS\Admin\Admin;
use EstatOS\Integrations\Elementor\Bridge;

estat_reset_world();
estat_login_owner();

/* ------------------------------------------------------- Elementor guard */

EstatTests::group( 'Integration: Elementor stays optional' );

EstatTests::ok( ! Bridge::active(), 'without Elementor installed the bridge reports inactive' );

$threw = false;
try {
	Bridge::register_widgets( new stdClass() );
} catch ( Throwable $e ) {
	$threw = true;
}
EstatTests::ok( ! $threw, 'registering widgets without Elementor present does not crash the site' );

/* --------------------------------------------------------- Admin screens */

EstatTests::group( 'Integration: every admin screen renders' );

$GLOBALS['estat_is_admin'] = true;

$screens = array(
	'Today'       => EstatOS\Admin\Screens\Today::class,
	'AddHome'     => EstatOS\Admin\Screens\AddHome::class,
	'Listings'    => EstatOS\Admin\Screens\ListingsScreen::class,
	'Enquiries'   => EstatOS\Admin\Screens\Enquiries::class,
	'Visits'      => EstatOS\Admin\Screens\VisitsScreen::class,
	'Projects'    => EstatOS\Admin\Screens\ProjectsScreen::class,
	'Team'        => EstatOS\Admin\Screens\TeamScreen::class,
	'Forms'       => EstatOS\Admin\Screens\FormsScreen::class,
	'Spreadsheet' => EstatOS\Admin\Screens\SpreadsheetScreen::class,
	'Reports'     => EstatOS\Admin\Screens\Reports::class,
	'Settings'    => EstatOS\Admin\Screens\SettingsScreen::class,
);

foreach ( $screens as $label => $class ) {
	if ( ! class_exists( $class ) ) {
		EstatTests::ok( false, 'the ' . $label . ' screen class exists' );
		continue;
	}
	if ( ! method_exists( $class, 'render' ) ) {
		EstatTests::ok( false, 'the ' . $label . ' screen has a render method' );
		continue;
	}

	ob_start();
	$error = '';
	try {
		call_user_func( array( $class, 'render' ) );
	} catch ( Throwable $e ) {
		$error = $e->getMessage();
	}
	$output = (string) ob_get_clean();

	EstatTests::is( '', $error, 'the ' . $label . ' screen renders without throwing' );
	EstatTests::ok( '' !== trim( $output ), 'the ' . $label . ' screen produces markup' );
	EstatTests::ok(
		false === stripos( $output, 'custom post type' )
		&& false === stripos( $output, 'taxonomy' )
		&& false === stripos( $output, 'post meta' ),
		'the ' . $label . ' screen avoids WordPress jargon'
	);
}

/* ------------------------------------------------------- Nonce enforcement */

EstatTests::group( 'Integration: admin actions demand a valid nonce' );

$_REQUEST = array();
$_POST    = array();
$_GET     = array();

// A forged request with no nonce must be stopped before anything is written.
$_REQUEST['estat_action'] = 'save_settings';
$_POST['estat_action']    = 'save_settings';
$_POST['office_name']    = 'Hijacked Office Name';

$stopped = false;
try {
	Actions::handle();
} catch ( Estat_Die_Exception $e ) {
	$stopped = true;
} catch ( Estat_Redirect_Exception $e ) {
	$stopped = true;
}

EstatTests::ok( $stopped, 'a settings save with no nonce is stopped' );
EstatTests::ok(
	'Hijacked Office Name' !== EstatOS\Settings\Settings::get( 'office_name' ),
	'the forged value was never written'
);

$_REQUEST = array();
$_POST    = array();

/* ------------------------------------------------- Destructive confirmation */

EstatTests::group( 'Integration: destructive actions need typed confirmation' );

$lead_id = (int) EstatOS\Leads\Leads::create(
	array(
		'name'  => 'Keep This Person',
		'phone' => '+91 90000 77777',
	)
);

$_REQUEST = array(
	'estat_action' => 'erase_lead',
	'lead'        => (string) $lead_id,
	'_wpnonce'    => wp_create_nonce( 'estat_erase_lead_' . $lead_id ),
	'confirm'     => 'nope',
);
$_POST = $_REQUEST;

try {
	Actions::handle();
} catch ( Throwable $e ) {
	// A redirect back with a notice is the expected outcome.
}

$still = EstatOS\Leads\Leads::get( $lead_id );
EstatTests::ok(
	is_array( $still ) && 0 === (int) $still['erased'],
	'erasing without typing DELETE leaves the record intact'
);

$_REQUEST = array();
$_POST    = array();

/* -------------------------------------------------------------- Menu shape */

EstatTests::group( 'Integration: the menu speaks plain language' );

$GLOBALS['estat_admin_menu'] = array();
$admin = new Admin();
$admin->add_menu();

$titles = array();
foreach ( $GLOBALS['estat_admin_menu'] as $entry ) {
	$titles[] = (string) ( $entry['menu_title'] ?? '' );
}
$joined = strtolower( implode( ' | ', $titles ) );

// Gallery and Insights deliberately reuse the built-in WordPress screens
// rather than reinventing the media library and the post editor.
foreach ( array( 'today', 'add a home', 'listings', 'enquiries', 'site visits', 'societies & projects', 'team', 'gallery & media', 'insights', 'forms', 'spreadsheet', 'reports', 'office settings' ) as $expected ) {
	EstatTests::ok( false !== strpos( $joined, $expected ), 'the menu offers "' . $expected . '"' );
}

EstatTests::ok( false === strpos( $joined, 'post type' ), 'the menu never says post type' );
EstatTests::ok( false === strpos( $joined, 'taxonomy' ), 'the menu never says taxonomy' );

$GLOBALS['estat_is_admin'] = false;
