<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
use Mayfair\RealEstatePlatform\Contracts\{DatabaseInterface, MigrationInterface};
return new class() implements MigrationInterface { public function id(): string {
		return '001';
} public function up( DatabaseInterface $database ): void {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = $database->prefix() . 'rep_schema_migrations';
	$charset = '';
	global $wpdb;
	/** @var \wpdb $wpdb */
	if ( isset( $wpdb ) && method_exists( $wpdb, 'get_charset_collate' ) ) {
		$charset = $wpdb->get_charset_collate();
	} dbDelta( "CREATE TABLE {$table} (migration_id varchar(32) NOT NULL, checksum char(64) NOT NULL, applied_at datetime NOT NULL, PRIMARY KEY  (migration_id)) {$charset};" );
	if ( $database->lastError() !== '' ) {
		throw new RuntimeException( 'Could not create migration history table.' );
	} $sql = $database->prepare( "INSERT IGNORE INTO {$table} (migration_id,checksum,applied_at) VALUES (%s,%s,%s)", '001', hash_file( 'sha256', __FILE__ ), gmdate( 'Y-m-d H:i:s' ) );
	if ( $database->query( $sql ) === false ) {
		throw new RuntimeException( 'Could not record initial migration.' );
	} } };
