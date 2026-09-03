<?php
/** Disposable uninstall policy assertion. TEST_CASE selects preserve|partial|purge|multisite. */
$case = getenv( 'TEST_CASE' ) ?: 'preserve';
global $wpdb;
$table = $wpdb->prefix . 'rep_schema_migrations';
update_option( 'rep_unrelated_sentinel', 'keep-me' );
$advanced = (array) get_option( 'realestate_platform_settings_advanced', array() );
$advanced['purge_on_uninstall'] = in_array( $case, array( 'partial', 'purge', 'multisite' ), true );
update_option( 'realestate_platform_settings_advanced', $advanced );
if ( 'purge' === $case || 'multisite' === $case ) { define( 'REALESTATE_PLATFORM_PURGE_DATA', true ); }
define( 'WP_UNINSTALL_PLUGIN', true );
require WP_PLUGIN_DIR . '/realestate-platform/uninstall.php';
$option_exists = false !== get_option( 'realestate_platform_settings_advanced', false );
$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
$unrelated = get_option( 'rep_unrelated_sentinel' ) === 'keep-me';
$should_purge = 'purge' === $case && ! is_multisite();
$pass = $unrelated && ( $should_purge ? ( ! $option_exists && ! $table_exists ) : ( $option_exists && $table_exists ) );
echo wp_json_encode( array( 'status' => $pass ? 'PASS' : 'FAIL', 'case' => $case, 'multisite' => is_multisite(), 'platform_option_exists' => $option_exists, 'platform_table_exists' => $table_exists, 'unrelated_preserved' => $unrelated ), JSON_PRETTY_PRINT );
if ( ! $pass ) { exit( 1 ); }
