<?php
/**
 * The Mayfair asset package.
 *
 * The icons and background line-work come from the office's own package
 * (github.com/devkaushall/dev_vault). Bringing in someone else's artwork is
 * the easy part; keeping it consistent afterwards is not, so this pins the
 * things that quietly drift:
 *
 * - a second icon family creeping in beside the first
 * - an icon carrying its own colour, which then glares in dark mode
 * - background line-work escaping from behind a container onto content
 * - a file being deleted while CSS still asks for it, or shipped while
 *   nothing does
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Support\Icon;

estat_reset_world();
estat_login_owner();

$root      = dirname( __DIR__, 2 );
$admin_css = (string) file_get_contents( $root . '/assets/css/admin.css' );
$public_css = (string) file_get_contents( $root . '/assets/css/public.css' );

EstatTests::group( 'The icon set is one family' );

$icons = Icon::paths();

EstatTests::ok( count( $icons ) >= 90, 'the set covers the product: ' . count( $icons ) . ' icons' );

/*
 * Phosphor draws on 256 units. The number is written out rather than compared
 * against Icon::GRID, because comparing the code to itself proves nothing: if
 * the constant were changed, both sides would move together and a set drawn on
 * the wrong grid would pass.
 */
EstatTests::is( 256, Icon::GRID, 'the grid is the 256 units Phosphor draws on' );

$wrong_grid = array();
$coloured   = array();
$sized      = array();

foreach ( array_keys( $icons ) as $name ) {
	$svg = Icon::render( $name );

	if ( false === strpos( $svg, 'viewBox="0 0 256 256"' ) ) {
		$wrong_grid[] = $name;
	}

	// A colour baked into the artwork survives into dark mode and glares.
	if ( preg_match( '/(?:fill|stroke)="(?!currentColor|none)[^"]*"/', $icons[ $name ] ) ) {
		$coloured[] = $name;
	}

	// A fixed size inside the path defeats the size argument.
	if ( preg_match( '/\swidth="\d|\sheight="\d/', $icons[ $name ] ) ) {
		$sized[] = $name;
	}
}

/*
 * The wrapper always writes viewBox="0 0 256 256", so checking the rendered
 * SVG cannot tell whether the artwork inside was actually drawn for that grid.
 * An icon copied in from a 24-unit family renders at a tenth of the size and
 * every assertion above still passes.
 *
 * So look at the coordinates. A 256-unit drawing reaches well past 24 in at
 * least one number; a 24-unit one never does.
 */
$too_small = array();

foreach ( $icons as $name => $body ) {
	preg_match_all( '/-?\d+(?:\.\d+)?/', $body, $numbers );

	$largest = 0.0;

	foreach ( $numbers[0] as $number ) {
		$largest = max( $largest, abs( (float) $number ) );
	}

	if ( $largest < 32 ) {
		$too_small[] = $name . ' (largest coordinate ' . $largest . ')';
	}
}

EstatTests::is(
	array(),
	$too_small,
	'every icon is drawn at 256 units, not scaled down from another family'
);

EstatTests::is( array(), $wrong_grid, 'every icon is on the same grid' );
EstatTests::is( array(), $coloured, 'no icon carries a colour of its own' );
EstatTests::is( array(), $sized, 'no icon carries a size of its own' );

// The rendered wrapper is what makes the size argument work at all.
$rendered = Icon::render( 'home', 32 );

EstatTests::ok( false !== strpos( $rendered, 'width="32" height="32"' ), 'the size argument is honoured' );
EstatTests::ok( false !== strpos( $rendered, 'fill="currentColor"' ), 'the icon takes its colour from the text' );
EstatTests::ok( false !== strpos( $rendered, 'aria-hidden="true"' ), 'the icon is hidden from screen readers' );

// A silly size must be clamped rather than rendered.
EstatTests::ok( false !== strpos( Icon::render( 'home', 9999 ), 'width="64"' ), 'an absurd size is clamped' );
EstatTests::ok( false !== strpos( Icon::render( 'home', 1 ), 'width="12"' ), 'a tiny size is clamped too' );

EstatTests::is( '', Icon::render( 'no-such-icon' ), 'an unknown name renders nothing' );

EstatTests::group( 'Every icon the product asks for exists' );

$referenced = array();

foreach ( array_merge( glob( $root . '/includes/*/*.php' ), glob( $root . '/includes/*/*/*.php' ) ) as $file ) {
	if ( false !== strpos( $file, 'Elementor' ) ) {
		continue; // Elementor supplies its own icon names.
	}

	preg_match_all( "/'icon'\s*=>\s*'([a-z-]+)'/", (string) file_get_contents( $file ), $found );

	foreach ( $found[1] as $name ) {
		$referenced[ $name ] = basename( $file );
	}
}

EstatTests::ok( count( $referenced ) > 30, count( $referenced ) . ' icon names are in use' );

foreach ( $referenced as $name => $where ) {
	EstatTests::ok( Icon::has( $name ), 'the icon "' . $name . '" used by ' . $where . ' exists' );
}

