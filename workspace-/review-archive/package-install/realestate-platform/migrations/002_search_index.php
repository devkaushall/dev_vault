<?php
/**
 * Phase 3 disposable search projection.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);

use Mayfair\RealEstatePlatform\Contracts\DatabaseInterface;
use Mayfair\RealEstatePlatform\Contracts\MigrationInterface;

return new class() implements MigrationInterface {
	public function id(): string {
		return '002';
	}

	public function up( DatabaseInterface $database ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$charset    = $wpdb->get_charset_collate();
		$properties = $database->prefix() . 'rep_search_properties';
		$terms      = $database->prefix() . 'rep_search_terms';
		dbDelta(
			"CREATE TABLE {$properties} (
			post_id bigint(20) unsigned NOT NULL,
			post_modified_gmt datetime NOT NULL,
			title text NOT NULL,
			slug varchar(200) NOT NULL,
			keyword_text longtext NOT NULL,
			reference varchar(191) NULL,
			country varchar(191) NULL,
			state varchar(191) NULL,
			city varchar(191) NULL,
			locality varchar(191) NULL,
			neighborhood varchar(191) NULL,
			postal_code varchar(32) NULL,
			currency varchar(16) NULL,
			developer varchar(191) NULL,
			rera varchar(191) NULL,
			furnishing varchar(64) NULL,
			possession varchar(64) NULL,
			availability varchar(64) NULL,
			construction_status varchar(64) NULL,
			price decimal(20,4) NULL,
			area decimal(20,4) NULL,
			plot_area decimal(20,4) NULL,
			bedrooms int NULL,
			bathrooms int NULL,
			floors int NULL,
			floor int NULL,
			parking int NULL,
			latitude decimal(10,7) NULL,
			longitude decimal(10,7) NULL,
			project_id bigint(20) unsigned NULL,
			featured tinyint(1) NOT NULL DEFAULT 0,
			verified tinyint(1) NOT NULL DEFAULT 0,
			indexed_at datetime NOT NULL,
			PRIMARY KEY  (post_id),
			KEY modified (post_modified_gmt),
			KEY price (price),
			KEY area (area),
			KEY bedrooms (bedrooms),
			KEY bathrooms (bathrooms),
			KEY featured (featured,post_id),
			KEY verified (verified,post_id),
			KEY project (project_id),
			KEY country_state_city (country,state,city),
			KEY developer (developer)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$terms} (
			post_id bigint(20) unsigned NOT NULL,
			taxonomy varchar(32) NOT NULL,
			term_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (post_id,taxonomy,term_id),
			KEY term_lookup (taxonomy,term_id,post_id),
			KEY post_taxonomy (post_id,taxonomy)
		) {$charset};"
		);
		if ( '' !== $database->lastError() ) {
			throw new RuntimeException( 'Could not create search projection tables.' );
		}
		$history = $database->prefix() . 'rep_schema_migrations';
		$sql     = $database->prepare(
			"INSERT IGNORE INTO {$history} (migration_id,checksum,applied_at) VALUES (%s,%s,%s)",
			'002',
			hash_file( 'sha256', __FILE__ ),
			gmdate( 'Y-m-d H:i:s' )
		);
		if ( false === $database->query( $sql ) ) {
			throw new RuntimeException( 'Could not record search migration checksum.' );
		}
	}
};
