<?php
/**
 * The form studio: seven stages, three columns, and full styling.
 *
 * The rule that matters most is the one at the bottom: styling is appearance
 * only. No colour, size or corner radius may ever change a field, a label,
 * whether a field is required, or where a submission goes.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Screens\FormsScreen;
use EstatOS\Forms\Forms;
use EstatOS\Forms\Renderer;
use EstatOS\Forms\Style;

estat_reset_world();
estat_login_owner();

/**
 * Make a form and hand back its id.
 *
 * @param array  $settings Settings to merge.
 * @param string $name     Form name.
 * @return int
 */
function estat_it21_form( array $settings = array(), string $name = 'Studio form' ): int {
	$id = Forms::save(
		array(
			'name'       => $name,
			'definition' => wp_json_encode(
				array(
					'rows' => array(
						array(
							'columns' => array(
								array(
									'width'  => 100,
									'fields' => array(
										array(
											'id'       => 'nm',
											'type'     => 'text',
											'label'    => 'Your name',
											'required' => true,
										),
										array(
											'id'    => 'ms',
											'type'  => 'textarea',
											'label' => 'Message',
										),
									),
								),
							),
						),
					),
				)
			),
			'settings'   => array_merge( array( 'submit_label' => 'Send' ), $settings ),
		)
	);

	return is_wp_error( $id ) ? 0 : (int) $id;
}

/**
 * Render the builder screen for a form.
 *
 * @param int $form_id Form ID.
 * @return string
 */
function estat_it21_screen( int $form_id ): string {
	$_GET['edit']     = (string) $form_id;
	$_REQUEST['edit'] = (string) $form_id;

	ob_start();
	try {
		FormsScreen::render();
		$html = ob_get_contents();
		ob_end_clean();
	} catch ( \Throwable $e ) {
		$html = ob_get_contents();
		ob_end_clean();
		$html .= "\n<!-- FATAL " . get_class( $e ) . ' @ ' . $e->getLine() . ': ' . $e->getMessage() . ' -->';
	}

	unset( $_GET['edit'], $_REQUEST['edit'] );

	return (string) $html;
}

$form_id = estat_it21_form();
$screen  = estat_it21_screen( $form_id );

EstatTests::group( 'The builder is arranged in seven stages' );

EstatTests::ok( $form_id > 0, 'the test form saved' );
EstatTests::ok( false === strpos( $screen, 'FATAL' ), 'the builder screen renders without fatalling' );

$stages = FormsScreen::stages();

EstatTests::is( 7, count( $stages ), 'there are seven stages' );
EstatTests::is(
	'container,layout,content,styling,advanced,backend,done',
	implode( ',', array_keys( $stages ) ),
	'the stages run container, layout, content, styling, advanced, where it goes, finish'
);

foreach ( array_keys( $stages ) as $key ) {
	EstatTests::ok(
		false !== strpos( $screen, 'data-stage="' . $key . '"' ),
		'the "' . $key . '" stage is on the screen'
	);
	EstatTests::ok(
		false !== strpos( $screen, 'id="estat-stage-' . $key . '"' ),
		'the "' . $key . '" panel can be found by its tab'
	);
	EstatTests::ok(
		false !== strpos( $screen, 'id="estat-stage-tab-' . $key . '"' ),
		'the "' . $key . '" tab exists'
	);
}

EstatTests::is( 7, (int) preg_match_all( '/class="estat-stage"/', $screen ), 'seven panels are rendered' );
EstatTests::is( 7, (int) preg_match_all( '/class="estat-stage-tab/', $screen ), 'seven tabs are rendered' );

// Only the first stage may be open, or the panel becomes an endless scroll.
EstatTests::is(
	6,
	(int) preg_match_all( '/data-stage="[a-z]+"\s*hidden/', $screen ),
	'six panels start hidden, so only one stage shows at a time'
);

EstatTests::group( 'The screen really is a three column studio' );

foreach (
	array(
		'estat-studio'          => 'the studio wrapper is there',
		'estat-studio-panel'    => 'the controls column is there',
		'estat-studio-canvas'   => 'the canvas column is there',
		'estat-studio-preview'  => 'the preview column is there',
		'estat-builder'         => 'the row designer is inside the canvas',
		'estat-form-preview'    => 'the visitor preview is inside the preview column',
	) as $needle => $why
) {
	EstatTests::ok( false !== strpos( $screen, (string) $needle ), $why );
}

$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/admin.css' );

EstatTests::ok(
	(bool) preg_match( '/grid-template-columns:\s*minmax\(0,\s*22fr\)\s+minmax\(0,\s*43fr\)\s+minmax\(0,\s*35fr\)/', $css ),
	'the columns are 22 / 43 / 35 controls, canvas and preview'
);

