<?php
/**
 * The form builder's layout controls must survive the whole round trip:
 * builder -> JSON -> sanitiser -> stored -> rendered HTML.
 *
 * Layout is presentation, so none of it may ever change what a submission
 * means. The last group here pins that separation.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Forms\Forms;
use EstatOS\Forms\Renderer;

estat_reset_world();
estat_login_owner();

/**
 * Build a form from a raw definition array and hand back its rendered HTML.
 *
 * @param array  $definition Raw definition.
 * @param string $name       Form name.
 * @return array{id:int,html:string,clean:array}
 */
function estat_it20_build( array $definition, string $name ): array {
	$id = Forms::save(
		array(
			'name'       => $name,
			'definition' => wp_json_encode( $definition ),
		)
	);

	if ( is_wp_error( $id ) ) {
		return array(
			'id'    => 0,
			'html'  => '<!-- SAVE FAILED: ' . $id->get_error_message() . ' -->',
			'clean' => array(),
		);
	}

	$stored = Forms::get( (int) $id );

	return array(
		'id'    => (int) $id,
		'html'  => Renderer::render( (int) $id ),
		'clean' => is_array( $stored ) ? (array) $stored['definition'] : array(),
	);
}

$definition = array(
	'rows' => array(
		array(
			'heading'    => 'About you',
			'gap'        => 24,
			'gap_mobile' => 8,
			'align'      => 'center',
			'columns'    => array(
				array(
					'width'        => 66,
					'width_tablet' => 66,
					'width_mobile' => 100,
					'fields'       => array(
						array(
							'id'    => 'nm',
							'type'  => 'text',
							'label' => 'Name',
							'size'  => 'lg',
						),
					),
				),
				array(
					'width'        => 33,
					'width_tablet' => 33,
					'width_mobile' => 100,
					'fields'       => array(
						array(
							'id'    => 'ph',
							'type'  => 'text',
							'label' => 'Phone',
							'size'  => 'sm',
							'width' => 50,
						),
					),
				),
			),
		),
		array(
			'gap'     => 0,
			'align'   => 'end',
			'columns' => array(
				array(
					'width'  => 100,
					'fields' => array(
						array(
							'id'    => 'ms',
							'type'  => 'textarea',
							'label' => 'Message',
							'rows'  => 9,
						),
					),
				),
			),
		),
	),
);

$built = estat_it20_build( $definition, 'Layout round trip' );
$html  = $built['html'];
$clean = $built['clean'];

EstatTests::group( 'Layout choices survive being saved' );

EstatTests::ok( $built['id'] > 0, 'the form saved' );
EstatTests::ok( false === strpos( $html, 'SAVE FAILED' ), 'the form rendered' );

EstatTests::is( 24, (int) $clean['rows'][0]['gap'], 'the desktop gap is kept exactly' );
EstatTests::is( 8, (int) $clean['rows'][0]['gap_mobile'], 'the phone gap is kept exactly' );
EstatTests::is( 'center', (string) $clean['rows'][0]['align'], 'the alignment is kept' );
EstatTests::is( 66, (int) $clean['rows'][0]['columns'][0]['width'], 'a 66% column stays 66%' );
EstatTests::is( 33, (int) $clean['rows'][0]['columns'][1]['width'], 'a 33% column stays 33%' );
EstatTests::is( 'lg', (string) $clean['rows'][0]['columns'][0]['fields'][0]['size'], 'a large box stays large' );
EstatTests::is( 'sm', (string) $clean['rows'][0]['columns'][1]['fields'][0]['size'], 'a small box stays small' );
EstatTests::is( 9, (int) $clean['rows'][1]['columns'][0]['fields'][0]['rows'], 'a 9-line message box stays 9 lines' );
EstatTests::is( 0, (int) $clean['rows'][1]['gap'], 'a deliberate zero gap is not replaced by the default' );

EstatTests::group( 'Layout choices reach the visitor as real CSS' );

foreach (
	array(
		'--gap:24px'      => 'the desktop gap is written as a custom property',
		'--gap-m:8px'     => 'the phone gap is written as a custom property',
		'is-align-center' => 'centre alignment reaches the markup',
		'is-align-end'    => 'bottom alignment reaches the markup',
		'--w:66%'         => 'the 66% column width reaches the markup',
		'--wt:66%'        => 'the tablet width reaches the markup',
		'--wm:100%'       => 'the phone width reaches the markup',
		'estat-size-lg'   => 'a large box gets its size class',
		'estat-size-sm'   => 'a small box gets its size class',
		'rows="9"'        => 'the message box really is nine lines tall',
		'--gap:0px'       => 'a zero gap is written, not omitted',
	) as $needle => $why
) {
	EstatTests::ok( false !== strpos( $html, (string) $needle ), $why );
}

EstatTests::group( 'The double-gutter bug stays fixed' );

// A column used to carry both a flex gap AND a padding-right, so two 50%
// columns added up to more than 100% and wrapped onto separate lines.
$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/forms.css' );

EstatTests::ok(
	false === strpos( $css, 'padding-right:12px' ),
	'the old padding gutter is gone'
);
EstatTests::ok(
	false !== strpos( $css, 'gap: var(--gap' ),
	'rows space their columns with a real gap'
);
EstatTests::ok(
	(bool) preg_match( '/flex:\s*1 1 calc\(var\(--w,\s*100%\) - var\(--gap/', $css ),
	'a column subtracts its share of the gap, so 50/50 really fits on one line'
);

