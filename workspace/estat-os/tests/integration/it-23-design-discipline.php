<?php
/**
 * Design discipline.
 *
 * The office said the interface "felt AI-made". It did, and the reason was
 * measurable rather than mysterious: twenty-one different font sizes with no
 * scale behind them, sixty emoji standing in for an icon set, pure white and
 * pure black flattening every surface, and a handful of interaction details
 * that a person building this by hand would have got right without thinking.
 *
 * None of that is taste. All of it is countable, so this file counts it.
 *
 * The rules come from the Vercel Web Interface Guidelines and from the
 * published "AI tells" work on what makes generated interfaces recognisable.
 * Only the rules that genuinely apply to a dense back-office tool are here;
 * the ones aimed at marketing pages are deliberately left out.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Support\Icon;

estat_reset_world();
estat_login_owner();

$admin_css  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/admin.css' );
$forms_css  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/forms.css' );
$public_css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/public.css' );

EstatTests::group( 'There is a type scale, and everything sits on it' );

// A scale is a short, deliberate list. Twenty-one sizes is not a scale, it is
// whatever felt right at the time, and that is what reads as machine-made.
preg_match_all( '/--estat-text[a-z0-9-]*:\s*(\d+)px/', $admin_css, $steps );

$scale = array_map( 'intval', $steps[1] );

EstatTests::ok( count( $scale ) > 0, 'a type scale is defined' );
EstatTests::ok(
	count( $scale ) <= 8,
	'the scale has ' . count( $scale ) . ' steps, which is few enough to be a real scale'
);

// Every step must be a whole pixel. 12.5px and 14.5px are the fingerprints of
// nudging a number until it looked right.
foreach ( $steps[0] as $declaration ) {
	EstatTests::ok(
		false === strpos( $declaration, '.' ),
		'the scale step "' . trim( $declaration ) . '" is a whole pixel'
	);
}

// And the steps must actually differ from each other.
EstatTests::is(
	count( $scale ),
	count( array_unique( $scale ) ),
	'no two scale steps are the same size'
);

/**
 * Every raw font-size in a stylesheet, ignoring the ones that are allowed.
 *
 * 16px on a mobile input is a browser threshold, not a design decision: below
 * it, iOS Safari zooms the page. So it is exempt, and only it.
 *
 * @param string $css Stylesheet.
 * @return array<string>
 */
function estat_it23_raw_sizes( string $css ): array {
	preg_match_all( '/font-size:\s*([0-9.]+)(px|rem|em)/', $css, $m, PREG_SET_ORDER );

	$raw = array();
	foreach ( $m as $hit ) {
		if ( '16' === $hit[1] && 'px' === $hit[2] ) {
			continue;
		}
		$raw[] = $hit[1] . $hit[2];
	}

	return $raw;
}

/*
 * This check used to run against admin.css alone, even though public.css was
 * already loaded two lines up for the colour checks. Four raw sizes sat in the
 * visitor-facing stylesheet the whole time, and the test reported the rule as
 * enforced. A hole in a rule is worse than no rule, because it is trusted.
 */
foreach (
	array(
		'office'  => $admin_css,
		'public'  => $public_css,
		'form'    => $forms_css,
	) as $which => $sheet
) {
	EstatTests::is(
		array(),
		array_values( array_unique( estat_it23_raw_sizes( $sheet ) ) ),
		'no rule in the ' . $which . ' stylesheet sets a font size outside the scale'
	);
}

// The 16px exemption must still be there, or phones will zoom on every tap.
EstatTests::ok(
	false !== strpos( $admin_css, 'font-size: 16px' ),
	'mobile inputs are still exactly 16px, so a phone does not zoom when one is tapped'
);
EstatTests::ok(
	false !== strpos( $forms_css, 'font-size: 16px' ),
	'the same is true of the public form'
);

EstatTests::group( 'Icons are a set, not whatever emoji was to hand' );

$paths = Icon::paths();

EstatTests::ok( count( $paths ) >= 40, 'the icon set covers the product: ' . count( $paths ) . ' icons' );

// One grid, one stroke weight. This is what makes a set look like a set.
$sample = Icon::render( 'home' );

