<?php
/**
 * Small screens, helpful empty screens, and the conveniences that stop
 * people losing work.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Screens\Enquiries;
use EstatOS\Admin\Screens\FormsScreen;
use EstatOS\Admin\Screens\ListingsScreen;
use EstatOS\Admin\Screens\Partials;
use EstatOS\Admin\Screens\VisitsScreen;
use EstatOS\Data\Listings;

estat_reset_world();
estat_login_owner();

$css = (string) file_get_contents( ESTAT_DIR . 'assets/css/admin.css' );
$js  = (string) file_get_contents( ESTAT_DIR . 'assets/js/admin.js' );

/* ================================================ Dark mode is real */

EstatTests::group( 'Integration: no screen hardcodes a colour' );

// A rule that hardcodes a colour keeps that colour in dark mode, which is how
// a "dark" screen ends up with white boxes on it.
$offenders = array();

foreach ( explode( "\n", $css ) as $number => $line ) {
	if ( ! preg_match( '/#[0-9a-fA-F]{3,8}\b/', $line ) ) {
		continue;
	}

	// Token definitions are where literal colours belong.
	if ( preg_match( '/--estat-[a-z0-9-]+\s*:/', $line ) ) {
		continue;
	}

	/*
	 * A comment is not a rule. Naming a colour while explaining why it is
	 * there is the opposite of hiding one, and failing the build for it
	 * teaches the wrong lesson: it pushes people to stop writing the
	 * explanation rather than stop hardcoding the colour.
	 */
	if ( preg_match( '/^\s*(\/\*|\*|\/\/)/', $line ) ) {
		continue;
	}

	// White text sitting on a coloured button is correct as written.
	if ( preg_match( '/^\s*color\s*:\s*#(fff|ffffff)\s*;/', $line ) ) {
		continue;
	}

	/*
	 * A background element is artwork, not a rule. The Mayfair line-work is
	 * inlined as a data URI and carries its own gold inside the SVG, which is
	 * the whole point: the drawing is fixed, and what changes between light
	 * and dark is whether it is shown at all.
	 *
	 * The exemption is narrow on purpose - it only covers an inlined SVG, so
	 * an ordinary hardcoded colour on a background line is still caught.
	 */
	if ( false !== strpos( $line, 'data:image/svg+xml' ) ) {
		continue;
	}

	$offenders[] = ( $number + 1 ) . ': ' . trim( $line );
}

EstatTests::is( array(), $offenders, 'every rule uses a design token, so dark mode is correct everywhere' );

// Every token that is used must actually exist.
preg_match_all( '/var\(\s*(--estat-[a-z0-9-]+)\s*\)/', $css, $used );
preg_match_all( '/^\s*(--estat-[a-z0-9-]+)\s*:/m', $css, $defined );

$missing = array_values( array_unique( array_diff( $used[1], $defined[1] ) ) );
EstatTests::is( array(), $missing, 'no rule refers to a design token that was never defined' );

EstatTests::is(
	substr_count( $css, '{' ),
	substr_count( $css, '}' ),
	'the stylesheet is not broken half way through'
);

/* ============================================== Small screens */

EstatTests::group( 'Integration: the office works from a phone' );

EstatTests::ok( false !== strpos( $css, '@media ( max-width: 782px )' ), 'there is a phone layout at the same width WordPress uses' );
EstatTests::ok( false !== strpos( $css, '@media ( max-width: 480px )' ), 'and a narrower one for small phones' );

// A tap target smaller than about 40px is genuinely hard to hit.
EstatTests::ok(
	(bool) preg_match( '/max-width: 782px[^@]*min-height:\s*40px/s', $css ),
	'buttons and boxes are big enough to tap on a phone'
);

// Under 16px, phones zoom the page in when a box is tapped.
EstatTests::ok(
	(bool) preg_match( '/max-width: 782px[^@]*font-size:\s*16px/s', $css ),
	'typing into a box does not make the phone zoom in'
);

