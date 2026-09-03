<?php
/** Phase 7 lead, request, site-visit, and notification outbox tables. @package RealEstatePlatform */
declare(strict_types=1);

use Mayfair\RealEstatePlatform\Contracts\DatabaseInterface;
use Mayfair\RealEstatePlatform\Contracts\MigrationInterface;

return new class() implements MigrationInterface {
	public function id(): string {
		return '004';
	}

	public function up( DatabaseInterface $database ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$prefix  = $database->prefix();

		dbDelta(
			"CREATE TABLE {$prefix}rep_leads (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(190) NOT NULL,
			email varchar(190) NOT NULL,
			phone varchar(64) NULL,
			source varchar(64) NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'new',
			property_id bigint(20) unsigned NULL,
			project_id bigint(20) unsigned NULL,
			agent_id bigint(20) unsigned NULL,
			agency_id bigint(20) unsigned NULL,
			consent_granted tinyint(1) NOT NULL DEFAULT 0,
			consent_at datetime NULL,
			ip_hash char(64) NULL,
			user_agent_hash char(64) NULL,
			dedupe_key char(64) NULL,
			privacy_erased_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY dedupe_key (dedupe_key),
			KEY owner_lookup (user_id,created_at),
			KEY status_lookup (status,updated_at),
			KEY property_lookup (property_id),
			KEY project_lookup (project_id),
			KEY assignment_lookup (agent_id,agency_id)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$prefix}rep_lead_requests (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL,
			request_type varchar(32) NOT NULL DEFAULT 'inquiry',
			message text NOT NULL,
			metadata_json longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_lookup (lead_id,created_at),
			KEY type_lookup (request_type,created_at)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$prefix}rep_lead_status_history (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL,
			from_status varchar(32) NULL,
			to_status varchar(32) NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			note text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_history (lead_id,created_at),
			KEY status_history (to_status,created_at)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$prefix}rep_lead_assignment_history (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL,
			from_agent_id bigint(20) unsigned NULL,
			to_agent_id bigint(20) unsigned NULL,
			from_agency_id bigint(20) unsigned NULL,
			to_agency_id bigint(20) unsigned NULL,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_assignment_history (lead_id,created_at)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$prefix}rep_site_visits (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL,
			property_id bigint(20) unsigned NULL,
			requester_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			agent_id bigint(20) unsigned NULL,
			agency_id bigint(20) unsigned NULL,
			status varchar(32) NOT NULL DEFAULT 'requested',
			requested_start_at datetime NOT NULL,
			requested_end_at datetime NOT NULL,
			scheduled_start_at datetime NULL,
			scheduled_end_at datetime NULL,
			cancellation_reason text NOT NULL,
			dedupe_key char(64) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_lookup (lead_id,created_at),
			KEY property_lookup (property_id,requested_start_at),
			KEY status_lookup (status,requested_start_at),
			UNIQUE KEY visit_dedupe (dedupe_key),
			KEY assignment_lookup (agent_id,agency_id)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$prefix}rep_site_visit_history (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			visit_id bigint(20) unsigned NOT NULL,
			from_status varchar(32) NULL,
			to_status varchar(32) NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			note text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY visit_history (visit_id,created_at)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$prefix}rep_notification_events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(64) NOT NULL,
			aggregate_type varchar(32) NOT NULL,
			aggregate_id bigint(20) unsigned NOT NULL,
			recipient_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			recipient_email varchar(190) NOT NULL DEFAULT '',
			payload_json longtext NOT NULL,
			dedupe_key varchar(191) NOT NULL,
			status varchar(16) NOT NULL DEFAULT 'pending',
			attempts int unsigned NOT NULL DEFAULT 0,
			next_attempt_at datetime NULL,
			last_error text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			sent_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY dedupe_lookup (dedupe_key),
			KEY dispatch_lookup (status,next_attempt_at),
			KEY aggregate_lookup (aggregate_type,aggregate_id)
		) {$charset};"
		);
		if ( '' !== $database->lastError() ) {
			throw new RuntimeException( 'Could not create Phase 7 workflow tables.' );
		}
		$history = $prefix . 'rep_schema_migrations';
		$sql     = $database->prepare(
			"INSERT IGNORE INTO {$history} (migration_id,checksum,applied_at) VALUES (%s,%s,%s)",
			'004',
			hash_file( 'sha256', __FILE__ ),
			gmdate( 'Y-m-d H:i:s' )
		);
		if ( false === $database->query( $sql ) ) {
			throw new RuntimeException( 'Could not record Phase 7 migration checksum.' );
		}
	}
};
