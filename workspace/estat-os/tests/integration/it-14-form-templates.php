<?php
/**
 * Ready-made forms, the simpler builder, and enquiries arriving from
 * another form tool.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Actions;
use EstatOS\Admin\Screens\FormsScreen;
use EstatOS\Data\Listings;
use EstatOS\Forms\FieldTypes;
use EstatOS\Forms\Forms;
use EstatOS\Forms\Renderer;
use EstatOS\Forms\Submissions;
use EstatOS\Forms\Templates;
use EstatOS\Leads\Leads;
use EstatOS\Rest\RestApi;
use EstatOS\Settings\Settings;

estat_reset_world();
estat_login_owner();

/* ============================================ Ready-made forms */

EstatTests::group( 'Integration: the ready-made forms' );

$templates = Templates::all();

EstatTests::is( 5, count( $templates ), 'there are five ready-made forms' );

foreach ( array( 'enquiry', 'visit', 'contact', 'sell', 'valuation' ) as $key ) {
	EstatTests::ok( isset( $templates[ $key ] ), 'there is a "' . $key . '" form' );
}

foreach ( $templates as $key => $template ) {
	foreach ( array( 'label', 'icon', 'description', 'best_for', 'settings', 'definition' ) as $part ) {
		EstatTests::ok( isset( $template[ $part ] ), $key . ' describes its "' . $part . '"' );
	}

	EstatTests::ok( '' !== trim( (string) $template['label'] ), $key . ' has a name a person can read' );
	EstatTests::ok( ! empty( $template['definition']['rows'] ), $key . ' actually contains fields' );
}

EstatTests::is( null, Templates::get( 'not_a_template' ), 'an unknown template is not invented' );

/* ------------------------------- Field ids must stay standard */

EstatTests::group( 'Integration: a phone box is called "phone" in every form' );

// If one template called it "mobile" and another "phone", every report and
// export downstream would have to know which template was used.
$labels_by_id = array();

foreach ( $templates as $key => $template ) {
	foreach ( Forms::flatten( $template['definition'] ) as $field ) {
		$id = (string) $field['id'];

		if ( isset( $labels_by_id[ $id ] ) ) {
			EstatTests::is(
				$labels_by_id[ $id ],
				(string) $field['type'],
				'"' . $id . '" is the same kind of box in every ready-made form'
			);
			continue;
		}

		$labels_by_id[ $id ] = (string) $field['type'];
	}
}

// Renaming a standard box would quietly break every export, report and
// integration that relies on the name, so pin the important ones down.
$expected_ids = array(
	'enquiry'   => array( 'name', 'phone', 'email', 'message', 'consent' ),
	'visit'     => array( 'name', 'phone', 'visit_date', 'visit_time', 'message', 'consent' ),
	'contact'   => array( 'name', 'phone', 'email', 'message' ),
	'sell'      => array( 'name', 'phone', 'property_type', 'locality', 'budget', 'address', 'consent' ),
	'valuation' => array( 'name', 'phone', 'locality', 'property_type', 'area', 'consent' ),
);

foreach ( $expected_ids as $key => $ids ) {
	$actual = array_map(
		static function ( array $field ): string {
			return (string) $field['id'];
		},
		Forms::flatten( $templates[ $key ]['definition'] )
	);

	EstatTests::is( $ids, $actual, 'the "' . $key . '" form uses exactly the agreed field names, in order' );
}

// Every id a template uses must be one of the agreed standard names.
foreach ( $templates as $key => $template ) {
	foreach ( Forms::flatten( $template['definition'] ) as $field ) {
		$id = (string) $field['id'];

		EstatTests::ok(
			in_array( $id, Templates::STANDARD_IDS, true ) || 'area' === $id,
			'"' . $id . '" in the ' . $key . ' form is one of the agreed standard names'
		);
	}
}

// Anything named in STANDARD_IDS that a template uses must match that spelling.
foreach ( array_keys( $labels_by_id ) as $id ) {
	EstatTests::ok(
		(bool) preg_match( '/^[a-z][a-z0-9_]*$/', $id ),
		'the id "' . $id . '" is a plain lowercase name that other software can rely on'
	);
}

/* ------------------------- Each template saves and renders */

