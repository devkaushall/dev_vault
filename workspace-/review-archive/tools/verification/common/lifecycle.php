<?php
/** Disposable WP-CLI lifecycle assertion script. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 2 ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$fail = array();
$check = static function ( string $name, bool $ok, string $detail = '' ) use ( &$fail ): void {
	if ( ! $ok ) { $fail[] = array( 'check' => $name, 'detail' => $detail ); }
};
$plugin = 'realestate-platform/realestate-platform.php';
$check( 'active', is_plugin_active( $plugin ) );
global $wpdb;
$table = $wpdb->prefix . 'rep_schema_migrations';
$check( 'table_exists', $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );
$row = $wpdb->get_row( "SELECT migration_id, checksum FROM `{$table}` WHERE migration_id = '001'", ARRAY_A );
$check( 'migration_001', is_array( $row ) );
$check( 'checksum', is_array( $row ) && hash_equals( hash_file( 'sha256', WP_PLUGIN_DIR . '/realestate-platform/migrations/001_initial.php' ), (string) $row['checksum'] ) );
$check( 'schema_version', get_option( 'realestate_platform_db_version' ) === '001' );
$check( 'settings', is_array( get_option( 'realestate_platform_settings_general' ) ) );
$admin = get_role( 'administrator' );
foreach ( \Mayfair\RealEstatePlatform\Capabilities\CapabilityManager::CAPS as $cap ) { $check( 'cap_' . $cap, $admin instanceof WP_Role && $admin->has_cap( $cap ) ); }
$diagnostics = \Mayfair\RealEstatePlatform\Core\Bootstrap::instance()->services()->get( 'diagnostics' );
$check( 'diagnostics', $diagnostics instanceof \Mayfair\RealEstatePlatform\Diagnostics\DiagnosticsRunner && count( $diagnostics->run() ) >= 10 );
do_action( 'rest_api_init' );
wp_set_current_user( 0 );
$check( 'rest_unauthorized', rest_do_request( new WP_REST_Request( 'GET', '/realestate-platform/v1/status' ) )->get_status() === 403 );
wp_set_current_user( 1 );
$check( 'rest_authorized', rest_do_request( new WP_REST_Request( 'GET', '/realestate-platform/v1/status' ) )->get_status() === 200 );
$count_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
deactivate_plugins( $plugin );
$check( 'deactivate_preserves', (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) === $count_before );
$result = activate_plugin( $plugin );
$check( 'reactivate', ! is_wp_error( $result ) );
$result = activate_plugin( $plugin );
$check( 'repeat_activation', ! is_wp_error( $result ) );
$check( 'no_duplicate', (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) === $count_before );
echo wp_json_encode( array( 'status' => $fail ? 'FAIL' : 'PASS', 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'database' => $wpdb->db_version(), 'plugin' => REALESTATE_PLATFORM_VERSION, 'failures' => $fail ), JSON_PRETTY_PRINT );
if ( $fail ) { exit( 1 ); }
