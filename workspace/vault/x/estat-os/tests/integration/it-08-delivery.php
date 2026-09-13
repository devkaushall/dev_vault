<?php
/**
 * Notifications and webhook delivery, end to end.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Forms\Forms;
use EstatOS\Forms\Submissions;
use EstatOS\Leads\Leads;
use EstatOS\Settings\Settings;
use EstatOS\Webhooks\Webhooks;

estat_reset_world();
estat_login_owner();

update_option(
	'estat_settings',
	array_merge(
		(array) get_option( 'estat_settings', array() ),
		array(
			'webhook_enabled' => true,
			'webhook_url'     => 'https://hooks.example.test/estat',
			'webhook_secret'  => 'integration-secret',
			'webhook_events'  => array( 'lead.created', 'visit.scheduled' ),
			'notify_email'    => 'office@example.test',
			'notify_new_lead' => true,
			'notify_new_visit'=> true,
		)
	)
);
Settings::flush();

/* ----------------------------------------------------------- Notification */

EstatTests::group( 'Integration: a new enquiry reaches the office inbox' );

$GLOBALS['estat_mail'] = array();
$GLOBALS['estat_http'] = array();

$lead_id = (int) Leads::create(
	array(
		'name'    => 'Notify Me',
		'phone'   => '+91 90000 31337',
		'message' => 'Please call about the corner flat.',
	)
);

EstatTests::is( 1, count( $GLOBALS['estat_mail'] ), 'exactly one notification email is sent' );

$mail = $GLOBALS['estat_mail'][0] ?? array();
EstatTests::is( 'office@example.test', (string) ( $mail['to'] ?? '' ), 'it goes to the configured office address' );
EstatTests::ok( false !== strpos( (string) ( $mail['subject'] ?? '' ), 'New enquiry' ), 'the subject says a new enquiry arrived' );
EstatTests::ok( false !== strpos( (string) ( $mail['message'] ?? '' ), 'Notify Me' ), 'the body names the person' );
EstatTests::ok( false !== strpos( (string) ( $mail['message'] ?? '' ), '90000 31337' ), 'the body carries the phone number' );

/* -------------------------------------------------------- Webhook delivery */

EstatTests::group( 'Integration: the enquiry is delivered to the webhook' );

EstatTests::is( 1, count( $GLOBALS['estat_http'] ), 'exactly one webhook call is made' );

$call    = $GLOBALS['estat_http'][0] ?? array();
$headers = (array) ( $call['args']['headers'] ?? array() );

EstatTests::is( 'https://hooks.example.test/estat', (string) ( $call['url'] ?? '' ), 'it posts to the configured URL' );
EstatTests::is( 'lead.created', (string) ( $headers['X-Estat-Event'] ?? '' ), 'the event header names the event' );
EstatTests::ok( ! empty( $headers['X-Estat-Timestamp'] ), 'a timestamp header is sent so replays can be rejected' );

$signature = (string) ( $headers['X-Estat-Signature'] ?? '' );
EstatTests::ok( 0 === strpos( $signature, 'sha256=' ), 'the signature header is prefixed with the algorithm' );

// Verify the signature exactly as a receiving integrator would.
$body      = (string) ( $call['args']['body'] ?? '' );
$timestamp = (string) ( $headers['X-Estat-Timestamp'] ?? '' );
$hex       = substr( $signature, strlen( 'sha256=' ) );

EstatTests::ok(
	Webhooks::verify( $timestamp . '.' . $body, 'integration-secret', $hex ),
	'the signature an integrator receives actually verifies against the documented recipe'
);
EstatTests::ok(
	! Webhooks::verify( $timestamp . '.' . $body, 'the-wrong-secret', $hex ),
	'and it fails against the wrong secret'
);

$payload = json_decode( $body, true );
EstatTests::ok( is_array( $payload ), 'the body is valid JSON' );
EstatTests::ok( ! empty( $payload['event'] ), 'the payload names the event' );

/* ----------------------------------------------------- No double sending */

EstatTests::group( 'Integration: a form submission does not email twice' );

Forms::seed();
$all     = Forms::all();
$form_id = (int) $all[0]['id'];
$form    = Forms::get( $form_id );
$fields  = Forms::flatten( $form['definition'] );

$payload = array();
foreach ( $fields as $f ) {
	switch ( $f['type'] ) {
		case 'phone':
		case 'tel':
			$payload[ $f['id'] ] = '+91 98111 44555';
			break;
		case 'email':
			$payload[ $f['id'] ] = 'formbuyer@example.test';
			break;
		case 'message':
		case 'textarea':
			$payload[ $f['id'] ] = 'Interested, please call.';
			break;
		case 'consent':
			$payload[ $f['id'] ] = '1';
			break;
		case 'name':
		case 'text':
			$payload[ $f['id'] ] = 'Form Buyer';
			break;
	}
}

$GLOBALS['estat_mail'] = array();
$GLOBALS['estat_http'] = array();

$result = Submissions::handle( $form_id, array( 'fields' => $payload, 'idempotency_key' => 'delivery-001' ) );
EstatTests::ok( ! is_wp_error( $result ), 'the submission succeeds' );

EstatTests::ok(
	count( $GLOBALS['estat_mail'] ) <= 1,
	'a form enquiry produces at most one email, not one from the form and another from the hook (got ' . count( $GLOBALS['estat_mail'] ) . ')'
);
EstatTests::is( 1, count( $GLOBALS['estat_http'] ), 'and exactly one webhook call' );

/* --------------------------------------------------------- Opt-out honoured */

EstatTests::group( 'Integration: switching notifications off is respected' );

update_option(
	'estat_settings',
	array_merge( (array) get_option( 'estat_settings', array() ), array( 'notify_new_lead' => false, 'webhook_enabled' => false ) )
);
Settings::flush();

$GLOBALS['estat_mail'] = array();
$GLOBALS['estat_http'] = array();

Leads::create( array( 'name' => 'Quiet Please', 'phone' => '+91 90000 22222' ) );

EstatTests::is( 0, count( $GLOBALS['estat_mail'] ), 'no email is sent once notifications are switched off' );
EstatTests::is( 0, count( $GLOBALS['estat_http'] ), 'no webhook is sent once the webhook is switched off' );

/* ------------------------------------------------------ Webhook log */

EstatTests::group( 'Integration: deliveries are logged' );

global $wpdb;
$logged = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . EstatOS\Install\Schema::table( 'estat_webhook_log' ) );
EstatTests::ok( $logged > 0, 'webhook attempts are recorded so failures can be inspected' );
