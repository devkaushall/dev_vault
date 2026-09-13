<?php
/**
 * Form builder, submissions, conditional logic and the form to enquiry pipeline.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Forms\FieldTypes;
use EstatOS\Forms\Forms;
use EstatOS\Forms\Renderer;
use EstatOS\Forms\Submissions;
use EstatOS\Leads\Leads;

estat_reset_world();
estat_login_owner();

/* --------------------------------------------------------------- Seeding */

EstatTests::group( 'Integration: the starter form' );

Forms::seed();
$all = Forms::all();
EstatTests::ok( ! empty( $all ), 'a starter form is created on activation' );
$form_id = (int) $all[0]['id'];

Forms::seed();
EstatTests::is( count( $all ), count( Forms::all() ), 'seeding twice does not create a second starter form' );

$form = Forms::get( $form_id );
EstatTests::ok( is_array( $form ), 'the starter form can be read back' );
EstatTests::ok( ! empty( $form['definition']['rows'] ), 'the starter form has rows' );

$fields = Forms::flatten( $form['definition'] );
EstatTests::ok( count( $fields ) >= 3, 'the starter form has usable fields' );

$types = array_column( $fields, 'type' );
EstatTests::ok(
	in_array( 'name', $types, true ) || in_array( 'text', $types, true ),
	'it collects a name'
);
EstatTests::ok(
	in_array( 'phone', $types, true ) || in_array( 'tel', $types, true ) || in_array( 'email', $types, true ),
	'it collects a way to make contact'
);

/* -------------------------------------------------------- Field catalogue */

EstatTests::group( 'Integration: field types' );

$catalogue = FieldTypes::all();
EstatTests::ok( count( $catalogue ) >= 20, 'the builder offers a broad field catalogue' );

foreach ( $catalogue as $key => $def ) {
	EstatTests::ok( ! empty( $def['label'] ), 'field type "' . $key . '" has a human label' );
}

/* ------------------------------------------------------------- Rendering */

EstatTests::group( 'Integration: rendering a form' );

$html = Renderer::render( $form_id );
EstatTests::ok( '' !== $html, 'the form renders to markup' );
EstatTests::ok( false !== strpos( $html, '<form' ), 'the output contains a form element' );
EstatTests::ok( false !== strpos( $html, 'data-endpoint' ), 'the form advertises its submit endpoint for the script' );
EstatTests::ok( false !== strpos( $html, 'idempotency_key' ), 'the form carries an idempotency key' );
EstatTests::ok( false === strpos( $html, '<script>alert' ), 'no unescaped script leaks into the markup' );

EstatTests::ok(
	false !== strpos( $html, 'novalidate' ) || false !== strpos( $html, 'required' ),
	'required fields are marked up for the browser'
);

/* --------------------------------------------------- Escaping user content */

EstatTests::group( 'Integration: a hostile form definition cannot inject script' );

$evil_id = Forms::save(
	array(
		'name'       => 'Evil <script>alert(1)</script> form',
		'definition' => array(
			'rows' => array(
				array(
					'columns' => array(
						array(
							'width'  => 100,
							'fields' => array(
								array(
									'id'          => 'name',
									'type'        => 'text',
									'label'       => '"><script>alert(1)</script>',
									'placeholder' => '"><img src=x onerror=alert(1)>',
									'required'    => true,
								),
							),
						),
					),
				),
			),
		),
	)
);
$evil_html = Renderer::render( (int) $evil_id );
EstatTests::ok( false === strpos( $evil_html, '<script>alert(1)</script>' ), 'a script tag in a field label is escaped on output' );
EstatTests::ok( false === strpos( $evil_html, 'onerror=alert(1)' ), 'an event handler in a placeholder is escaped on output' );

/* ------------------------------------------------------------ Submission */

EstatTests::group( 'Integration: submitting a form creates an enquiry' );

$field_ids = array_column( $fields, 'id' );
$payload   = array();
foreach ( $fields as $f ) {
	switch ( $f['type'] ) {
		case 'email':
			$payload[ $f['id'] ] = 'buyer@example.test';
			break;
		case 'phone':
		case 'tel':
			$payload[ $f['id'] ] = '+91 98111 22333';
			break;
		case 'textarea':
		case 'message':
			$payload[ $f['id'] ] = 'Please call me back about this property.';
			break;
		case 'consent':
			$payload[ $f['id'] ] = '1';
			break;
		case 'name':
		case 'text':
			$payload[ $f['id'] ] = 'Sunita Rao';
			break;
		default:
			break;
	}
}

