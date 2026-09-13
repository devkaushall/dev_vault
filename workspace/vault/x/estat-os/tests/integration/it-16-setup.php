<?php
/**
 * First-run setup: a new office should get a working website, not a blank one.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Actions;
use EstatOS\Admin\Screens\SetupScreen;
use EstatOS\Data\Listings;
use EstatOS\Install\Pages;
use EstatOS\Settings\Settings;

estat_reset_world();
estat_login_owner();

/**
 * Capture a screen without letting a fatal escape.
 *
 * @param callable $render Renderer.
 * @return string
 */
function estat_capture( callable $render ): string {
	ob_start();
	try {
		$render();
	} catch ( \Throwable $e ) {
		// The assertions below report the missing content.
		$unused = $e;
	}
	$html = (string) ob_get_contents();
	ob_end_clean();
	return $html;
}

/* ================================================== Before setup has run */

EstatTests::group( 'Setup: a brand new office is offered help' );

EstatTests::ok( SetupScreen::needed(), 'a new office is told it still needs setting up' );
EstatTests::is( array(), Pages::existing(), 'and it starts with no website pages at all' );

$wizard = estat_capture(
	static function () {
		$_GET = array( 'page' => SetupScreen::SLUG );
		SetupScreen::render();
	}
);

EstatTests::ok( false !== strpos( $wizard, 'office_name' ), 'the wizard asks for the office name' );
EstatTests::ok( false !== strpos( $wizard, 'name="phone"' ), 'the wizard asks for a phone number' );
EstatTests::is( 4, substr_count( $wizard, 'name="pages[]"' ), 'it offers all four pages to build' );
EstatTests::ok( false !== strpos( $wizard, 'page_style' ), 'it asks how those pages should look' );
EstatTests::ok( false !== strpos( $wizard, 'estat_action" value="run_setup' ), 'and the form runs the setup action' );
EstatTests::ok( false !== strpos( $wizard, 'skip_setup' ), 'an office that wants no help can skip it' );

/* ======================================================== Running setup */

EstatTests::group( 'Setup: answering the questions builds a working website' );

/**
 * Post the setup form.
 *
 * @param array<string,mixed> $overrides Field overrides.
 * @return string Notice key.
 */
function estat_submit_setup( array $overrides = array() ): string {
	$_POST    = array_merge(
		array(
			'estat_action'      => 'run_setup',
			'estat_nonce'       => wp_create_nonce( 'estat_run_setup' ),
			'office_name'       => 'Sharma Properties',
			'phone'             => '+91 98765 43210',
			'email'             => 'office@sharma.test',
			'cities'            => 'Delhi, Gurgaon, Noida',
			'currency_symbol'   => 'Rs',
			'default_area_unit' => 'sqft',
			'pages'             => array( 'properties', 'contact' ),
			'page_style'        => 'ready',
		),
		$overrides
	);
	$_REQUEST = $_POST;
	$_GET     = array();
	try {
		Actions::handle();
		return 'no-redirect';
	} catch ( Estat_Redirect_Exception $e ) {
		return preg_match( '/estat_notice=([a-z_]+)/', $e->getMessage(), $m ) ? $m[1] : 'unknown';
	}
}

EstatTests::is( 'error', estat_submit_setup( array( 'office_name' => '' ) ), 'setup will not run without an office name' );
EstatTests::is( array(), Pages::existing(), 'and nothing was built on that failed attempt' );

EstatTests::is( 'setup_done', estat_submit_setup(), 'a complete answer finishes setup' );

$settings = Settings::all();
EstatTests::is( 'Sharma Properties', (string) $settings['office_name'], 'the office name was saved' );
EstatTests::is( '+91 98765 43210', (string) $settings['phone'], 'the phone number was saved' );
EstatTests::is( 'office@sharma.test', (string) $settings['email'], 'the email address was saved' );
EstatTests::is( 'Rs', (string) $settings['currency_symbol'], 'the currency sign was saved' );
EstatTests::is(
	array( 'Delhi', 'Gurgaon', 'Noida' ),
	(array) $settings['cities'],
	'a comma separated list of areas is split into separate areas'
);

$pages = Pages::existing();
EstatTests::is( 2, count( $pages ), 'exactly the two pages that were ticked got built' );
EstatTests::ok( isset( $pages['properties'] ), 'the properties page exists' );
EstatTests::ok( isset( $pages['contact'] ), 'the contact page exists' );
EstatTests::ok( ! isset( $pages['team'] ), 'a page that was not ticked was not built' );

$properties_page = get_post( $pages['properties'] );
EstatTests::is( 'publish', (string) $properties_page->post_status, 'the page is live, not left as a draft' );
EstatTests::ok(
	false !== strpos( (string) $properties_page->post_content, '[estat_properties]' ),
	'the properties page actually shows the properties'
);
EstatTests::ok(
	false !== strpos( (string) $properties_page->post_content, '[estat_search]' ),
	'and it has a search box on it'
);
EstatTests::ok(
	false !== strpos( (string) $properties_page->post_content, 'Sharma Properties' ),
	'the ready-made wording uses the office\'s own name'
);
EstatTests::ok( '' !== Pages::url( 'properties' ), 'the office can be sent straight to the page' );