EstatTests::group( 'Integration: every ready-made form works end to end' );

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

foreach ( $templates as $key => $template ) {
	$form_id = Forms::save(
		array(
			'name'       => (string) $template['label'],
			'definition' => $template['definition'],
			'settings'   => array_merge( Forms::default_settings(), (array) $template['settings'] ),
		)
	);

	EstatTests::ok( ! is_wp_error( $form_id ) && (int) $form_id > 0, $key . ' saves without complaint' );

	$saved = Forms::get( (int) $form_id );
	EstatTests::ok( null !== $saved, $key . ' can be read back' );

	$html = Renderer::render( (int) $form_id );
	EstatTests::ok( strlen( $html ) > 500, $key . ' renders a real form on the website' );

	// The fields the template promised must survive being saved.
	$before = count( Forms::flatten( $template['definition'] ) );
	$after  = count( Forms::flatten( $saved['definition'] ) );
	EstatTests::is( $before, $after, $key . ' keeps all its fields when saved' );

	// Spam protection is not optional, whichever template is used.
	EstatTests::ok( false !== strpos( $html, 'estat_website' ), $key . ' still carries its hidden spam trap' );
	EstatTests::ok( false !== strpos( $html, 'page_url' ), $key . ' still records which page it was sent from' );
}

/* --------------------- Creating a form from a template */

EstatTests::group( 'Integration: picking a ready-made form from the screen' );

$before = count( Forms::all( 100 ) );

$_REQUEST = $_POST = array(
	'estat_action' => 'create_form',
	'estat_nonce'  => wp_create_nonce( 'estat_create_form' ),
	'template'     => 'valuation',
	'name'         => 'Free property valuation',
);

try {
	Actions::handle();
} catch ( Throwable $e ) {
	$e->getMessage();
}

$after = Forms::all( 100 );
EstatTests::is( $before + 1, count( $after ), 'choosing a ready-made form creates exactly one form' );

$created = null;
foreach ( $after as $form ) {
	if ( 'Free property valuation' === $form['name'] ) {
		$created = $form;
	}
}

EstatTests::ok( null !== $created, 'the new form has the name of the template' );

if ( $created ) {
	$fields = Forms::flatten( Forms::get( (int) $created['id'] )['definition'] );
	EstatTests::ok( count( $fields ) >= 4, 'and it arrives already filled with fields, not empty' );
}

// A made-up template must not quietly produce an empty form.
$before = count( Forms::all( 100 ) );

$_REQUEST = $_POST = array(
	'estat_action' => 'create_form',
	'estat_nonce'  => wp_create_nonce( 'estat_create_form' ),
	'template'     => 'does_not_exist',
	'name'         => 'Sneaky',
);

try {
	Actions::handle();
} catch ( Throwable $e ) {
	$e->getMessage();
}

EstatTests::is( $before, count( Forms::all( 100 ) ), 'an unknown template creates nothing at all' );

/* ================================= A palette people can read */

EstatTests::group( 'Integration: the field palette is not a wall of buttons' );

$common = FieldTypes::common();
$all    = FieldTypes::all();

EstatTests::is( 8, count( $common ), 'eight everyday boxes are offered first' );

foreach ( $common as $type ) {
	EstatTests::ok( isset( $all[ $type ] ), 'the everyday box "' . $type . '" really exists' );
}

foreach ( array( 'name', 'phone', 'email', 'message', 'property_type', 'locality', 'budget', 'consent' ) as $expected ) {
	EstatTests::ok( in_array( $expected, $common, true ), '"' . $expected . '" is one of the everyday boxes' );
}

// Nothing may be hidden from the office entirely.
$shown = $common;
foreach ( FieldTypes::groups() as $group ) {
	$shown = array_merge( $shown, (array) $group['types'] );
}

$unreachable = array_diff( array_keys( $all ), $shown );
EstatTests::is( array(), array_values( $unreachable ), 'every field type is reachable somewhere in the palette' );

$ghosts = array_diff( $shown, array_keys( $all ) );
EstatTests::is( array(), array_values( $ghosts ), 'the palette never offers a field type that does not exist' );

/* ------------------------ The smart property fields */

EstatTests::group( 'Integration: the property fields fill themselves in' );

