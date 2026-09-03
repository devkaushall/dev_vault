<?php
/**
 * Read-only search projection consistency report.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\Search;

final class SearchIndexConsistency {
	/** Canonical metadata copied into the projection. */
	private const META_COLUMNS = array(
		'reference',
		'country',
		'state',
		'city',
		'locality',
		'neighborhood',
		'postal_code',
		'currency',
		'developer',
		'rera',
		'furnishing',
		'possession',
		'availability',
		'construction_status',
		'price',
		'area',
		'plot_area',
		'bedrooms',
		'bathrooms',
		'floors',
		'floor',
		'parking',
		'latitude',
		'longitude',
		'project_id',
		'featured',
		'verified',
	);

	/** @return array<string,mixed> */
	public function report(): array {
		global $wpdb;
		$index             = $wpdb->prefix . 'rep_search_properties';
		$bridge            = $wpdb->prefix . 'rep_search_terms';
		$property_table    = $this->tableExists( $index );
		$taxonomy_table    = $this->tableExists( $bridge );
		$installed_version = (string) get_option( 'realestate_platform_db_version', '0' );
		if ( ! $property_table || ! $taxonomy_table ) {
			return $this->missingSchemaReport( $property_table, $taxonomy_table, $installed_version );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table identifiers only.
		$expected   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='property' AND post_status='publish'" );
		$indexed    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$index}" );
		$missing    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$index} i ON i.post_id=p.ID WHERE p.post_type='property' AND p.post_status='publish' AND i.post_id IS NULL" );
		$orphaned   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$index} i LEFT JOIN {$wpdb->posts} p ON p.ID=i.post_id WHERE p.ID IS NULL" );
		$visibility = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$index} i JOIN {$wpdb->posts} p ON p.ID=i.post_id WHERE p.post_type<>'property' OR p.post_status<>'publish'" );
		$duplicates = (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT post_id FROM {$index} GROUP BY post_id HAVING COUNT(*)>1) d" );
		$rows       = $wpdb->get_results( "SELECT * FROM {$index}", ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$stale    = 0;
		$taxonomy = 0;
		foreach ( $rows as $row ) {
			$post = get_post( (int) $row['post_id'] );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			if ( $this->isStale( $post, $row ) ) {
				++$stale;
			}
			if ( $this->hasTaxonomyMismatch( (int) $post->ID, $bridge ) ) {
				++$taxonomy;
			}
		}

		return array(
			'schema'                => $installed_version,
			'schema_current'        => REALESTATE_PLATFORM_DB_VERSION === $installed_version,
			'property_table_exists' => $property_table,
			'taxonomy_table_exists' => $taxonomy_table,
			'last_rebuild'          => get_option( 'realestate_platform_search_last_rebuild', null ),
			'expected'              => $expected,
			'indexed'               => $indexed,
			'missing'               => $missing,
			'stale'                 => $stale,
			'orphaned'              => $orphaned,
			'duplicates'            => $duplicates,
			'taxonomy_mismatches'   => $taxonomy,
			'visibility_mismatches' => $visibility,
			'healthy'               => 0 === $missing + $stale + $orphaned + $duplicates + $taxonomy + $visibility,
		);
	}

	private function tableExists( string $table ): bool {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
	}

	/** @return array<string,mixed> */
	private function missingSchemaReport( bool $property_table, bool $taxonomy_table, string $version ): array {
		return array(
			'schema'                => $version,
			'schema_current'        => REALESTATE_PLATFORM_DB_VERSION === $version,
			'property_table_exists' => $property_table,
			'taxonomy_table_exists' => $taxonomy_table,
			'last_rebuild'          => get_option( 'realestate_platform_search_last_rebuild', null ),
			'expected'              => 0,
			'indexed'               => 0,
			'missing'               => 0,
			'stale'                 => 0,
			'orphaned'              => 0,
			'duplicates'            => 0,
			'taxonomy_mismatches'   => 0,
			'visibility_mismatches' => 0,
			'healthy'               => false,
		);
	}

	/** @param array<string,mixed> $row */
	private function isStale( \WP_Post $post, array $row ): bool {
		$expected = array(
			'post_modified_gmt' => $post->post_modified_gmt,
			'title'             => $post->post_title,
			'slug'              => $post->post_name,
		);
		foreach ( self::META_COLUMNS as $key ) {
			$value = get_post_meta( $post->ID, 'rep_' . $key, true );
			if ( 'featured' === $key || 'verified' === $key ) {
				$value = (int) (bool) $value;
			} elseif ( '' === $value ) {
				$value = null;
			}
			$expected[ $key ] = $value;
		}
		foreach ( $expected as $key => $value ) {
			if ( null === $value ? null !== $row[ $key ] : (string) $value !== (string) $row[ $key ] ) {
				return true;
			}
		}
		return false;
	}

	private function hasTaxonomyMismatch( int $post_id, string $bridge ): bool {
		global $wpdb;
		foreach ( SearchIndexWriter::TAXONOMIES as $taxonomy ) {
			$canonical_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $canonical_terms ) ) {
				return true;
			}
			$canonical = array_map( 'intval', $canonical_terms );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table identifier only.
			$projected = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$bridge} WHERE post_id=%d AND taxonomy=%s ORDER BY term_id", $post_id, $taxonomy ) ) );
			sort( $canonical );
			sort( $projected );
			if ( $canonical !== $projected ) {
				return true;
			}
		}
		return false;
	}
}