EstatTests::group( 'Background line-work stays behind the content' );

// The four small tileable pieces are inlined; the rest stay as files.
foreach ( array( 'estat-bg-grid', 'estat-bg-dots', 'estat-bg-hatch', 'estat-bg-plots' ) as $token ) {
	EstatTests::ok(
		false !== strpos( $admin_css, '--' . $token . ':' ),
		'the ' . $token . ' texture is defined as a token'
	);
}

EstatTests::ok(
	substr_count( $admin_css, 'data:image/svg+xml' ) >= 4,
	'the small textures are inlined rather than fetched'
);

/*
 * The whole point of a background element is that it sits behind. A card
 * carries words, so line-work must be switched off there or the text becomes
 * harder to read - which is worse than having no texture at all.
 */
foreach ( array( 'estat-card', 'estat-table', 'estat-studio-panel' ) as $surface ) {
	EstatTests::ok(
		false !== strpos( $admin_css, '.' . $surface ),
		'the ' . $surface . ' surface is styled'
	);
}

EstatTests::ok(
	(bool) preg_match( '/\.estat-screen \.estat-card,[^{]*\{\s*background-image:\s*none/s', $admin_css ),
	'cards explicitly clear the texture, so it never sits under body text'
);

/*
 * Dark mode: gold on ink reads far stronger than gold on ivory, so the same
 * artwork has to be dropped rather than reused.
 *
 * Each surface is named. A loose pattern matched whichever dark rule it found
 * first, so removing the page background entirely still passed.
 */
foreach (
	array(
		'the page itself'    => '\.estat-theme-dark\.estat-screen #wpbody-content',
		'the greeting card'  => '\.estat-theme-dark \.estat-greeting-card',
		'the empty state'    => '\.estat-theme-dark \.estat-empty',
	) as $surface => $selector
) {
	EstatTests::ok(
		(bool) preg_match( '/' . $selector . '[^{]*\{[^}]*background-image:\s*none/s', $admin_css ),
		'dark mode drops the line-work on ' . $surface
	);
}

// Printing a listing should not spend ink on decoration.
EstatTests::ok(
	(bool) preg_match( '/@media print \{[^}]*background-image:\s*none/s', $admin_css ),
	'printing drops the background artwork'
);

EstatTests::group( 'Shipped artwork is referenced, and referenced artwork ships' );

$background_dir = $root . '/assets/backgrounds';

EstatTests::ok( is_dir( $background_dir ), 'the backgrounds folder exists' );

$files = array_values(
	array_filter(
		scandir( $background_dir ),
		static function ( string $name ): bool {
			return in_array( pathinfo( $name, PATHINFO_EXTENSION ), array( 'svg', 'png', 'webp' ), true );
		}
	)
);

EstatTests::ok( count( $files ) > 0, count( $files ) . ' background files ship' );

// Anything the stylesheets ask for by name must actually be there.
preg_match_all( '#backgrounds/([a-z0-9-]+\.(?:svg|png|webp))#', $admin_css . $public_css, $wanted );

$missing = array();

foreach ( array_unique( $wanted[1] ) as $needed ) {
	if ( ! file_exists( $background_dir . '/' . $needed ) ) {
		$missing[] = $needed;
	}
}

EstatTests::is( array(), $missing, 'every background file the CSS asks for is present' );

// The inlined four must NOT also ship as files: two copies of the same artwork
// is how they drift apart.
foreach ( array( 'bg-grid-plan.svg', 'bg-dots.svg', 'bg-hatch.svg', 'bg-plots.svg' ) as $inlined ) {
	EstatTests::ok(
		! file_exists( $background_dir . '/' . $inlined ),
		$inlined . ' is inlined in CSS and does not also ship as a file'
	);
}

// A folder of artwork nobody can interpret is not much use.
EstatTests::ok( file_exists( $background_dir . '/README.md' ), 'the artwork is documented for whoever uses it next' );

EstatTests::group( 'The artwork cannot carry anything executable' );

/*
 * An SVG is markup, and markup can hold a script. These are shipped to a
 * visitor's browser, so every one is checked - not sampled.
 */
$dangerous = array();

foreach ( glob( $background_dir . '/*.svg' ) as $svg ) {
	$body = (string) file_get_contents( $svg );

	if ( preg_match( '/<script|javascript:|on[a-z]+\s*=|<foreignObject|xlink:href\s*=\s*"http/i', $body ) ) {
		$dangerous[] = basename( $svg );
	}
}

EstatTests::is( array(), $dangerous, 'no shipped SVG contains a script, an event handler or an external reference' );

// The same for the inlined ones, which reach the browser inside the CSS.
EstatTests::ok(
	false === stripos( $admin_css, '<script' ),
	'no inlined SVG smuggles a script into the stylesheet'
);
EstatTests::ok(
	0 === preg_match( '/data:image\/svg\+xml[^"]*on[a-z]+%3D/i', $admin_css ),
	'no inlined SVG carries an encoded event handler'
);