$form_id = (int) Forms::save(
	array(
		'name'       => 'Smart field check',
		'definition' => array(
			'rows' => array(
				array(
					'columns' => array(
						array(
							'width'  => 100,
							'fields' => array(
								Forms::field( 'property_type', 'property_type', 'Property type' ),
								Forms::field( 'locality', 'locality', 'Locality' ),
							),
						),
					),
				),
			),
		),
		'settings'   => Forms::default_settings(),
	)
);

$html = Renderer::render( $form_id );

EstatTests::ok( false !== strpos( $html, 'Apartment' ), 'the property type box offers the office\'s own property types' );
EstatTests::ok( false !== strpos( $html, 'Saket' ), 'the locality box offers the localities already in use' );

/* =============================== The builder screen itself */

EstatTests::group( 'Integration: the builder screen' );

$_GET = array( 'page' => 'estat-forms' );
ob_start();
FormsScreen::render();
$list = (string) ob_get_clean();

EstatTests::ok( false !== strpos( $list, 'estat-template-grid' ), 'the forms screen offers the ready-made forms' );
EstatTests::ok( false !== strpos( $list, 'Start with a ready-made form' ), 'and leads with them rather than an empty box' );
EstatTests::ok( false !== strpos( $list, 'Or start from scratch' ), 'building from scratch is still possible' );

foreach ( $templates as $template ) {
	EstatTests::ok(
		false !== strpos( $list, (string) $template['label'] ),
		'"' . $template['label'] . '" is offered on the screen'
	);
}

$_GET = array( 'page' => 'estat-forms', 'edit' => $form_id );
ob_start();
FormsScreen::render();
$builder = (string) ob_get_clean();

EstatTests::ok( false !== strpos( $builder, 'estat-palette-common' ), 'the builder shows the everyday boxes first' );
EstatTests::ok( false !== strpos( $builder, 'estat-palette-more' ), 'and folds the rest away' );
EstatTests::ok( false !== strpos( $builder, 'estat-palette-search' ), 'the extra boxes can be searched' );
EstatTests::ok( false !== strpos( $builder, 'estat-form-preview' ), 'there is a live preview of what visitors see' );
EstatTests::ok( false !== strpos( $builder, 'estat_nonce' ), 'the builder still carries its security token' );

// Every group heading must appear, or a group would be invisible.
foreach ( FieldTypes::groups() as $group ) {
	EstatTests::ok(
		false !== strpos( $builder, (string) $group['label'] ),
		'the "' . $group['label'] . '" group is on the screen'
	);
}

$js = (string) file_get_contents( ESTAT_DIR . 'assets/js/form-builder.js' );

EstatTests::ok( false !== strpos( $js, 'refreshPreview' ), 'the preview updates as the form is changed' );
// The half-width checkbox became a width strip. The requirement is unchanged:
// there must be a way to make a box half width without typing a percentage.
EstatTests::ok(
	false !== strpos( $js, "[ '100', '50', '33', '25' ]" ),
	'there is a one-tap width strip, so half width needs no percentage typing'
);
EstatTests::ok( false !== strpos( $js, 'estat-seg-item' ), 'the width strip renders as a segmented control' );
EstatTests::ok( false !== strpos( $js, 'renderConditionEditor' ), 'a field can be shown only sometimes' );
EstatTests::ok( false !== strpos( $js, 'control.disabled = true' ), 'nobody can type into the preview by mistake' );

/* ========================= Enquiries from another form tool */

EstatTests::group( 'Integration: enquiries posted in from Elementor' );

$call = static function ( array $params, array $headers = array() ) {
	$request = new WP_REST_Request( 'POST', '/estat/v1/enquiries' );

	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	foreach ( $headers as $key => $value ) {
		$request->set_header( $key, $value );
	}

	return RestApi::create_enquiry( $request );
};

// With no key set the door must be shut, not open to the whole internet.
Settings::update( array( 'inbound_secret' => '' ) );

$result = $call( array( 'name' => 'Anyone', 'phone' => '9876543210' ) );
EstatTests::ok( is_wp_error( $result ), 'with no secret set, nothing can post an enquiry in' );
EstatTests::is( 'estat_inbound_disabled', $result->get_error_code(), 'and it says the door is closed' );