EstatTests::ok( ! SetupScreen::needed(), 'the office is no longer nagged once setup is done' );

/* ================================================= Running it more than once */

EstatTests::group( 'Setup: running it again never makes a mess' );

$before = count( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any' ) ) );
Pages::create( array(), true );
Pages::create( array(), true );
$after = count( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any' ) ) );

EstatTests::is( $before + 2, $after, 'the two pages not built yet are added, and only once' );

$again = count( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any' ) ) );
Pages::create( array(), true );
EstatTests::is(
	$again,
	count( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any' ) ) ),
	'and building them a fourth time adds nothing at all'
);

// Start from nothing, then pretend the office already hand-made the team page.
Settings::update( array( Pages::SETTING => array() ) );
foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any' ) ) as $existing_page ) {
	wp_delete_post( (int) $existing_page->ID, true );
}
$adopted_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_title'  => 'A page the office made themselves',
		'post_name'   => 'our-team',
		'post_status' => 'publish',
	)
);
$adopted = Pages::create( array( 'team' ), true );
EstatTests::is(
	(int) $adopted_id,
	(int) $adopted['team'],
	'a page the office already made is adopted rather than duplicated'
);
EstatTests::is(
	1,
	count( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any' ) ) ),
	'and no second copy of it was created'
);
EstatTests::is(
	'A page the office made themselves',
	(string) get_post( $adopted_id )->post_title,
	'the office\'s own wording on that page is left completely alone'
);

/* ===================================================== A blank page instead */

EstatTests::group( 'Setup: an office that wants to design it themselves' );

Settings::update( array( Pages::SETTING => array() ) );
foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any' ) ) as $page ) {
	wp_delete_post( (int) $page->ID, true );
}

$blank = Pages::create( array( 'properties' ), false );
$bare  = (string) get_post( $blank['properties'] )->post_content;

EstatTests::ok( false !== strpos( $bare, '[estat_properties]' ), 'the bare page still lists the properties' );
EstatTests::ok( false === strpos( $bare, '<!-- wp:heading' ), 'but it has no ready-made heading to undo' );
EstatTests::ok( false === strpos( $bare, 'Find your next home' ), 'and none of the ready-made wording' );

/* ============================================================= Permissions */

EstatTests::group( 'Setup: only the owner may set the office up' );

estat_login_with( 7, array( 'read', 'estat_manage_listings' ) );
EstatTests::is(
	'forbidden',
	estat_submit_setup( array( 'office_name' => 'A Hostile Takeover' ) ),
	'an agent cannot run setup'
);
EstatTests::ok(
	'A Hostile Takeover' !== (string) Settings::get( 'office_name' ),
	'and the office name they tried to set was not saved'
);

estat_login_owner();
$_POST                = array(
	'estat_action' => 'run_setup',
	'estat_nonce'  => 'not-a-real-nonce',
	'office_name'  => 'Forged Setup',
);
$_REQUEST             = $_POST;
$_GET                 = array();
$blocked_by_bad_nonce = false;
try {
	Actions::handle();
} catch ( Estat_Die_Exception $e ) {
	$blocked_by_bad_nonce = true;
} catch ( Estat_Redirect_Exception $e ) {
	$blocked_by_bad_nonce = true;
}
EstatTests::ok( $blocked_by_bad_nonce, 'a forged setup submission is refused' );
EstatTests::ok( 'Forged Setup' !== (string) Settings::get( 'office_name' ), 'and it changed nothing' );

/* ============================================================ What to do next */

EstatTests::group( 'Setup: the office is told what to do next' );

estat_reset_world();
estat_login_owner();
Pages::create( array( 'properties' ), true );

$steps = estat_capture(
	static function () {
		SetupScreen::next_steps();
	}
);

EstatTests::ok( false !== strpos( $steps, 'Add your first property' ), 'an office with nothing is told to add a property' );
EstatTests::ok( false !== strpos( $steps, 'Add the people in your office' ), 'and to add their team' );
EstatTests::ok( false !== strpos( $steps, 'estat-add-home' ), 'each step links to the screen that does the job' );

Listings::save(
	array(
		'title'         => 'The first flat this office listed',
		'offer'         => 'sale',
		'property_type' => 'apartment',
		'locality'      => 'Saket',
		'price'         => 2500000,
		'area'          => 1000,
		'area_unit'     => 'sqft',
		'status'        => 'draft',
	)
);

$steps_after = estat_capture(
	static function () {
		SetupScreen::next_steps();
	}
);

EstatTests::ok(
	false === strpos( $steps_after, 'Add your first property' ),
	'once a property exists that step disappears on its own'
);
EstatTests::ok(
	false !== strpos( $steps_after, 'Put a property on your website' ),
	'and is replaced by a nudge to publish the draft'
);

Settings::update( array( 'email' => 'office@sharma.test' ) );
$steps_email = estat_capture(
	static function () {
		SetupScreen::next_steps();
	}
);
EstatTests::ok(
	false === strpos( $steps_email, 'Tell us where to send new enquiries' ),
	'giving an email address removes that step too'
);
