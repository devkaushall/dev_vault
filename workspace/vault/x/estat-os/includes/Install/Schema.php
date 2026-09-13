<?php
/**
 * Database tables owned by the plugin.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Operational records live in dedicated, indexed tables so the plugin stays
 * fast on shared hosting with roughly ten thousand listings.
 *
 * Table names are part of the public data contract.
 */
final class Schema {

	/**
	 * Object cache key for the table health answer.
	 *
	 * @var string
	 */
	const HEALTH_KEY = 'estat_schema_healthy';

	/**
	 * Per-request memo of the table health answer.
	 *
	 * @var bool|null
	 */
	private static $healthy = null;

	/**
	 * Table base names.
	 *
	 * @return string[]
	 */
	public static function tables(): array {
		return array( 'estat_leads', 'estat_visits', 'estat_forms', 'estat_submissions', 'estat_index', 'estat_audit', 'estat_webhook_log', 'estat_import_jobs', 'estat_requests' );
	}

	/**
	 * Fully qualified table name.
	 *
	 * @param string $name Base name without prefix.
	 * @return string
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	/**
	 * Create or update all tables. dbDelta is idempotent.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		$sql = array();

		$sql[] = "CREATE TABLE {$p}estat_leads (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			name VARCHAR(191) NOT NULL DEFAULT '',
			phone VARCHAR(32) NOT NULL DEFAULT '',
			email VARCHAR(191) NOT NULL DEFAULT '',
			message TEXT NULL,
			source VARCHAR(64) NOT NULL DEFAULT 'website',
			submission_source VARCHAR(191) NOT NULL DEFAULT '',
			listing_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			agent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(32) NOT NULL DEFAULT 'new',
			notes LONGTEXT NULL,
			followup_at DATETIME NULL,
			consent TINYINT(1) NOT NULL DEFAULT 0,
			practice TINYINT(1) NOT NULL DEFAULT 0,
			erased TINYINT(1) NOT NULL DEFAULT 0,
			dedupe_hash CHAR(64) NOT NULL DEFAULT '',
			idempotency_key CHAR(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY dedupe_hash (dedupe_hash),
			KEY status_created (status, created_at),
			KEY agent_id (agent_id),
			KEY listing_id (listing_id),
			KEY followup_at (followup_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estat_visits (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			lead_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			listing_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			agent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			scheduled_at DATETIME NOT NULL,
			outcome VARCHAR(32) NOT NULL DEFAULT 'pending',
			notes TEXT NULL,
			reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
			practice TINYINT(1) NOT NULL DEFAULT 0,
			dedupe_hash CHAR(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY dedupe_hash (dedupe_hash),
			KEY scheduled_at (scheduled_at),
			KEY lead_id (lead_id),
			KEY agent_id (agent_id),
			KEY outcome (outcome)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estat_forms (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			name VARCHAR(191) NOT NULL DEFAULT '',
			slug VARCHAR(191) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			definition LONGTEXT NULL,
			settings LONGTEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estat_submissions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			lead_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			payload LONGTEXT NULL,
			page_url VARCHAR(255) NOT NULL DEFAULT '',
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			idempotency_key CHAR(64) NOT NULL DEFAULT '',
			practice TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY form_created (form_id, created_at),
			KEY lead_id (lead_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estat_index (
			listing_id BIGINT UNSIGNED NOT NULL,
			post_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			title VARCHAR(255) NOT NULL DEFAULT '',
			search_text TEXT NULL,
			offer VARCHAR(20) NOT NULL DEFAULT '',
			property_type VARCHAR(32) NOT NULL DEFAULT '',
			availability VARCHAR(20) NOT NULL DEFAULT '',
			construction VARCHAR(20) NOT NULL DEFAULT '',
			furnishing VARCHAR(20) NOT NULL DEFAULT '',
			price DECIMAL(18,2) NOT NULL DEFAULT 0,
			rent DECIMAL(18,2) NOT NULL DEFAULT 0,
			price_sort DECIMAL(18,2) NOT NULL DEFAULT 0,
			area_sqft DECIMAL(14,2) NOT NULL DEFAULT 0,
			bedrooms SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			bathrooms SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			locality_ids VARCHAR(191) NOT NULL DEFAULT '',
			project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			agent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			featured TINYINT(1) NOT NULL DEFAULT 0,
			investment TINYINT(1) NOT NULL DEFAULT 0,
			practice TINYINT(1) NOT NULL DEFAULT 0,
			completeness SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			latitude DECIMAL(10,7) NULL,
			longitude DECIMAL(10,7) NULL,
			published_at DATETIME NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (listing_id),
			KEY status_offer (post_status, offer),
			KEY price_sort (price_sort),
			KEY area_sqft (area_sqft),
			KEY bedrooms (bedrooms),
			KEY property_type (property_type),
			KEY project_id (project_id),
			KEY agent_id (agent_id),
			KEY featured (featured),
			KEY published_at (published_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estat_audit (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(64) NOT NULL DEFAULT '',
			object_type VARCHAR(32) NOT NULL DEFAULT '',
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			context LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY action_created (action, created_at),
			KEY object (object_type, object_id),
			KEY created_at (created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estat_webhook_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			event VARCHAR(64) NOT NULL DEFAULT '',
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			status_code SMALLINT NOT NULL DEFAULT 0,
			success TINYINT(1) NOT NULL DEFAULT 0,
			attempt SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			error VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY event_created (event, created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estat_import_jobs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			kind VARCHAR(32) NOT NULL DEFAULT 'listings',
			file_path VARCHAR(255) NOT NULL DEFAULT '',
			mapping LONGTEXT NULL,
			state VARCHAR(20) NOT NULL DEFAULT 'pending',
			total_rows INT UNSIGNED NOT NULL DEFAULT 0,
			processed_rows INT UNSIGNED NOT NULL DEFAULT 0,
			created_count INT UNSIGNED NOT NULL DEFAULT 0,
			updated_count INT UNSIGNED NOT NULL DEFAULT 0,
			skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
			errors LONGTEXT NULL,
			file_hash CHAR(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY state (state),
			KEY file_hash (file_hash)
		) {$charset};";

		/*
		 * Changes an agent has proposed to a record that is not theirs.
		 *
		 * Only the fields being changed are stored, as JSON, never a copy of
		 * the whole record. Two requests against the same property can then
		 * be approved independently without one silently reverting the
		 * other's untouched fields.
		 */
		$sql[] = "CREATE TABLE {$p}estat_requests (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			record_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			record_type VARCHAR(32) NOT NULL DEFAULT '',
			requested_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			reviewed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			changes LONGTEXT NULL,
			message TEXT NULL,
			reason TEXT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY record_id (record_id),
			KEY requested_by (requested_by)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		self::flush_health();
	}


	/**
	 * Whether all expected tables exist.
	 *
	 * @return bool
	 */
	public static function healthy(): bool {
		if ( null !== self::$healthy ) {
			return self::$healthy;
		}

		// This is consulted on nearly every read path, so the answer is cached
		// for the request and in the object cache. Without this a single
		// listings page would issue one SHOW TABLES per table per call.
		$cached = wp_cache_get( self::HEALTH_KEY, 'estat' );
		if ( is_string( $cached ) && '' !== $cached ) {
			self::$healthy = ( 'yes' === $cached );
			return self::$healthy;
		}

		global $wpdb;
		$healthy = true;
		foreach ( self::tables() as $name ) {
			$table = $wpdb->prefix . $name;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $found !== $table ) {
				$healthy = false;
				break;
			}
		}

		self::$healthy = $healthy;
		wp_cache_set( self::HEALTH_KEY, $healthy ? 'yes' : 'no', 'estat', HOUR_IN_SECONDS );
		return $healthy;
	}

	/**
	 * Forget the cached health answer.
	 *
	 * Call this after creating or dropping tables so the next check looks again.
	 *
	 * @return void
	 */
	public static function flush_health(): void {
		self::$healthy = null;
		wp_cache_delete( self::HEALTH_KEY, 'estat' );
	}
}
