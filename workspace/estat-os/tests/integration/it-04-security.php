<?php
/**
 * REST routes, capabilities, nonces, rate limiting and webhook signing.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Webhooks\Webhooks;

estat_reset_world();
estat_login_owner();

/**
 * Dispatch a registered route the way the REST server would: check the
 * permission callback first, then run the handler.
 *
 * @param string              $method Method.
 * @param string              $route  Route as registered.
 * @param array<string,mixed> $params Parameters.
 * @return array{allowed:bool,result:mixed}
 */
function estat_dispatch( string $method, string $route, array $params = array() ): array {
	$args = $GLOBALS['estat_rest_routes'][ 'estat/v1' . $route ] ?? null;
	if ( null === $args ) {
		return array( 'allowed' => false, 'result' => new WP_Error( 'no_route', 'Route not registered: ' . $route ) );
	}

	// Routes register either a single endpoint or a list of them.
	$endpoints = isset( $args['methods'] ) ? array( $args ) : $args;

	foreach ( $endpoints as $endpoint ) {
		if ( ! is_array( $endpoint ) || ! isset( $endpoint['methods'] ) ) {
			continue;
		}
		$methods = array_map( 'strtoupper', array_map( 'trim', explode( ',', (string) $endpoint['methods'] ) ) );
		if ( ! in_array( strtoupper( $method ), $methods, true ) ) {
			continue;
		}

		$request = new WP_REST_Request( strtoupper( $method ), '/estat/v1' . $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$permission = $endpoint['permission_callback'] ?? '__return_true';
		if ( ! call_user_func( $permission, $request ) ) {
			return array( 'allowed' => false, 'result' => null );
		}
		return array( 'allowed' => true, 'result' => call_user_func( $endpoint['callback'], $request ) );
	}
	return array( 'allowed' => false, 'result' => new WP_Error( 'no_method', 'Method not allowed' ) );
}

/* --------------------------------------------------------- Registration */

EstatTests::group( 'Integration: REST routes are registered' );

$expected = array(
	'/properties',
	'/properties/(?P<id>\d+)',
	'/projects/(?P<id>\d+)/stats',
	'/agents',
	'/forms/(?P<id>\d+)/submit',
	'/leads',
	'/leads/(?P<id>\d+)',
	'/visits',
	'/visits/(?P<id>\d+)',
	'/audit',
	'/maintenance/(?P<task>[a-z_\-]+)',
);
foreach ( $expected as $route ) {
	EstatTests::ok(
		isset( $GLOBALS['estat_rest_routes'][ 'estat/v1' . $route ] ),
		'the documented route ' . $route . ' is registered'
	);
}

/* ------------------------------------------------------ Public read-only */

EstatTests::group( 'Integration: public endpoints are readable by visitors' );

$GLOBALS['estat_current_user'] = 0;
$GLOBALS['estat_caps'][0]      = array();

$public = estat_dispatch( 'GET', '/properties' );
EstatTests::ok( $public['allowed'], 'a logged-out visitor can browse properties' );

$agents = estat_dispatch( 'GET', '/agents' );
EstatTests::ok( $agents['allowed'], 'a logged-out visitor can see the team' );

/* ------------------------------------------------- Private routes gated */

EstatTests::group( 'Integration: private endpoints reject a logged-out visitor' );

foreach (
	array(
		array( 'GET', '/leads' ),
		array( 'POST', '/leads' ),
		array( 'PATCH', '/leads/(?P<id>\d+)' ),
		array( 'GET', '/visits' ),
		array( 'POST', '/visits' ),
		array( 'PATCH', '/visits/(?P<id>\d+)' ),
		array( 'GET', '/audit' ),
		array( 'POST', '/maintenance/(?P<task>[a-z_\-]+)' ),
	) as $pair
) {
	list( $method, $route ) = $pair;
	$res = estat_dispatch( $method, $route );
	EstatTests::ok( ! $res['allowed'], $method . ' ' . $route . ' is refused without permission' );
}

/* ------------------------------------------------ Least privilege checks */

EstatTests::group( 'Integration: one permission does not unlock another' );

estat_login_with( 7, array( 'estat_manage_leads' ) );

EstatTests::ok( estat_dispatch( 'GET', '/leads' )['allowed'], 'the enquiries permission opens the enquiries endpoint' );
EstatTests::ok( ! estat_dispatch( 'GET', '/audit' )['allowed'], 'the enquiries permission does not open the audit log' );
EstatTests::ok( ! estat_dispatch( 'GET', '/visits' )['allowed'], 'the enquiries permission does not open visits' );
EstatTests::ok( ! estat_dispatch( 'POST', '/maintenance/(?P<task>[a-z_\-]+)' )['allowed'], 'the enquiries permission does not open maintenance' );

estat_login_with( 8, array( 'estat_view_audit' ) );
EstatTests::ok( estat_dispatch( 'GET', '/audit' )['allowed'], 'the audit permission opens the audit log' );
EstatTests::ok( ! estat_dispatch( 'POST', '/leads' )['allowed'], 'the audit permission does not allow creating enquiries' );

estat_login_owner();

/* -------------------------------------------------------------- Roles */

EstatTests::group( 'Integration: roles ship with sensible permissions' );

$owner = $GLOBALS['estat_roles']['estat_office_owner']['capabilities'] ?? null;
$agent = $GLOBALS['estat_roles']['estat_office_agent']['capabilities'] ?? null;

EstatTests::ok( is_array( $owner ), 'the office owner role exists' );
EstatTests::ok( is_array( $agent ), 'the agent role exists' );

if ( is_array( $owner ) && is_array( $agent ) ) {
	EstatTests::ok( ! empty( $owner['estat_manage_settings'] ), 'the owner can change office settings' );
	EstatTests::ok( ! empty( $owner['estat_view_audit'] ), 'the owner can read the audit log' );
	EstatTests::ok( ! empty( $agent['estat_manage_leads'] ), 'an agent can work enquiries' );
	EstatTests::ok( empty( $agent['estat_manage_settings'] ), 'an agent cannot change office settings' );
	EstatTests::ok( empty( $agent['estat_run_maintenance'] ), 'an agent cannot run maintenance tools' );
	EstatTests::ok( empty( $agent['estat_delete_listings'] ), 'an agent cannot delete listings' );
}

/* ------------------------------------------------------- Webhook signing */

EstatTests::group( 'Integration: webhook signatures' );

$secret    = 'a-very-secret-value';
$timestamp = (string) time();
$body      = wp_json_encode( array( 'event' => 'lead.created', 'id' => 42 ) );
$message   = $timestamp . '.' . $body;
$signature = Webhooks::sign( $message, $secret );

EstatTests::ok( Webhooks::verify( $message, $secret, $signature ), 'a genuine signature verifies' );
EstatTests::ok( ! Webhooks::verify( $message, 'wrong-secret', $signature ), 'the wrong secret fails' );
EstatTests::ok( ! Webhooks::verify( $timestamp . '.' . '{"event":"tampered"}', $secret, $signature ), 'a tampered body fails' );
EstatTests::ok( ! Webhooks::verify( $message, $secret, 'deadbeef' ), 'a forged signature fails' );

/* --------------------------------------------------------- SQL injection */

EstatTests::group( 'Integration: hostile input cannot break out of a query' );

$evil = "'; DROP TABLE wp_estat_leads; --";

$lead = EstatOS\Leads\Leads::create(
	array(
		'name'    => $evil,
		'phone'   => '+91 90000 12345',
		'message' => $evil,
	)
);
EstatTests::ok( ! is_wp_error( $lead ), 'a hostile string is accepted as ordinary text' );
EstatTests::ok(
	$GLOBALS['wpdb']->has_table( $GLOBALS['wpdb']->prefix . 'estat_leads' ),
	'the enquiries table still exists after a SQL injection attempt'
);

$found = EstatOS\Leads\Leads::query( array( 'search' => $evil ) );
EstatTests::ok( is_array( $found ), 'searching for a hostile string returns a normal result set' );
EstatTests::ok(
	$GLOBALS['wpdb']->has_table( $GLOBALS['wpdb']->prefix . 'estat_leads' ),
	'the table survives a hostile search term too'
);

/* ------------------------------------------------------ Prepared queries */

EstatTests::group( 'Integration: queries are prepared correctly' );

// The harness $wpdb->prepare() throws when placeholders and arguments do not
// match, so simply exercising the read paths proves they are well formed.
$ok = true;
try {
	EstatOS\Leads\Leads::query( array( 'status' => 'new', 'per_page' => 10, 'page' => 1 ) );
	EstatOS\Leads\Leads::counts();
	EstatOS\Leads\Visits::query( array( 'per_page' => 10 ) );
	EstatOS\Leads\Visits::counts();
	EstatOS\Audit\AuditLog::query( array( 'per_page' => 10 ) );
	EstatOS\Search\SearchIndex::status();
	EstatOS\Forms\Forms::all();
} catch ( RuntimeException $e ) {
	$ok = false;
	EstatTests::ok( false, 'a query had mismatched placeholders: ' . $e->getMessage() );
}
if ( $ok ) {
	EstatTests::ok( true, 'every read path prepares its SQL with matching placeholders' );
}