EstatTests::ok(
	(bool) preg_match( '/max-width: 782px[^@]*\.estat-filters[^}]*flex-direction:\s*column/s', $css ),
	'the search and filter bar stacks instead of squashing'
);

/* -------------------------- Tables must survive being a card */

EstatTests::group( 'Integration: tables turn into readable cards on a phone' );

// On a phone the column headings are hidden, so each cell has to carry its own
// label or the row becomes a meaningless list of values.
$unlabelled = array();

foreach ( glob( ESTAT_DIR . 'includes/Admin/Screens/*.php' ) as $file ) {
	$source = (string) file_get_contents( $file );

	if ( false === strpos( $source, 'estat-table' ) ) {
		continue;
	}

	preg_match_all( '/<t[dh](?![a-z])((?:(?!>).)*)>/s', $source, $cells );

	foreach ( $cells[1] as $cell ) {
		if ( false !== strpos( $cell, 'scope="col"' ) ) {
			continue;
		}

		if ( false === strpos( $cell, 'data-label' ) ) {
			$unlabelled[] = basename( $file ) . ': ' . trim( $cell );
		}
	}
}

EstatTests::is( array(), $unlabelled, 'every table cell is labelled, so phone users can read the row' );

EstatTests::ok(
	(bool) preg_match( '/max-width: 782px[^@]*\.estat-table tr[^}]*background:\s*var\(/s', $css ),
	'the mobile row-card uses a token, so it is not white in dark mode'
);

/* ============================================== Empty screens */

EstatTests::group( 'Integration: an empty screen explains itself' );

$_GET = array( 'page' => 'estat-listings' );
ob_start();
ListingsScreen::render();
$html = (string) ob_get_clean();

EstatTests::ok( false !== strpos( $html, 'No properties yet' ), 'the empty listings screen has a heading, not just a sentence' );
EstatTests::ok( false !== strpos( $html, 'estat-empty-steps' ), 'and shows the steps out of it' );
EstatTests::ok( false !== strpos( $html, 'is-reassuring' ), 'a new office is reassured rather than alarmed' );
EstatTests::ok( false !== strpos( $html, 'estat-add-home' ), 'and is given a button that actually helps' );

$_GET = array( 'page' => 'estat-enquiries' );
ob_start();
Enquiries::render();
$html = (string) ob_get_clean();
EstatTests::ok( false !== strpos( $html, 'No enquiries yet' ), 'the empty enquiries screen explains itself' );
EstatTests::ok( false !== strpos( $html, 'Nothing is broken' ), 'and says plainly that nothing is wrong' );

$_GET = array( 'page' => 'estat-visits' );
ob_start();
VisitsScreen::render();
$html = (string) ob_get_clean();
EstatTests::ok( false !== strpos( $html, 'No site visits booked' ), 'the empty visits screen explains where visits come from' );

$_GET = array( 'page' => 'estat-forms' );
ob_start();
FormsScreen::render();
$html = (string) ob_get_clean();
EstatTests::ok( false !== strpos( $html, 'No forms yet' ), 'the empty forms screen explains what a form is for' );

/* ------------- A search that found nothing is a different problem */

EstatTests::group( 'Integration: "nothing found" is not the same as "nothing here"' );

Listings::save(
	array(
		'title'         => 'Saket flat with a park view',
		'offer'         => 'sale',
		'property_type' => 'apartment',
		'locality'      => 'Saket',
		'price'         => 5000000,
		'area'          => 1000,
		'area_unit'     => 'sqft',
		'status'        => 'publish',
	)
);

$_GET = array( 'page' => 'estat-listings', 's' => 'zzzznothingatall' );
ob_start();
ListingsScreen::render();
$html = (string) ob_get_clean();