$before = Leads::counts();
$result = Submissions::handle(
	$form_id,
	array(
		'fields'          => $payload,
		'idempotency_key' => 'form-idem-001',
	)
);
EstatTests::ok( ! is_wp_error( $result ), 'a complete submission is accepted' );
EstatTests::ok( ! empty( $result['lead_id'] ), 'the submission produced an enquiry' );

$lead = Leads::get( (int) $result['lead_id'] );
EstatTests::ok( is_array( $lead ), 'that enquiry exists in the enquiries table' );
EstatTests::ok( '' !== (string) $lead['phone'] || '' !== (string) $lead['email'], 'the enquiry captured a contact detail' );

/* -------------------------------------------------- Submission idempotency */

EstatTests::group( 'Integration: a double-clicked submit does not double-book' );

$again = Submissions::handle(
	$form_id,
	array(
		'fields'          => $payload,
		'idempotency_key' => 'form-idem-001',
	)
);
EstatTests::ok( ! is_wp_error( $again ), 'the repeat submission is handled gracefully' );
EstatTests::is(
	(int) $result['lead_id'],
	(int) $again['lead_id'],
	'resubmitting with the same key returns the original enquiry instead of a duplicate'
);

/* -------------------------------------------------------- Required fields */

EstatTests::group( 'Integration: validation messages' );

$empty = Submissions::handle( $form_id, array( 'fields' => array(), 'idempotency_key' => 'form-idem-002' ) );
EstatTests::ok( is_wp_error( $empty ), 'an empty submission is refused' );
if ( is_wp_error( $empty ) ) {
	EstatTests::ok( '' !== $empty->get_error_message(), 'the refusal carries a message the visitor can read' );
}

$bad_email = $payload;
foreach ( $fields as $f ) {
	if ( 'email' === $f['type'] ) {
		$bad_email[ $f['id'] ] = 'definitely-not-an-email';
	}
}
$bad = Submissions::handle( $form_id, array( 'fields' => $bad_email, 'idempotency_key' => 'form-idem-003' ) );
EstatTests::ok( is_wp_error( $bad ) || ! empty( $bad['lead_id'] ), 'a malformed email is either refused or safely normalised' );

/* ------------------------------------------------------------ Duplicate */

EstatTests::group( 'Integration: managing forms' );

$copy_id = Forms::duplicate( $form_id );
EstatTests::ok( $copy_id > 0 && $copy_id !== $form_id, 'a form can be duplicated' );

$copy = Forms::get( (int) $copy_id );
EstatTests::is(
	count( Forms::flatten( $form['definition'] ) ),
	count( Forms::flatten( $copy['definition'] ) ),
	'the copy has the same number of fields as the original'
);

EstatTests::ok( Forms::delete( (int) $copy_id ), 'a form can be deleted' );
EstatTests::ok( null === Forms::get( (int) $copy_id ) || false === Forms::get( (int) $copy_id ), 'the deleted form is gone' );

/* ----------------------------------------------------- Responsive widths */

EstatTests::group( 'Integration: responsive layout survives a save' );

$resp_id = (int) Forms::save(
	array(
		'name'       => 'Responsive layout form',
		'definition' => array(
			'rows' => array(
				array(
					'columns' => array(
						array(
							'width'        => 50,
							'width_tablet' => 100,
							'width_mobile' => 100,
							'fields'       => array(
								array( 'id' => 'a', 'type' => 'text', 'label' => 'First name', 'width' => 50, 'width_tablet' => 100, 'width_mobile' => 100 ),
							),
						),
					),
				),
			),
		),
	)
);
$resp = Forms::get( $resp_id );
$col  = $resp['definition']['rows'][0]['columns'][0];
EstatTests::is( 50, (int) $col['width'], 'the desktop width is kept' );
EstatTests::is( 100, (int) $col['width_tablet'], 'the tablet width is kept' );
EstatTests::is( 100, (int) $col['width_mobile'], 'the mobile width is kept' );

$resp_html = Renderer::render( $resp_id );
EstatTests::ok( false !== strpos( $resp_html, '--estat-w' ) || false !== strpos( $resp_html, 'estat-col' ), 'the rendered column carries its width for CSS' );
