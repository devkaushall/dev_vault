<?php
/**
 * Does the builder actually FIT in the space it is given?
 *
 * This file exists because of a real failure. The studio was shipped with the
 * row controls laid out on a single flex line needing about 930px, inside a
 * canvas column that is only ever about 520px wide. Every other test passed:
 * they check that a class exists, never that the thing can be seen. The office
 * got clipped labels, a "Remove" button reading "Re", and a sideways
 * scrollbar.
 *
 * So this file does arithmetic instead of string matching. It works out the
 * real width of each column from the grid, then checks that the widest strip
 * of controls in that column can fit.
 *
 * The numbers are deliberate over-estimates of rendered text. If a strip only
 * just fits here, it needs to be made smaller.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

estat_reset_world();
estat_login_owner();

$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/admin.css' );
$js  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/form-builder.js' );

/**
 * The narrowest desktop we promise to support, minus the WordPress menu.
 *
 * 1280px is a very common laptop. The admin menu is 160px when open.
 */
const ESTAT_IT22_PAGE = 1280 - 160;

/**
 * Read the studio's three column percentages out of the stylesheet.
 *
 * @param string $css Stylesheet.
 * @return array{panel:float,canvas:float,preview:float}|null
 */
function estat_it22_columns( string $css ): ?array {
	if ( ! preg_match( '/grid-template-columns:\s*minmax\(0,\s*(\d+)fr\)\s+minmax\(0,\s*(\d+)fr\)\s+minmax\(0,\s*(\d+)fr\)/', $css, $m ) ) {
		return null;
	}

	$total = (float) $m[1] + (float) $m[2] + (float) $m[3];

	if ( $total <= 0.0 ) {
		return null;
	}

	return array(
		'panel'   => (float) $m[1] / $total,
		'canvas'  => (float) $m[2] / $total,
		'preview' => (float) $m[3] / $total,
	);
}

$columns = estat_it22_columns( $css );

EstatTests::group( 'The studio columns are declared and add up' );

EstatTests::ok( null !== $columns, 'the three column widths can be read from the stylesheet' );

if ( null === $columns ) {
	return;
}

$total = $columns['panel'] + $columns['canvas'] + $columns['preview'];

EstatTests::ok(
	$total <= 1.0,
	'the three columns add up to ' . round( $total * 100 ) . '%, which is not more than 100%'
);

// Card padding 2x16, and inside the canvas a row also pads 2x12.
$panel_inner   = ( ESTAT_IT22_PAGE * $columns['panel'] ) - 32;
$canvas_inner  = ( ESTAT_IT22_PAGE * $columns['canvas'] ) - 32 - 24;
$preview_inner = ( ESTAT_IT22_PAGE * $columns['preview'] ) - 32 - 32;

/**
 * Read a pixel value straight out of the stylesheet.
 *
 * The point is that this test measures what the CSS actually says, rather
 * than a number someone typed here that can drift out of date.
 *
 * @param string $css      Stylesheet.
 * @param string $selector Selector to look inside.
 * @param string $property Property to read.
 * @param int    $fallback Value if it cannot be found.
 * @return int
 */
function estat_it22_css_px( string $css, string $selector, string $property, int $fallback ): int {
	$pattern = '/' . preg_quote( $selector, '/' ) . '\s*\{[^}]*' . preg_quote( $property, '/' ) . ':\s*(\d+)px/s';

	return preg_match( $pattern, $css, $m ) ? (int) $m[1] : $fallback;
}

/**
 * Check that a strip of controls fits the room it has.
 *
 * @param string                     $name  What this strip is.
 * @param array<array{0:string,1:int}> $items Label and generous pixel width.
 * @param float                      $have  Available width.
 * @param int                        $gap   Gap between items.
 * @return void
 */
function estat_it22_fits( string $name, array $items, float $have, int $gap = 16 ): void {
	$need = 0;
	foreach ( $items as $item ) {
		$need += (int) $item[1];
	}
	$need += $gap * max( 0, count( $items ) - 1 );

	$widest = '';
	$max    = 0;
	foreach ( $items as $item ) {
		if ( (int) $item[1] > $max ) {
			$max    = (int) $item[1];
			$widest = (string) $item[0];
		}
	}

	EstatTests::ok(
		$need <= $have,
		sprintf(
			'%s fits: needs %dpx, has %dpx%s',
			$name,
			$need,
			(int) $have,
			$need > $have ? ' (over by ' . ( $need - (int) $have ) . 'px; widest part is "' . $widest . '")' : ''
		)
	);
}