// fr units, not percentages: with percentages the two gaps were added ON TOP
// of a full 100%, so the grid overflowed and the columns were squeezed.
EstatTests::ok(
	false === strpos( $css, 'grid-template-columns: 22% minmax' ),
	'the studio no longer uses percentages that overflow once gaps are added'
);
EstatTests::ok(
	(bool) preg_match( '/\.estat-studio-preview\s*\{[^}]*position:\s*sticky/s', $css ),
	'the preview sticks, so it stays in front of the office while they scroll'
);

// The preview must come after the canvas in the markup, or it is not on the right.
EstatTests::ok(
	strpos( $screen, 'estat-studio-canvas' ) < strpos( $screen, 'estat-studio-preview' ),
	'the preview sits to the right of the canvas'
);

EstatTests::group( 'Every styling control is saved and validated' );

$defaults = Style::defaults();
$screen_php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Admin/Screens/FormsScreen.php' );
$style_php  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Forms/Style.php' );

// Pull the second argument out of every style control call.
preg_match_all( '/self::style_(?:range|colour|choice)\((.*?)\);/s', $screen_php, $calls );
$controls = array();
foreach ( $calls[1] as $args ) {
	if ( preg_match( "/,\s*'([a-z_]+)'/", $args, $m ) ) {
		$controls[] = $m[1];
	}
}
$controls = array_unique( $controls );

EstatTests::ok( count( $controls ) > 20, 'the styling stage offers a full set of controls' );

foreach ( $controls as $key ) {
	EstatTests::ok(
		isset( $defaults[ $key ] ),
		'the styling control "' . $key . '" has a default, so it cannot be dropped on save'
	);
	EstatTests::ok(
		false !== strpos( $screen, 'settings[style][' . $key . ']' ),
		'the styling control "' . $key . '" is actually posted'
	);
}

// And nothing in the schema is unreachable from the screen.
foreach ( array_keys( $defaults ) as $key ) {
	EstatTests::ok(
		in_array( $key, $controls, true ),
		'the style setting "' . $key . '" has a control, so it is not dead weight'
	);
}

EstatTests::group( 'Styling survives the round trip and reaches the visitor' );

$styled_id = estat_it21_form(
	array(
		'style' => array(
			'font'         => 'serif',
			'radius'       => 20,
			'input_size'   => 22,
			'label_weight' => '700',
			'button_bg'    => '#ff0000',
			'button_align' => 'center',
			'button_width' => 'full',
		),
	),
	'Styled form'
);

$styled_html = Renderer::render( $styled_id );

foreach (
	array(
		'--estat-f-radius:20px'       => 'the corner radius reaches the page',
		'--estat-f-font:22px'         => 'the text size reaches the page',
		'--estat-f-label-weight:700'  => 'the label thickness reaches the page',
		'--estat-f-btn-bg:#ff0000'    => 'the button colour reaches the page',
		'Georgia'                     => 'the chosen font stack reaches the page',
		'is-center'                   => 'the button alignment reaches the page',
		'is-full'                     => 'a full width button reaches the page',
	) as $needle => $why
) {
	EstatTests::ok( false !== strpos( $styled_html, (string) $needle ), $why );
}

// An unset colour must stay unset, so the theme keeps control.
$plain_html = Renderer::render( estat_it21_form( array(), 'Plain styled form' ) );

EstatTests::ok(
	false === strpos( $plain_html, '--estat-f-btn-bg:' ),
	'a colour left empty is not written at all, so the theme decides'
);

EstatTests::group( 'Styling cannot be used to inject CSS' );

$hostile = Style::sanitize(
	array(
		'font'         => '"><script>alert(1)</script>',
		'radius'       => 99999,
		'border_width' => -20,
		'button_bg'    => 'red; background:url(https://evil.example/x)',
		'label_colour' => 'expression(alert(1))',
		'input_bg'     => 'rgb(0,0,0)',
		'label_weight' => '999',
		'button_align' => 'javascript:alert(1)',
		'button_width' => '../../etc/passwd',
	)
);

EstatTests::is( 'theme', $hostile['font'], 'a script tag in the font falls back to the theme font' );
EstatTests::is( 40, $hostile['radius'], 'a huge radius is clamped' );
EstatTests::is( 0, $hostile['border_width'], 'a negative border is clamped to zero' );
EstatTests::is( '', $hostile['button_bg'], 'a colour carrying extra CSS is refused outright' );
EstatTests::is( '', $hostile['label_colour'], 'an expression() colour is refused' );
EstatTests::is( '', $hostile['input_bg'], 'even a valid rgb() colour is refused: only hex is allowed' );
EstatTests::is( '600', $hostile['label_weight'], 'an invalid weight falls back' );
EstatTests::is( 'left', $hostile['button_align'], 'a javascript: alignment falls back' );
EstatTests::is( 'auto', $hostile['button_width'], 'a path traversal in the width falls back' );

$hostile_css = Style::to_css(
	array(
		'button_bg' => 'red; background:url(https://evil.example/x)',
		'radius'    => 50,
	)
);