/*
 * The set moved from hand-drawn 24-unit strokes to the office's own Mayfair
 * package: Phosphor Regular, filled, on a 256 unit grid. The rule is unchanged
 * - one family, one grid, colour taken from the text - only the shape of the
 * evidence is different.
 */
/*
 * Pin the number, not the constant. Comparing the rendered SVG against
 * Icon::GRID is circular: change the constant and both sides move together,
 * so an icon set silently drawn on the wrong grid would still pass.
 */
EstatTests::is( 256, Icon::GRID, 'the grid is the 256 units Phosphor draws on' );
EstatTests::ok(
	false !== strpos( $sample, 'viewBox="0 0 256 256"' ),
	'a rendered icon really carries that grid'
);
EstatTests::ok( false !== strpos( $sample, 'fill="currentColor"' ), 'icons take their colour from the text, so dark mode needs no second definition' );
EstatTests::ok( false !== strpos( $sample, 'aria-hidden="true"' ), 'icons are decorative and hidden from screen readers' );

// Mixing grids is what made the old set look uneven, so every icon is checked,
// not just the sample.
$off_grid = array();

foreach ( array_keys( Icon::paths() ) as $icon_name ) {
	$svg = Icon::render( $icon_name );

	if ( false === strpos( $svg, 'viewBox="0 0 256 256"' ) ) {
		$off_grid[] = $icon_name;
	}
}

EstatTests::is( array(), $off_grid, 'every icon is on the same grid, not just the one sampled' );

// A hard-coded colour would survive into dark mode and glare.
$coloured = array();

foreach ( Icon::paths() as $icon_name => $body ) {
	if ( preg_match( '/(?:fill|stroke)="(?!currentColor|none)[^"]+"/', $body ) ) {
		$coloured[] = $icon_name;
	}
}

EstatTests::is( array(), $coloured, 'no icon carries a colour of its own' );

// An unknown name must fail quietly, not print a broken glyph.
EstatTests::is( '', Icon::render( 'no-such-icon' ), 'an unknown icon name renders nothing at all' );
EstatTests::ok( ! Icon::has( 'no-such-icon' ), 'has() agrees' );

// No emoji anywhere in the product code. They are drawn by the operating
// system, so the same screen looks different on every machine.
$emoji = '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]/u';
$dirty = array();