EstatTests::group( 'The studio is given the room it needs' );

// The studio was capped at the normal reading width, which left a wide
// monitor half empty while the three columns were squeezed into 655px.
EstatTests::ok(
	(bool) preg_match( '/\.estat-wrap-wide[^{]*\{[^}]*max-width:\s*none/s', $css ),
	'the studio is allowed the full window instead of the 1240px reading width'
);

$admin_src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Admin/Admin.php' );

EstatTests::ok(
	false !== strpos( $admin_src, 'estat-wrap-wide' ),
	'the builder screen really asks for the wide wrapper'
);
EstatTests::ok(
	false !== strpos( $admin_src, "'estat-forms' === \$page && ! empty( \$_GET['edit'] )" ),
	'only the form builder gets the wide wrapper, so other screens keep their reading width'
);

// Three columns must survive a normal laptop. The breakpoint was 1400px,
// which dropped a 1408px screen to two columns and wasted half the page.
if ( preg_match( '/@media \(max-width:\s*(\d+)px\)\s*\{\s*\.estat-studio\s*\{\s*grid-template-columns:\s*minmax\(0,\s*1fr\)\s+minmax\(0,\s*1fr\)/s', $css, $bp ) ) {
	$breakpoint = (int) $bp[1];

	EstatTests::ok(
		$breakpoint <= 1120,
		'the studio keeps three columns down to ' . $breakpoint . 'px, so a normal laptop is not dropped to two'
	);

	// And at that breakpoint the canvas must still be usable, or reflowing
	// there would be hiding a fit problem rather than solving one.
	$canvas_at_bp = ( $breakpoint * 0.43 ) - 56;

	EstatTests::ok(
		$canvas_at_bp >= 400,
		sprintf( 'at the breakpoint the canvas is still %dpx, which is wide enough to work in', (int) $canvas_at_bp )
	);
} else {
	EstatTests::ok( false, 'the two column breakpoint can be found' );
}

EstatTests::group( 'Nothing in the studio\'s ancestry squeezes it' );

/*
 * Three times now the studio has been narrow because of a width cap on
 * something ABOVE it: the 1240px reading width, then the 1400px breakpoint,
 * then a 780px cap on the <form> the studio happens to live inside. Each time
 * the studio's own widths were correct and each time I fixed only the layer I
 * happened to look at.
 *
 * So this walks the whole chain instead of checking one rule.
 */
$ancestors = array(
	'estat-screen',
	'wpbody-content',
	'wrap',
	'estat-admin',
	'estat-form-admin',
	'estat-builder-form',
	'estat-studio',
);

// Look only at the base cascade; device previews legitimately cap themselves.
$base = preg_replace( '/@media[^{]*\{(?:[^{}]*\{[^}]*\})*[^{}]*\}/', '', $css );

preg_match_all( '/([^{}]+)\{([^}]*)\}/', (string) $base, $rules, PREG_SET_ORDER );

foreach ( $ancestors as $class ) {
	$caps = array();

	foreach ( $rules as $rule ) {
		foreach ( explode( ',', $rule[1] ) as $selector ) {
			$selector = trim( $selector );

			// Only rules whose KEY selector is this class.
			if ( ! preg_match( '/\.' . preg_quote( $class, '/' ) . '(?:[.:\[][^\s>+~]*)?\s*$/', $selector ) ) {
				continue;
			}

			if ( ! preg_match( '/max-width:\s*([^;]+)/', $rule[2], $found ) ) {
				continue;
			}

			$value = trim( $found[1] );

			// A cap is fine as long as a more specific rule lifts it for the
			// builder. "none" and "100%" never squeeze anything.
			if ( 'none' === $value || '100%' === $value ) {
				continue;
			}

			$caps[] = $value . ' (from ' . $selector . ')';
		}
	}

	if ( ! $caps ) {
		EstatTests::ok( true, 'nothing caps .' . $class );
		continue;
	}

	// There is a cap. A builder-specific override must exist to undo it.
	$lifted = (bool) preg_match(
		'/\.' . preg_quote( $class, '/' ) . '\.estat-(?:wrap-wide|builder-form)[^{]*\{[^}]*max-width:\s*none/s',
		$css
	);

	EstatTests::ok(
		$lifted,
		'.' . $class . ' caps width at ' . implode( ', ', $caps ) . ', and the builder lifts that cap'
	);
}

