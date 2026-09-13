<?php
/**
 * Every screen should explain itself, in words a non-technical person uses.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Screens\Partials;

estat_reset_world();
estat_login_owner();

/**
 * Capture the help drawer for one screen.
 *
 * @param string $page Page slug.
 * @return string
 */
function estat_help_for( string $page ): string {
	ob_start();
	try {
		Partials::header( $page );
	} catch ( \Throwable $e ) {
		$unused = $e;
	}
	$html = (string) ob_get_contents();
	ob_end_clean();

	$start = strpos( $html, '<div class="estat-help"' );
	if ( false === $start ) {
		return '';
	}
	return substr( $html, $start );
}

/* ========================================== Every screen explains itself */

EstatTests::group( 'Help: no screen is left without an explanation' );

$screens = array(
	'estat-setup',
	'estat-today',
	'estat-add-home',
	'estat-listings',
	'estat-enquiries',
	'estat-visits',
	'estat-projects',
	'estat-team',
	'estat-forms',
	'estat-gallery',
	'estat-insights',
	'estat-spreadsheet',
	'estat-reports',
	'estat-settings',
);

$fallback = 'Every field on this screen has a short explanation below it.';
$generic  = array();
$thin     = array();

foreach ( $screens as $page ) {
	$help = estat_help_for( $page );

	if ( '' === $help || false !== strpos( $help, $fallback ) ) {
		$generic[] = $page;
		continue;
	}
	// One line is not help. A screen should say what it is for, what to do,
	// and at least one thing that catches people out.
	if ( substr_count( $help, '<li>' ) < 2 ) {
		$thin[] = $page;
	}
}

EstatTests::is( array(), $generic, 'all 14 screens have help written specifically for them' );
EstatTests::is( array(), $thin, 'and none of them gets away with a single line' );

/* ================================================= The help is in plain words */

EstatTests::group( 'Help: written for an office, not for a developer' );

// Words the office would have to look up. The product deliberately says
// "Listings", "Property Information", "Website Address" and so on instead.
$jargon = array(
	'custom post type',
	'taxonomy',
	'meta field',
	'meta box',
	'REST endpoint',
	'shortcode attribute',
	'slug',
	'hook',
	'callback',
	'nonce',
	'CPT',
);

$offenders = array();
foreach ( $screens as $page ) {
	$help = strtolower( estat_help_for( $page ) );
	foreach ( $jargon as $word ) {
		if ( false !== strpos( $help, strtolower( $word ) ) ) {
			$offenders[] = $page . ': ' . $word;
		}
	}
}
EstatTests::is( array(), $offenders, 'no screen explains itself using technical jargon' );

/* ============================================ It warns about the risky things */

EstatTests::group( 'Help: it warns before something cannot be undone' );

$listings_help = estat_help_for( 'estat-listings' );
EstatTests::ok(
	false !== stripos( $listings_help, 'bin' ),
	'the listings screen explains that removing puts things in a bin'
);
EstatTests::ok(
	false !== stripos( $listings_help, 'put it back' ),
	'and that a removed property can be recovered'
);

$gallery_help = estat_help_for( 'estat-gallery' );
EstatTests::ok(
	false !== stripos( $gallery_help, 'for good' ),
	'the gallery warns that deleting a file is permanent'
);

$settings_help = estat_help_for( 'estat-settings' );
EstatTests::ok(
	false !== stripos( $settings_help, 'deactivate' ),
	'settings reassures the office that deactivating keeps their data'
);
EstatTests::ok(
	false !== stripos( $settings_help, 'uninstalling keeps everything' ),
	'and that uninstalling keeps it too unless they say otherwise'
);

$spreadsheet_help = estat_help_for( 'estat-spreadsheet' );
EstatTests::ok(
	false !== stripos( $spreadsheet_help, 'draft' ),
	'the import screen promises nothing goes live on its own'
);

$enquiries_help = estat_help_for( 'estat-enquiries' );
EstatTests::ok(
	false !== stripos( $enquiries_help, 'erase' ),
	'the enquiries screen explains how to erase somebody\'s details on request'
);

/* ==================================================== Support details are safe */

EstatTests::group( 'Help: the support text gives nothing away' );

$diagnostics = Partials::diagnostics();
EstatTests::ok( '' !== $diagnostics, 'there is something to copy for support' );

foreach ( array( 'password', 'secret', 'api_key', 'webhook_secret', 'inbound_secret' ) as $sensitive ) {
	EstatTests::ok(
		false === stripos( $diagnostics, $sensitive ),
		'the support text never mentions ' . $sensitive
	);
}