EstatTests::ok( false === strpos( $hostile_css, 'evil.example' ), 'no injected url survives into the CSS' );
EstatTests::ok( false === strpos( $hostile_css, 'url(' ), 'no url() can be smuggled into the CSS' );

// A hex colour is the only accepted shape.
EstatTests::is( '#abc123', Style::colour( '#abc123' ), 'a plain hex colour is kept' );
EstatTests::is( '#fff', Style::colour( '#fff' ), 'a short hex colour is kept' );
EstatTests::is( '', Style::colour( '#gggggg' ), 'a colour that is not hex is refused' );
EstatTests::is( '', Style::colour( '' ), 'no colour stays no colour' );

EstatTests::group( 'Styling never changes what a form means' );

$definition = array(
	'rows' => array(
		array(
			'columns' => array(
				array(
					'width'  => 100,
					'fields' => array(
						array(
							'id'       => 'nm',
							'type'     => 'text',
							'label'    => 'Your name',
							'required' => true,
						),
						array(
							'id'    => 'em',
							'type'  => 'email',
							'label' => 'Email',
						),
					),
				),
			),
		),
	),
);

$business = array(
	'submit_label'     => 'Send',
	'create_lead'      => true,
	'store_submission' => true,
	'notify'           => true,
	'notify_email'     => 'office@example.com',
	'require_consent'  => true,
	'rate_limit'       => 7,
	'redirect_url'     => 'https://example.com/thanks',
);

$bare = Forms::save(
	array(
		'name'       => 'Bare',
		'definition' => wp_json_encode( $definition ),
		'settings'   => $business,
	)
);

$loud = Forms::save(
	array(
		'name'       => 'Loud',
		'definition' => wp_json_encode( $definition ),
		'settings'   => array_merge(
			$business,
			array(
				'style' => array(
					'font'          => 'mono',
					'radius'        => 38,
					'border_width'  => 5,
					'input_size'    => 27,
					'label_colour'  => '#123456',
					'button_bg'     => '#00ff00',
					'button_radius' => 33,
					'field_gap'     => 55,
				),
			)
		),
	)
);

EstatTests::ok( ! is_wp_error( $bare ) && ! is_wp_error( $loud ), 'both forms saved' );

$bare_settings = Forms::get( (int) $bare )['settings'];
$loud_settings = Forms::get( (int) $loud )['settings'];

// Every business setting must be identical. Only "style" may differ.
foreach ( $bare_settings as $key => $value ) {
	if ( 'style' === $key ) {
		continue;
	}

	EstatTests::is(
		wp_json_encode( $value ),
		wp_json_encode( $loud_settings[ $key ] ),
		'heavy styling left the "' . $key . '" setting untouched'
	);
}

EstatTests::ok(
	wp_json_encode( $bare_settings['style'] ) !== wp_json_encode( $loud_settings['style'] ),
	'the two forms really do look different, so the comparison above means something'
);

EstatTests::is(
	wp_json_encode( Forms::get( (int) $bare )['definition'] ),
	wp_json_encode( Forms::get( (int) $loud )['definition'] ),
	'heavy styling left every field, label and required flag identical'
);

EstatTests::group( 'The builder JavaScript matches the screen' );

$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/form-builder.js' );

EstatTests::ok( false !== strpos( $js, 'function initStages' ), 'the stage switcher exists' );
EstatTests::ok( false !== strpos( $js, 'function initStyling' ), 'the styling controls are wired up' );
EstatTests::ok( false !== strpos( $js, 'function applyStyle' ), 'styling repaints the preview' );

// This file has no jQuery and did not previously define all(); the stage code
// assumed a helper from a different script, which would have thrown on load.
EstatTests::ok(
	(bool) preg_match( '/function all\(\s*selector/', $js ),
	'the all() helper the stage code relies on is defined in this file'
);

// Every class the stage and styling code queries must be rendered by PHP.
foreach ( array( 'estat-stage-bar', 'estat-stage-tab', 'estat-stage', 'estat-style-input', 'estat-style-toggle', 'estat-style-colour', 'estat-style-value' ) as $class ) {
	EstatTests::ok( false !== strpos( $js, $class ), 'the JS knows about .' . $class );
	EstatTests::ok( false !== strpos( $screen, $class ), 'the PHP renders .' . $class );
}

// Every custom property the JS paints must be one Style::to_css also emits,
// or the preview and the real page would disagree.
preg_match_all( "/^\t\t\t[a-z_]+: '(--estat-f-[a-z-]+)'/m", $js, $props );

foreach ( array_unique( $props[1] ) as $property ) {
	EstatTests::ok(
		false !== strpos( $style_php, (string) $property ),
		'the preview property ' . $property . ' is one the front end really uses'
	);
}

// The double "100%" that overlapped on narrow columns must not come back.
EstatTests::ok(
	false === strpos( $js, "'estat-builder-column-width', Number( shown || 100 ) + '%'" ),
	'the column no longer prints its width twice, once as a tag and once in the dropdown'
);