// And the two overrides must be more specific than the caps they undo, or
// they lose the cascade and the fix is silently dead.
foreach (
	array(
		'.estat-screen .wrap.estat-wrap-wide'  => '.estat-screen .wrap',
		'.estat-form-admin.estat-builder-form' => '.estat-form-admin',
	) as $override => $capped
) {
	$override_specificity = substr_count( $override, '.' );
	$capped_specificity   = substr_count( $capped, '.' );

	EstatTests::ok(
		$override_specificity > $capped_specificity,
		'"' . $override . '" is more specific than "' . $capped . '", so it wins the cascade'
	);

	EstatTests::ok(
		false !== strpos( $css, $override ),
		'"' . $override . '" is actually in the stylesheet'
	);
}

// The builder form must really carry both classes, or neither override fires.
$forms_src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Admin/Screens/FormsScreen.php' );

EstatTests::ok(
	false !== strpos( $forms_src, 'class="estat-form-admin estat-builder-form"' ),
	'the builder form carries both classes the override needs'
);

EstatTests::group( 'The row controls fit inside the canvas column' );

// The controls were split onto two lines. Each line must fit on its own.
EstatTests::ok(
	false !== strpos( $css, '.estat-builder-tools-line' ),
	'the row controls are split across more than one line'
);
EstatTests::ok(
	false !== strpos( $js, "el( 'div', 'estat-builder-tools-line' )" ),
	'the builder really renders that second line'
);
EstatTests::ok(
	(bool) preg_match( '/\.estat-builder-row-tools\s*\{[^}]*display:\s*grid/s', $css ),
	'the row controls stack rather than fighting for one line'
);

estat_it22_fits(
	'row controls, line 1 (column presets)',
	array(
		array( 'the COLUMNS caption', 80 ),
		array( 'six layout chips', 6 * 42 + 5 * 4 ),
	),
	$canvas_inner
);

estat_it22_fits(
	'row controls, line 2 (spacing and alignment)',
	array(
		array( 'the Spacing caption', 70 ),
		array( 'the spacing slider', estat_it22_css_px( $css, '.estat-builder-range', 'max-width', 130 ) ),
		array( 'the spacing value', 40 ),
		array( 'the Align caption', 55 ),
		array( 'the alignment dropdown', estat_it22_css_px( $css, '.estat-builder-tool select', 'max-width', 130 ) ),
	),
	$canvas_inner
);

EstatTests::ok(
	(bool) preg_match( '/\.estat-builder-row-head input\[type="text"\]\s*\{[^}]*flex:\s*1 1 100px/s', $css ),
	'the row heading box shrinks rather than pushing the buttons off the line'
);

estat_it22_fits(
	'the row heading strip',
	array(
		array( 'the heading box', 100 ),
		array( 'Add a column', 100 ),
		array( 'move up', 34 ),
		array( 'move down', 34 ),
		array( 'Remove', 85 ),
	),
	$canvas_inner,
	8
);

// The hint is the least useful thing on this strip, so it is allowed to drop
// onto its own line rather than squeezing the three buttons.
EstatTests::ok(
	(bool) preg_match( '/\.estat-builder-device-hint\s*\{[^}]*flex:\s*1 1 100%/s', $css ),
	'the device hint wraps to its own line instead of squeezing the buttons'
);

estat_it22_fits(
	'the computer / tablet / phone buttons',
	array(
		array( 'Computer', 95 ),
		array( 'Tablet', 75 ),
		array( 'Phone', 75 ),
	),
	$canvas_inner,
	8
);

EstatTests::group( 'A column bar fits even when a row is split four ways' );

// Inside the canvas, columns share the width and a 10px gap.
$two_columns  = ( $canvas_inner - 10 ) / 2;
$four_columns = ( $canvas_inner - 30 ) / 4;

