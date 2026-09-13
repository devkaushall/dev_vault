<?php
/**
 * Society / project repository.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Data;

use EstatOS\Audit\AuditLog;
use EstatOS\Settings\Settings;
use EstatOS\Support\Format;
use EstatOS\Support\Sanitize;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Projects are first class records that can act as a parent for listings.
 */
final class Projects {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
	}

	/**
	 * Create or update a project.
	 *
	 * @param array<string,mixed> $input   Raw input.
	 * @param int                 $post_id Existing ID or 0.
	 * @return int|WP_Error
	 */
	public static function save( array $input, int $post_id = 0 ) {
		$title = Sanitize::title( $input['title'] ?? '' );
		if ( 0 === $post_id && '' === $title ) {
			return new WP_Error( 'estat_invalid_title', __( 'Please enter the name of the society or project.', 'estat-os' ) );
		}

		$postarr = array(
			'post_type'   => PostTypes::PROJECT,
			'post_title'  => $title,
			'post_status' => Sanitize::choice( $input['status'] ?? 'publish', array( 'draft', 'publish' ), 'publish' ),
		);
		if ( isset( $input['description'] ) ) {
			$postarr['post_content'] = Sanitize::html( $input['description'] );
		}
		if ( $post_id > 0 ) {
			$postarr['ID'] = $post_id;

			// Snapshot what is there now, before any of it is overwritten.
			Undo::capture( $post_id );
			$result        = wp_update_post( $postarr, true );
		} else {
			$result = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$project_id = (int) $result;

		foreach ( Meta::project_schema() as $key => $field ) {
			if ( ! empty( $field['computed'] ) ) {
				continue;
			}
			$short = substr( $key, strlen( '_estat_' ) );
			if ( array_key_exists( $key, $input ) ) {
				$raw = $input[ $key ];
			} elseif ( array_key_exists( $short, $input ) ) {
				$raw = $input[ $short ];
			} else {
				continue;
			}
			$value = Meta::sanitize_value( $raw, $field );
			if ( '' === $value || array() === $value ) {
				delete_post_meta( $project_id, $key );
			} else {
				update_post_meta( $project_id, $key, $value );
			}
		}

		if ( isset( $input['locality'] ) ) {
			$locality = is_array( $input['locality'] ) ? $input['locality'] : array( $input['locality'] );
			$ids      = array();
			foreach ( $locality as $item ) {
				if ( is_numeric( $item ) ) {
					$ids[] = (int) $item;
					continue;
				}
				$name = Sanitize::text( $item );
				if ( '' === $name ) {
					continue;
				}
				$term = term_exists( $name, Taxonomies::LOCALITY );
				if ( ! is_array( $term ) ) {
					$term = wp_insert_term( $name, Taxonomies::LOCALITY );
				}
				if ( ! is_wp_error( $term ) ) {
					$ids[] = (int) $term['term_id'];
				}
			}
			wp_set_object_terms( $project_id, $ids, Taxonomies::LOCALITY, false );
		}

		if ( isset( $input['cover_id'] ) ) {
			$cover = Sanitize::int( $input['cover_id'] );
			if ( $cover ) {
				set_post_thumbnail( $project_id, $cover );
			}
		}

		AuditLog::record( $post_id > 0 ? 'project.updated' : 'project.created', 'project', $project_id, array() );
		return $project_id;
	}

	/**
	 * How many published listings belong to each of these projects.
	 *
	 * The Projects screen used to call stats() once per row, which is one
	 * database query per project on every page load. This asks once.
	 *
	 * @param array<int> $project_ids Project IDs.
	 * @return array<int,int> Project ID => number of published listings.
	 */
	public static function listing_counts( array $project_ids ): array {
		$project_ids = array_values( array_unique( array_filter( array_map( 'absint', $project_ids ) ) ) );

		if ( ! $project_ids ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'estat_index';

		// Integers only, and built from absint above, so this is safe to inline.
		$placeholders = implode( ',', array_fill( 0, count( $project_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT project_id, COUNT(*) AS total
				 FROM {$table}
				 WHERE project_id IN ({$placeholders}) AND post_status = 'publish'
				 GROUP BY project_id",
				...$project_ids
			),
			ARRAY_A
		);
		// phpcs:enable

		$counts = array_fill_keys( $project_ids, 0 );

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['project_id'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * One project as a plain array.
	 *
	 * Listings and Agents both had this; Projects did not, so seven fields the
	 * editor collects - the builder, the price range, unit counts, possession
	 * date, unit types and the registration number - reached nobody. The
	 * template read only the stage and two figures from stats().
	 *
	 * @param int $project_id Project ID.
	 * @return array<string,mixed> Empty when the ID is not a project.
	 */
	public static function to_array( int $project_id ): array {
		$post = get_post( $project_id );

		if ( ! $post || PostTypes::PROJECT !== $post->post_type ) {
			return array();
		}

		/**
		 * A price range of 0 means "not stated", not "free". Callers need to
		 * be able to tell those apart, so the raw numbers are given as well
		 * as the formatted strings.
		 */
		$price_min = (float) get_post_meta( $project_id, '_estat_price_min', true );
		$price_max = (float) get_post_meta( $project_id, '_estat_price_max', true );

		$data = array(
			'id'                => $project_id,
			'title'             => $post->post_title,
			'description'       => $post->post_content,
			'url'               => get_permalink( $project_id ),
			'status'            => $post->post_status,
			'stage'             => (string) get_post_meta( $project_id, '_estat_project_status', true ),
			'developer'         => (string) get_post_meta( $project_id, '_estat_developer', true ),
			'possession_date'   => (string) get_post_meta( $project_id, '_estat_possession_date', true ),
			'price_min'         => $price_min,
			'price_max'         => $price_max,
			'price_min_display' => $price_min > 0 ? Format::money( $price_min ) : '',
			'price_max_display' => $price_max > 0 ? Format::money( $price_max ) : '',
			'total_units'       => (int) get_post_meta( $project_id, '_estat_total_units', true ),
			'available_units'   => (int) get_post_meta( $project_id, '_estat_available_units', true ),
			'unit_types'        => (string) get_post_meta( $project_id, '_estat_unit_types', true ),
			'highlights'        => (string) get_post_meta( $project_id, '_estat_highlights', true ),
			'locality'          => wp_get_object_terms( $project_id, Taxonomies::LOCALITY, array( 'fields' => 'names' ) ),
			'cover'             => get_the_post_thumbnail_url( $project_id, 'large' ) ?: '',
			'latitude'          => (string) get_post_meta( $project_id, '_estat_latitude', true ),
			'longitude'         => (string) get_post_meta( $project_id, '_estat_longitude', true ),
		);

		// Same rule as a listing: shown only when the office has turned
		// registration numbers on.
		if ( Settings::get( 'show_regulatory' ) ) {
			$data['regulatory_id'] = (string) get_post_meta( $project_id, '_estat_regulatory_id', true );
		}

		/**
		 * Filter the project representation.
		 *
		 * @param array<string,mixed> $data       Project data.
		 * @param int                 $project_id Project ID.
		 */
		return (array) apply_filters( 'estat_project_array', $data, $project_id );
	}

	/**
	 * Statistics for one project.
	 *
	 * @param int $project_id Project ID.
	 * @return array<string,mixed>
	 */
	public static function stats( int $project_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'estat_index';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total,
					SUM(CASE WHEN availability = 'available' THEN 1 ELSE 0 END) AS available,
					MIN(CASE WHEN price > 0 THEN price END) AS price_min,
					MAX(price) AS price_max
				 FROM {$table} WHERE project_id = %d AND post_status = 'publish'",
				$project_id
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'project_id' => $project_id,
			'listings'   => isset( $row['total'] ) ? (int) $row['total'] : 0,
			'available'  => isset( $row['available'] ) ? (int) $row['available'] : 0,
			'price_min'  => isset( $row['price_min'] ) ? (float) $row['price_min'] : 0.0,
			'price_max'  => isset( $row['price_max'] ) ? (float) $row['price_max'] : 0.0,
		);
	}

	/**
	 * Detach listings from a deleted project so relationships never break.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_delete( int $post_id ): void {
		if ( PostTypes::PROJECT !== get_post_type( $post_id ) ) {
			return;
		}
		$listings = get_posts(
			array(
				'post_type'   => PostTypes::LISTING,
				'post_status' => 'any',
				'numberposts' => 500,
				'fields'      => 'ids',
				'meta_key'    => '_estat_project_id',
				'meta_value'  => $post_id,
			)
		);
		foreach ( $listings as $listing_id ) {
			delete_post_meta( (int) $listing_id, '_estat_project_id' );
			Listings::refresh_derived( (int) $listing_id );
		}
		AuditLog::record( 'project.deleted', 'project', $post_id, array( 'detached' => count( $listings ) ) );
	}

	/**
	 * Find a project by reference number.
	 *
	 * @param string $external_id Reference.
	 * @return int
	 */
	public static function find_by_external_id( string $external_id ): int {
		$external_id = Sanitize::text( $external_id );
		if ( '' === $external_id ) {
			return 0;
		}
		$found = get_posts(
			array(
				'post_type'     => PostTypes::PROJECT,
				'post_status'   => 'any',
				'numberposts'   => 1,
				'fields'        => 'ids',
				'meta_key'      => '_estat_external_id',
				'meta_value'    => $external_id,
				'no_found_rows' => true,
			)
		);
		return $found ? (int) $found[0] : 0;
	}
}