Settings::update( array( 'inbound_secret' => 'super-secret-key' ) );

$result = $call( array( 'secret' => 'wrong-key', 'name' => 'Intruder', 'phone' => '9876543210' ) );
EstatTests::ok( is_wp_error( $result ), 'the wrong secret is refused' );
EstatTests::is( 'estat_inbound_forbidden', $result->get_error_code(), 'and says so plainly' );

$result = $call( array( 'name' => 'Intruder', 'phone' => '9876543210' ) );
EstatTests::ok( is_wp_error( $result ), 'no secret at all is refused' );

$before = (int) Leads::query( array( 'per_page' => 100 ) )['total'];

$result = $call(
	array(
		'secret'  => 'super-secret-key',
		'name'    => 'Priya Sharma',
		'phone'   => '9876543210',
		'message' => 'Interested in the Saket flat',
	)
);

EstatTests::ok( ! is_wp_error( $result ), 'the correct secret is accepted' );
EstatTests::is( 201, $result->get_status(), 'and reports that something was created' );
EstatTests::is( $before + 1, (int) Leads::query( array( 'per_page' => 100 ) )['total'], 'an enquiry really appears in the inbox' );

// Elementor sends the key as a header in some setups.
$result = $call(
	array( 'name' => 'Ravi Kumar', 'phone' => '9000011111' ),
	array( 'x_estat_secret' => 'super-secret-key' )
);
EstatTests::ok( ! is_wp_error( $result ), 'the secret may also travel as a header' );

// An enquiry with no way to reply is useless.
$result = $call( array( 'secret' => 'super-secret-key', 'name' => 'No Contact' ) );
EstatTests::ok( is_wp_error( $result ), 'an enquiry with no phone and no email is refused' );
EstatTests::is( 'estat_inbound_incomplete', $result->get_error_code(), 'and explains what was missing' );

// A tool that retries must not create the enquiry twice.
$first  = $call( array( 'secret' => 'super-secret-key', 'name' => 'Retry', 'phone' => '9111122222', 'idempotency_key' => 'retry-1' ) );
$count  = (int) Leads::query( array( 'per_page' => 100 ) )['total'];
$second = $call( array( 'secret' => 'super-secret-key', 'name' => 'Retry', 'phone' => '9111122222', 'idempotency_key' => 'retry-1' ) );

EstatTests::is( $count, (int) Leads::query( array( 'per_page' => 100 ) )['total'], 'a repeated send does not create a second enquiry' );
EstatTests::is(
	$first->get_data()['lead_id'],
	$second->get_data()['lead_id'],
	'and the sender is told about the same enquiry'
);

// Hostile content must never be stored as-is. A name made only of a script
// tag cleans down to nothing, and an enquiry with no name is refused outright
// — which is the safest possible outcome.
$result = $call(
	array(
		'secret'  => 'super-secret-key',
		'name'    => '<script>alert(1)</script>',
		'phone'   => '9222233333',
		'message' => '<img src=x onerror=alert(1)>',
	)
);

EstatTests::ok( is_wp_error( $result ), 'an enquiry whose name is only a script tag is refused' );

// A real name with hostile content attached must be cleaned and kept.
$result = $call(
	array(
		'secret'  => 'super-secret-key',
		'name'    => 'Priya <script>alert(1)</script> Sharma',
		'phone'   => '9333344444',
		'message' => 'Hello <img src=x onerror=alert(1)> there',
	)
);

EstatTests::ok( ! is_wp_error( $result ), 'a real name with hostile content attached is still accepted' );

$stored = Leads::get( (int) $result->get_data()['lead_id'] );
EstatTests::ok( false === strpos( (string) $stored['name'], '<script' ), 'the hostile part of the name is stripped before storing' );
EstatTests::ok( false === strpos( (string) $stored['name'], 'alert(1)' ), 'and nothing runnable survives in the name' );
EstatTests::ok( false === strpos( (string) $stored['message'], 'onerror' ), 'the hostile message is cleaned too' );
EstatTests::ok( false !== strpos( (string) $stored['name'], 'Priya' ), 'while the real name is kept' );

$_GET     = array();
$_POST    = array();
$_REQUEST = array();
