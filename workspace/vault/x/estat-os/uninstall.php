<?php
/**
 * Uninstall routine.
 *
 * Nothing is deleted unless the office explicitly asked for it in
 * Office Settings -> Tools. Silence is treated as "keep my data".
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove every trace of the plugin for one site.
 *
 * @return void
 */
function estat_uninstall_site(): void {
	global $wpdb;

	$settings = get_option( 'estat_settings' );
	if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
		// The office chose to keep everything. Leave the database untouched.
		return;
	}

	// Custom tables.
	$tables = array(
		'estat_leads',
		'estat_visits',
		'estat_forms',
		'estat_submissions',
		'estat_index',
		'estat_audit',
		'estat_webhook_log',
		'estat_import_jobs',
		'estat_requests',
	);
	foreach ( $tables as $table ) {
		$name = $wpdb->prefix . $table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$name}`" );
	}

	/*
	 * Content.
	 *
	 * This list and the taxonomy list below are hand-written rather than read
	 * from the plugin, because uninstall.php runs without the plugin loaded.
	 * That is exactly why they drifted: 'estat_insight' and its two taxonomies
	 * were added later and never added here, so an office that explicitly
	 * asked to erase everything kept every article as an orphaned row.
	 *
	 * A test now compares these lists against PostTypes and Taxonomies, so
	 * the next addition cannot slip through the same gap.
	 */
	$post_types = array( 'estat_listing', 'estat_project', 'estat_agent', 'estat_agency', 'estat_document', 'estat_insight' );
	foreach ( $post_types as $post_type ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", $post_type ) );
		foreach ( (array) $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	// Taxonomies.
	foreach ( array( 'estat_locality', 'estat_feature', 'estat_amenity', 'estat_insight_kind', 'estat_insight_topic' ) as $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( (array) $terms as $term_id ) {
			wp_delete_term( (int) $term_id, $taxonomy );
		}
	}

	// Roles and capabilities.
	remove_role( 'estat_office_owner' );
	remove_role( 'estat_office_agent' );
	$administrator = get_role( 'administrator' );
	if ( $administrator ) {
		foreach ( array_keys( (array) $administrator->capabilities ) as $cap ) {
			if ( 0 === strpos( (string) $cap, 'estat_' ) ) {
				$administrator->remove_cap( (string) $cap );
			}
		}
	}

	// Options and scheduled jobs.
	foreach ( array( 'estat_settings', 'estat_db_version', 'estat_version', 'estat_installed_at', 'estat_index_repair_offset', 'estat_show_setup' ) as $option ) {
		delete_option( $option );
	}
	wp_clear_scheduled_hook( 'estat_daily_maintenance' );
	wp_clear_scheduled_hook( 'estat_hourly_maintenance' );

	// Uploaded import files.
	$uploads = wp_get_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'estat-imports';
	if ( is_dir( $dir ) ) {
		$files = glob( $dir . '/*' );
		foreach ( (array) $files as $file ) {
			if ( is_file( (string) $file ) ) {
				wp_delete_file( (string) $file );
			}
		}
	}
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( (array) $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		estat_uninstall_site();
		restore_current_blog();
	}
} else {
	estat_uninstall_site();
}