estat_it22_fits(
	'the column bar in a half-width column',
	array(
		array( 'the Width caption', 45 ),
		array( 'the width dropdown', 80 ),
		array( 'the remove icon', 24 ),
	),
	$two_columns,
	8
);

// At four columns the caption is hidden by CSS, so only the controls remain.
EstatTests::ok(
	false !== strpos( $css, '.estat-builder-column-width' ) && false !== strpos( $css, 'nth-child(3)' ),
	'the width caption is dropped once a row has three or more columns'
);

estat_it22_fits(
	'the column bar in a quarter-width column, caption hidden',
	array(
		array( 'the width dropdown', estat_it22_css_px( $css, '.estat-builder-column-pick', 'max-width', 80 ) ),
		array( 'the remove icon', 24 ),
	),
	$four_columns,
	8
);

EstatTests::group( 'Nothing spells out a word where there is no room for it' );

// "Remove column" and "Remove" were both being clipped inside a column.
EstatTests::ok(
	false === strpos( $js, "t.removeColumn || 'Remove column'" ),
	'the column remove control is no longer a wide worded button'
);
EstatTests::ok(
	(bool) preg_match( "/el\(\s*'button',\s*'estat-icon-button estat-danger-link',\s*'\\\\u00d7'\s*\)/", $js ),
	'a narrow icon button is used instead'
);

// An icon-only control must still say what it does, for screen readers.
$icon_buttons = preg_match_all( "/'estat-icon-button estat-danger-link'/", $js );
$aria_labels  = preg_match_all( "/\.setAttribute\(\s*'aria-label',\s*t\.(remove|removeColumn)/", $js );

EstatTests::ok( $icon_buttons > 0, 'icon buttons are used' );
EstatTests::is( $icon_buttons, $aria_labels, 'every icon button carries an aria-label, so it is still readable' );

EstatTests::group( 'The captions themselves are short enough' );

$admin_php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Admin/Admin.php' );
$start     = strpos( $admin_php, 'estatBuilder' );
$block     = false === $start ? '' : substr( $admin_php, $start, 6000 );

// These sit next to a control on a shared line. A long caption is what pushed
// "SPACE BETWEEN BOXES" onto three lines in the first place.
foreach ( array( 'gap', 'align', 'columnWidth', 'layout' ) as $key ) {
	if ( ! preg_match( "/'" . $key . "'\s*=> __\(\s*'([^']+)'/", $block, $m ) ) {
		EstatTests::ok( false, 'the caption "' . $key . '" is localised' );
		continue;
	}

	EstatTests::ok(
		strlen( $m[1] ) <= 14,
		'the caption for "' . $key . '" is short enough to sit beside its control (it is "' . $m[1] . '")'
	);
}

EstatTests::group( 'No part of the studio can scroll sideways' );

foreach (
	array(
		'.estat-studio-canvas'         => 'the canvas',
		'.estat-studio-preview-inner'  => 'the preview panel',
	) as $selector => $what
) {
	EstatTests::ok(
		(bool) preg_match( '/' . preg_quote( $selector, '/' ) . '[^{]*\{[^}]*overflow-x:\s*hidden/s', $css ),
		$what . ' cannot grow a sideways scrollbar'
	);
}

// Long words inside a narrow column must break rather than push it wider.
EstatTests::ok(
	(bool) preg_match( '/\.estat-builder-column[^{]*\{[^}]*overflow-wrap:\s*anywhere/s', $css ),
	'a long word inside a column wraps instead of widening the column'
);
EstatTests::ok(
	(bool) preg_match( '/\.estat-builder-field-title\s*\{[^}]*text-overflow:\s*ellipsis/s', $css ),
	'a long field name is trimmed with an ellipsis rather than clipped mid-letter'
);

EstatTests::group( 'The preview column has room for a two column form' );

$preview_half = ( $preview_inner - 10 ) / 2;

estat_it22_fits(
	'a half-width box in the preview',
	array(
		array( 'a label such as "Phone number *"', 120 ),
	),
	$preview_half,
	0
);

EstatTests::ok(
	(bool) preg_match( '/\.estat-preview-field\s*\{[^}]*min-width:\s*0/s', $css ),
	'a preview box may shrink below its content, so it cannot force a scrollbar'
);