foreach ( glob( dirname( __DIR__, 2 ) . '/includes/*/*.php' ) as $file ) {
	if ( preg_match( $emoji, (string) file_get_contents( $file ) ) ) {
		$dirty[] = basename( dirname( $file ) ) . '/' . basename( $file );
	}
}
foreach ( glob( dirname( __DIR__, 2 ) . '/includes/*/*/*.php' ) as $file ) {
	if ( preg_match( $emoji, (string) file_get_contents( $file ) ) ) {
		$dirty[] = basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

EstatTests::is( array(), $dirty, 'no PHP file uses an emoji where an icon belongs' );

// Every icon name referenced anywhere must exist, or a screen shows a gap.
$referenced = array();

foreach ( array_merge( glob( dirname( __DIR__, 2 ) . '/includes/*/*.php' ), glob( dirname( __DIR__, 2 ) . '/includes/*/*/*.php' ) ) as $file ) {
	$src = (string) file_get_contents( $file );

	if ( false !== strpos( $file, 'Elementor' ) ) {
		continue; // Elementor supplies its own icon names.
	}

	preg_match_all( "/'icon'\s*=>\s*'([a-z-]+)'/", $src, $found );

	foreach ( $found[1] as $name ) {
		$referenced[ $name ] = basename( $file );
	}
}

EstatTests::ok( count( $referenced ) > 20, 'icons are used widely: ' . count( $referenced ) . ' names in use' );

foreach ( $referenced as $name => $where ) {
	EstatTests::ok(
		Icon::has( $name ),
		'the icon "' . $name . '" used by ' . $where . ' exists in the set'
	);
}

EstatTests::group( 'Surfaces have depth' );

// Pure white on an off-white page, or pure black text, flattens everything.
// Real interfaces sit slightly off the extremes.
foreach ( array( 'admin' => $admin_css, 'public' => $public_css ) as $which => $css ) {
	EstatTests::ok(
		0 === preg_match( '/#(?:fff|ffffff|000|000000)\b/i', $css ),
		'the ' . $which . ' stylesheet uses no pure black or pure white'
	);
}

// Text on the accent colour is its own token, so dark mode can flip it.
EstatTests::ok(
	false !== strpos( $admin_css, '--estat-on-accent' ),
	'text sitting on the accent colour has its own token'
);
EstatTests::ok(
	(bool) preg_match( '/\.estat-screen\.estat-theme-dark\s*\{[^}]*--estat-on-accent/s', $admin_css ),
	'that token is restated for dark mode'
);

EstatTests::group( 'The interaction details a person would not skip' );

EstatTests::ok(
	false !== strpos( $admin_css, 'touch-action: manipulation' ),
	'tappable controls set touch-action, so a phone does not wait 300ms after every tap'
);
EstatTests::ok(
	false !== strpos( $admin_css, '-webkit-tap-highlight-color' ),
	'the tap highlight is our colour rather than the browser default grey'
);
EstatTests::ok(
	false !== strpos( $admin_css, 'font-variant-numeric: tabular-nums' ),
	'numbers that get compared are tabular, so their digits line up'
);
EstatTests::ok(
	false !== strpos( $admin_css, 'scroll-margin-top' ),
	'a heading linked to from elsewhere does not hide under the sticky bar'
);
EstatTests::ok(
	(bool) preg_match( '/\[draggable="true"\][^{]*\{[^}]*user-select:\s*none/s', $admin_css ),
	'dragging a card does not also select the text under it'
);

// transition: all animates layout properties too, which causes jank.
foreach ( array( 'admin' => $admin_css, 'forms' => $forms_css, 'public' => $public_css ) as $which => $css ) {
	EstatTests::ok(
		false === strpos( $css, 'transition: all' ),
		'the ' . $which . ' stylesheet never uses transition: all'
	);
}

// Buttons show a focus ring on keyboard focus, not on every mouse click.
EstatTests::ok(
	substr_count( $admin_css, 'focus-visible' ) >= 10,
	'focus rings use :focus-visible in ' . substr_count( $admin_css, 'focus-visible' ) . ' places'
);

$bare_focus = array();
preg_match_all( '/^[^\n{]*:focus[^-a-z][^\n]*$/m', $admin_css, $focus_lines );

foreach ( $focus_lines[0] as $line ) {
	if ( false !== strpos( $line, 'focus-within' ) ) {
		continue;
	}
	// A form field genuinely should ring when clicked into.
	if ( preg_match( '/\b(input|select|textarea)\b/', $line ) ) {
		continue;
	}
	$bare_focus[] = trim( $line );
}

EstatTests::is( array(), $bare_focus, 'nothing but a form field uses a bare :focus' );

// Motion has to be switchable off, wholesale.
EstatTests::ok(
	(bool) preg_match( '/@media \(prefers-reduced-motion: reduce\)\s*\{\s*\.estat-screen \*/s', $admin_css ),
	'a reader who asks for less motion gets it everywhere, not rule by rule'
);

EstatTests::group( 'An icon never carries meaning on its own' );

// An icon-only control has to say what it is for. Colour and shape alone are
// not readable, and not everybody can see them.
$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/form-builder.js' );

$icon_buttons = preg_match_all( "/'estat-icon-button/", $js );
$labelled     = preg_match_all( "/setAttribute\(\s*'aria-label'/", $js );

EstatTests::ok( $icon_buttons > 0, 'icon-only buttons exist' );
EstatTests::ok(
	$labelled >= $icon_buttons,
	'every icon-only button is named for a screen reader'
);

// The rendered icon must never be the only thing in a control.
$screen_php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Admin/Screens/Partials.php' );

EstatTests::ok(
	false !== strpos( $screen_php, 'Icon::render' ),
	'screens draw icons through the set rather than pasting characters'
);
EstatTests::ok(
	false === strpos( $screen_php, 'estat-highlight-icon" aria-hidden="true"><?php echo esc_html' ),
	'the highlight icon is an SVG, not an escaped character'
);
