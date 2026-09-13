<?php
/**
 * Webhook signing.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Webhooks\Webhooks;

EstatTests::group( 'Webhooks: signatures' );

$secret    = 'a-shared-secret';
$timestamp = '1700000000';
$body      = '{"event":"lead.created"}';
$message   = $timestamp . '.' . $body;

$signature = Webhooks::sign( $message, $secret );

EstatTests::is(
	hash_hmac( 'sha256', $message, $secret ),
	$signature,
	'sign() returns the raw HMAC over timestamp, a dot, then the raw body'
);
EstatTests::is(
	'sha256=' . $signature,
	'sha256=' . hash_hmac( 'sha256', $message, $secret ),
	'the X-Estat-Signature header is that digest prefixed with the algorithm'
);
EstatTests::ok( Webhooks::verify( $message, $secret, $signature ), 'a genuine signature verifies' );
EstatTests::ok( ! Webhooks::verify( $message, $secret, 'sha256=deadbeef' ), 'a forged signature is rejected' );
EstatTests::ok( ! Webhooks::verify( $message, 'wrong-secret', $signature ), 'the wrong secret is rejected' );
EstatTests::ok( ! Webhooks::verify( $message . 'tampered', $secret, $signature ), 'a tampered body is rejected' );

EstatTests::group( 'Webhooks: the event catalogue' );

$events = Webhooks::events();
foreach ( array( 'lead.created', 'lead.updated', 'visit.scheduled', 'visit.completed', 'listing.published' ) as $event ) {
	EstatTests::ok( isset( $events[ $event ] ), 'the documented event "' . $event . '" exists' );
}
