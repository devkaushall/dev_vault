<?php
/** Verify normal uninstall preserved platform and unrelated data. */
global $wpdb;
$table = $wpdb->prefix . 'rep_schema_migrations';
$checks = array(
	'unrelated_option' => get_option( 'rep_unrelated_sentinel' ) === 'keep-me',
	'settings'         => is_array( get_option( 'realestate_platform_settings_general' ) ),
	'ledger'           => $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table,
);
$pass = ! in_array( false, $checks, true );
echo wp_json_encode( array( 'status' => $pass ? 'PASS' : 'FAIL', 'policy' => 'normal uninstall preserves data', 'checks' => $checks ), JSON_PRETTY_PRINT );
if ( ! $pass ) { exit( 1 ); }
