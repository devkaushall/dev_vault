<?php
/** Runtime security probes in a disposable site. */
$fail = array(); $check = static function ( $n, $v ) use ( &$fail ) { if ( ! $v ) { $fail[] = $n; } };
do_action( 'rest_api_init' );
$subscriber = wp_create_user( 'phase1-sub', 'disposable-only', 'sub@example.test' ); ( new WP_User( $subscriber ) )->set_role( 'subscriber' );
foreach ( array( 0, $subscriber ) as $user ) { wp_set_current_user( $user ); $check( 'rest_denied_' . $user, rest_do_request( new WP_REST_Request( 'GET', '/realestate-platform/v1/status' ) )->get_status() === 403 ); }
wp_set_current_user( $subscriber ); $settings = new \Mayfair\RealEstatePlatform\Settings\SettingsManager(); $check( 'settings_escalation', false === $settings->update( 'operating_mode', 'migration' ) );
wp_set_current_user( 1 ); $bad = new WP_REST_Request( 'POST', '/realestate-platform/v1/status' ); $bad->set_param( 'unexpected', '<script>alert(1)</script>' ); $check( 'unexpected_method', rest_do_request( $bad )->get_status() >= 400 );
$check( 'traversal', is_wp_error( \Mayfair\RealEstatePlatform\Security\Security::safePath( '/safe', '../etc/passwd' ) ) );
$log = new \Mayfair\RealEstatePlatform\Logging\OptionLogger(); $log->log( 'error', 'probe', array( 'password' => 'secret', 'api_key' => 'key', 'token' => 'token', 'email' => 'person@example.test' ), 'security' ); $rows = get_option( 'realestate_platform_log', array() ); $last = end( $rows );
foreach ( array( 'password', 'api_key', 'token', 'email' ) as $key ) { $check( 'redact_' . $key, '[REDACTED]' === ( $last['context'][ $key ] ?? '' ) ); }
$check( 'purge_dual_guard', ! ( defined( 'REALESTATE_PLATFORM_PURGE_DATA' ) && REALESTATE_PLATFORM_PURGE_DATA ) );
echo wp_json_encode( array( 'status' => $fail ? 'FAIL' : 'PASS', 'failures' => $fail ), JSON_PRETTY_PRINT ); if ( $fail ) { exit( 1 ); }
