<?php
/** Phase 5 user-owned favorites, saved searches, and alerts. @package RealEstatePlatform */
declare(strict_types=1);
use Mayfair\RealEstatePlatform\Contracts\DatabaseInterface;
use Mayfair\RealEstatePlatform\Contracts\MigrationInterface;
return new class() implements MigrationInterface {
	public function id(): string {
		return '003'; }
	public function up( DatabaseInterface $database ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$prefix  = $database->prefix();
		dbDelta( "CREATE TABLE {$prefix}rep_favorites (user_id bigint(20) unsigned NOT NULL,post_id bigint(20) unsigned NOT NULL,created_at datetime NOT NULL,PRIMARY KEY (user_id,post_id),KEY property_lookup (post_id),KEY created_lookup (user_id,created_at)) {$charset};" );
		dbDelta( "CREATE TABLE {$prefix}rep_saved_searches (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,user_id bigint(20) unsigned NOT NULL,title varchar(120) NOT NULL,criteria_json longtext NOT NULL,criteria_hash char(64) NOT NULL,enabled tinyint(1) NOT NULL DEFAULT 1,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY (id),UNIQUE KEY owner_hash (user_id,criteria_hash),KEY owner_updated (user_id,updated_at)) {$charset};" );
		dbDelta( "CREATE TABLE {$prefix}rep_search_alerts (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,saved_search_id bigint(20) unsigned NOT NULL,user_id bigint(20) unsigned NOT NULL,frequency varchar(16) NOT NULL,enabled tinyint(1) NOT NULL DEFAULT 0,last_run_at datetime NULL,next_run_at datetime NULL,notified_json longtext NOT NULL,failure_count int unsigned NOT NULL DEFAULT 0,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY (id),UNIQUE KEY saved_search (saved_search_id),KEY due_alerts (enabled,next_run_at),KEY owner_lookup (user_id)) {$charset};" );
		if ( '' !== $database->lastError() ) {
			throw new RuntimeException( 'Could not create Phase 5 user-state tables.' );
		}
		$history = $prefix . 'rep_schema_migrations';
		$sql     = $database->prepare( "INSERT IGNORE INTO {$history} (migration_id,checksum,applied_at) VALUES (%s,%s,%s)", '003', hash_file( 'sha256', __FILE__ ), gmdate( 'Y-m-d H:i:s' ) );
		if ( false === $database->query( $sql ) ) {
			throw new RuntimeException( 'Could not record Phase 5 migration checksum.' );
		}
	}
};