// The field wrapper must not re-apply the column width on top of the column.
EstatTests::ok(
	0 === preg_match( '/class="estat-field[^"]*" style="width:/', $html ),
	'a field no longer hard-codes a width that fights its column'
);
EstatTests::ok(
	false !== strpos( $html, '--fw:' ),
	'a field states its own width as a custom property instead'
);

EstatTests::group( 'Nonsense layout values cannot get through' );

$hostile = estat_it20_build(
	array(
		'rows' => array(
			array(
				'gap'        => 9999,
				'gap_mobile' => -50,
				'align'      => '"><script>alert(1)</script>',
				'columns'    => array(
					array(
						'width'  => 500,
						'fields' => array(
							array(
								'id'    => 'x',
								'type'  => 'text',
								'label' => 'X',
								'size'  => '"><script>',
								'rows'  => 9999,
							),
						),
					),
				),
			),
		),
	),
	'Hostile layout'
);

$hclean = $hostile['clean'];

EstatTests::is( 80, (int) $hclean['rows'][0]['gap'], 'a huge gap is clamped to the maximum' );
EstatTests::is( 0, (int) $hclean['rows'][0]['gap_mobile'], 'a negative gap is clamped to zero' );
EstatTests::is( 'stretch', (string) $hclean['rows'][0]['align'], 'a script tag in the alignment falls back to a safe value' );
EstatTests::is( 100, (int) $hclean['rows'][0]['columns'][0]['width'], 'a 500% column is clamped to 100%' );
EstatTests::is( 'md', (string) $hclean['rows'][0]['columns'][0]['fields'][0]['size'], 'a script tag in the size falls back to medium' );
EstatTests::is( 20, (int) $hclean['rows'][0]['columns'][0]['fields'][0]['rows'], 'a 9999-line box is clamped to 20 lines' );
EstatTests::ok(
	false === strpos( $hostile['html'], '<script>alert(1)</script>' ),
	'no script from a layout value is ever printed'
);

EstatTests::group( 'Changing the layout never changes what a form means' );

// Same fields, wildly different layout. The business definition -- ids, types,
// labels, required flags -- must come out identical.
$plain = estat_it20_build(
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
								'label'    => 'Name',
								'required' => true,
							),
							array(
								'id'    => 'ph',
								'type'  => 'text',
								'label' => 'Phone',
							),
						),
					),
				),
			),
		),
	),
	'Plain layout'
);

$fancy = estat_it20_build(
	array(
		'rows' => array(
			array(
				'gap'     => 40,
				'align'   => 'end',
				'columns' => array(
					array(
						'width'  => 25,
						'fields' => array(
							array(
								'id'       => 'nm',
								'type'     => 'text',
								'label'    => 'Name',
								'required' => true,
								'size'     => 'lg',
							),
						),
					),
					array(
						'width'  => 75,
						'fields' => array(
							array(
								'id'    => 'ph',
								'type'  => 'text',
								'label' => 'Phone',
								'size'  => 'sm',
							),
						),
					),
				),
			),
		),
	),
	'Fancy layout'
);

/**
 * Strip every presentation key, leaving only what the form MEANS.
 *
 * @param array $definition Clean definition.
 * @return array
 */
function estat_it20_meaning( array $definition ): array {
	$out = array();

	foreach ( (array) $definition['rows'] as $row ) {
		foreach ( (array) $row['columns'] as $column ) {
			foreach ( (array) $column['fields'] as $field ) {
				$out[] = array(
					'id'       => $field['id'],
					'type'     => $field['type'],
					'label'    => $field['label'],
					'required' => (bool) $field['required'],
				);
			}
		}
	}

	return $out;
}

EstatTests::is(
	wp_json_encode( estat_it20_meaning( $plain['clean'] ) ),
	wp_json_encode( estat_it20_meaning( $fancy['clean'] ) ),
	'two very different layouts describe exactly the same form'
);

EstatTests::group( 'The builder and the sanitiser agree' );

$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/form-builder.js' );

// A new row must start with values the server will accept. It once shipped
// gap:'normal', which the server read as a number and turned into 0.
EstatTests::ok(
	false === strpos( $js, "gap: 'normal'" ),
	'a new row no longer stores its gap as a word the server reads as zero'
);
EstatTests::ok(
	(bool) preg_match( '/newRow\(\)\s*\{\s*return \{[^}]*gap: \d+/', $js ),
	'a new row stores its gap as a number'
);

// Every key the builder writes must be a key the sanitiser keeps.
$forms_php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Forms/Forms.php' );

preg_match_all( '/\brow\.([a-z_]+) *=/', $js, $row_keys );
preg_match_all( '/\bfield\.([a-z_]+) *=/', $js, $field_keys );
preg_match_all( '/\bcolumn\.([a-z_]+) *=/', $js, $column_keys );

foreach ( array_unique( array_merge( $row_keys[1], $field_keys[1], $column_keys[1] ) ) as $key ) {
	EstatTests::ok(
		false !== strpos( $forms_php, "'" . $key . "'" ),
		'the sanitiser keeps "' . $key . '", so the builder cannot silently lose it'
	);
}

EstatTests::group( 'Every builder string is translated' );

$admin_php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Admin/Admin.php' );
$localised = '';
$start     = strpos( $admin_php, 'estatBuilder' );
if ( false !== $start ) {
	$localised = substr( $admin_php, $start, 6000 );
}

preg_match_all( '/\bt\.([a-zA-Z]+) *\|\|/', $js, $strings );

foreach ( array_unique( $strings[1] ) as $key ) {
	EstatTests::ok(
		false !== strpos( $localised, "'" . $key . "'" ),
		'the builder string "' . $key . '" is localised, so it can be translated'
	);
}
