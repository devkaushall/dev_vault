<?php
/** Data-preserving uninstall by default. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
$advanced = (array) get_option( 'realestate_platform_settings_advanced', array() );
$purge    = defined( 'REALESTATE_PLATFORM_PURGE_DATA' ) && REALESTATE_PLATFORM_PURGE_DATA && ! empty( $advanced['purge_on_uninstall'] );
if ( ! $purge ) {
	return;
}
if ( is_multisite() ) {
	return;
} // Multisite is unsupported in 0.x; never partially purge.
delete_option( 'realestate_platform_settings_general' );
delete_option( 'realestate_platform_settings_performance' );
delete_option( 'realestate_platform_settings_privacy' );
delete_option( 'realestate_platform_settings_advanced' );
delete_option( 'realestate_platform_version' );
delete_option( 'realestate_platform_db_version' );
delete_option( 'realestate_platform_applied_migrations' );
delete_option( 'realestate_platform_log' );

global $wpdb;
/** @var wpdb $wpdb */
foreach ( array( 'rep_lead_assignment_history', 'rep_lead_requests', 'rep_lead_status_history', 'rep_leads', 'rep_notification_events', 'rep_site_visit_history', 'rep_site_visits', 'rep_search_alerts', 'rep_saved_searches', 'rep_favorites', 'rep_search_terms', 'rep_search_properties', 'rep_schema_migrations' ) as $suffix ) {
	$table = $wpdb->prefix . $suffix;
	// The identifier is a fixed plugin-owned suffix joined to WordPress's trusted table prefix.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed plugin table identifier only.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}