EstatTests::ok( false !== strpos( $html, 'Nothing matched' ), 'a fruitless search says so' );
EstatTests::ok( false !== strpos( $html, 'Clear the filters' ), 'and offers a way back to everything' );
EstatTests::ok( false === strpos( $html, 'No properties yet' ), 'it does not wrongly claim the office is empty' );

// The older call style must keep working — several screens still use it.
ob_start();
Partials::empty_state( 'A plain sentence', 'https://example.com/go', 'Go' );
$plain = (string) ob_get_clean();
EstatTests::ok( false !== strpos( $plain, 'A plain sentence' ), 'the simple way of calling empty_state still works' );
EstatTests::ok( false !== strpos( $plain, 'example.com/go' ), 'and still renders its button' );

// Hostile text must not escape, whichever style is used.
ob_start();
Partials::empty_state(
	array(
		'title'   => '<script>alert(1)</script>',
		'message' => '<img src=x onerror=alert(1)>',
		'steps'   => array( '<b>bold</b>' ),
	)
);
$hostile = (string) ob_get_clean();
EstatTests::ok( false === strpos( $hostile, '<script>' ), 'a hostile title cannot run code' );
EstatTests::ok( false === strpos( $hostile, '<img src=x' ), 'a hostile message cannot run code' );
EstatTests::ok( false === strpos( $hostile, '<b>bold</b>' ), 'hostile step text cannot run code' );

/* ============================================ Conveniences */

EstatTests::group( 'Integration: small conveniences' );

EstatTests::ok( false !== strpos( $js, 'estat-is-busy' ), 'a pressed button shows that it is working' );
EstatTests::ok( false !== strpos( $css, 'estat-is-busy' ), 'and there is a spinner style for it' );
EstatTests::ok( false !== strpos( $js, 'beforeunload' ), 'leaving a half-filled form warns first' );
EstatTests::ok( false !== strpos( $js, 'initShortcuts' ), 'there are keyboard shortcuts' );
EstatTests::ok( false !== strpos( $js, 'estat-scroll-x' ), 'a wide table can be swiped instead of stretching the page' );

// Pressing Save twice must not create the record twice.
EstatTests::ok(
	false !== strpos( $js, 'if ( submitted ) {' ),
	'a second press of Save is ignored, so nothing is created twice'
);

// Disabling a submit button drops its name and value, which would silently
// change what the form does.
EstatTests::ok(
	false === strpos( $js, 'button.disabled = true' ),
	'the busy state does not disable the button, which would lose which button was pressed'
);

EstatTests::ok(
	false !== strpos( $css, 'prefers-reduced-motion' ),
	'the spinner stops for anyone who has asked for less movement'
);

/* --------------------------------- Clearer messages */

EstatTests::group( 'Integration: messages say what actually happened' );

$_GET = array( 'estat_notice' => 'published' );
ob_start();
Partials::notices();
$notice = (string) ob_get_clean();
EstatTests::ok( false !== strpos( $notice, 'live on your website' ), 'publishing says the property is now public' );

$_GET = array( 'estat_notice' => 'draft' );
ob_start();
Partials::notices();
$notice = (string) ob_get_clean();
EstatTests::ok( false !== strpos( $notice, 'Only your office can see it' ), 'saving a draft says who can see it' );

$_GET = array( 'estat_notice' => 'imported' );
ob_start();
Partials::notices();
$notice = (string) ob_get_clean();
EstatTests::ok( false !== strpos( $notice, 'saved as drafts' ), 'an import says the new properties are not live yet' );

$_GET = array( 'estat_notice' => 'erased' );
ob_start();
Partials::notices();
$notice = (string) ob_get_clean();
EstatTests::ok( false !== strpos( $notice, 'for good' ), 'erasing says plainly that it cannot be undone' );

// An unknown notice must stay silent rather than inventing a message.
$_GET = array( 'estat_notice' => 'not_a_real_notice' );
ob_start();
Partials::notices();
EstatTests::is( '', trim( (string) ob_get_clean() ), 'an unknown message code shows nothing at all' );

$_GET = array();
