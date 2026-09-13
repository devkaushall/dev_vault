<?php
/**
 * Denormalised search index for fast, shared-hosting friendly filtering.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Search;

use EstatOS\Data\PostTypes;
use EstatOS\Data\Taxonomies;
use EstatOS\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * The index is a cache, never the source of truth. It can be rebuilt at any
 * time from the listings themselves, and every write is an upsert so repeating
 * an index operation is always safe.
 */
final class SearchIndex {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'set_object_terms', array( __CLASS__, 'on_terms_changed' ), 10, 4 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status_change' ), 10, 3 );
	}

	/**
	 * Insert or update the index row for a listing.
	 *
	 * @param int $listing_id Listing ID.
	 * @return void
	 */
	public static function index_listing( int $listing_id ): void {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return;
		}
		$post = get_post( $listing_id );
		if ( ! $post || PostTypes::LISTING !== $post->post_type ) {
			return;
		}
		if ( in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) ) {
			self::remove( $listing_id );
			return;
		}

		$meta = static function ( string $key ) use ( $listing_id ) {
			return get_post_meta( $listing_id, $key, true );
		};

		$locality_ids = wp_get_object_terms( $listing_id, Taxonomies::LOCALITY, array( 'fields' => 'ids' ) );
		$locality_ids = is_wp_error( $locality_ids ) ? array() : array_map( 'intval', $locality_ids );

		$locality_names = wp_get_object_terms( $listing_id, Taxonomies::LOCALITY, array( 'fields' => 'names' ) );
		$locality_names = is_wp_error( $locality_names ) ? array() : $locality_names;

		$offer = (string) $meta( '_estat_offer' );
		$price = (float) $meta( '_estat_price' );
		$rent  = (float) $meta( '_estat_rent' );
		$sort  = in_array( $offer, array( 'rent', 'lease' ), true ) && $rent > 0 ? $rent : $price;

		$search_text = implode(
			' ',
			array_filter(
				array(
					$post->post_title,
					wp_strip_all_tags( $post->post_excerpt ),
					implode( ' ', $locality_names ),
					(string) $meta( '_estat_developer' ),
					(string) $meta( '_estat_address' ),
					(string) $meta( '_estat_external_id' ),
				)
			)
		);

		$row = array(
			'listing_id'    => $listing_id,
			'post_status'   => $post->post_status,
			'title'         => mb_substr( $post->post_title, 0, 255 ),
			'search_text'   => mb_substr( $search_text, 0, 4000 ),
			'offer'         => $offer,
			'property_type' => (string) $meta( '_estat_property_type' ),
			'availability'  => (string) $meta( '_estat_availability' ),
			'construction'  => (string) $meta( '_estat_construction' ),
			'furnishing'    => (string) $meta( '_estat_furnishing' ),
			'price'         => $price,
			'rent'          => $rent,
			'price_sort'    => $sort,
			'area_sqft'     => (float) $meta( '_estat_area_sqft' ),
			'bedrooms'      => (int) $meta( '_estat_bedrooms' ),
			'bathrooms'     => (int) $meta( '_estat_bathrooms' ),
			'locality_ids'  => $locality_ids ? ',' . implode( ',', $locality_ids ) . ',' : '',
			'project_id'    => (int) $meta( '_estat_project_id' ),
			'agent_id'      => (int) $meta( '_estat_agent_id' ),
			'featured'      => $meta( '_estat_featured' ) ? 1 : 0,
			'investment'    => $meta( '_estat_investment' ) ? 1 : 0,
			'practice'      => $meta( '_estat_practice' ) ? 1 : 0,
			'completeness'  => (int) $meta( '_estat_completeness' ),
			'latitude'      => '' !== (string) $meta( '_estat_latitude' ) ? (float) $meta( '_estat_latitude' ) : null,
			'longitude'     => '' !== (string) $meta( '_estat_longitude' ) ? (float) $meta( '_estat_longitude' ) : null,
			'published_at'  => 'publish' === $post->post_status ? get_gmt_from_date( $post->post_date ) : null,
			'updated_at'    => current_time( 'mysql', true ),
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' );

		$table = Schema::table( 'estat_index' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE listing_id = %d", $listing_id ) );
		if ( $exists ) {
			$update = $row;
			unset( $update['listing_id'] );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $update, array( 'listing_id' => $listing_id ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $row, $formats );
		}

		wp_cache_delete( 'estat_search_counts', 'estat' );
	}

	/**
	 * Remove a listing from the index.
	 *
	 * @param int $listing_id Listing ID.
	 * @return void
	 */
	public static function remove( int $listing_id ): void {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( Schema::table( 'estat_index' ), array( 'listing_id' => $listing_id ), array( '%d' ) );
	}

	/**
	 * Rebuild a batch of the index. Returns how many rows were processed.
	 *
	 * @param int $batch  Batch size.
	 * @param int $offset Offset.
	 * @return int
	 */
	public static function rebuild_batch( int $batch = 100, int $offset = 0 ): int {
		$ids = get_posts(
			array(
				'post_type'      => PostTypes::LISTING,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => max( 1, min( 500, $batch ) ),
				'offset'         => max( 0, $offset ),
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $id ) {
			self::index_listing( (int) $id );
		}
		return count( $ids );
	}

	/**
	 * Index status for the maintenance screen.
	 *
	 * @return array{indexed:int,listings:int,in_sync:bool}
	 */
	public static function status(): array {
		global $wpdb;
		$indexed = 0;
		if ( Schema::healthy() ) {
			$table = Schema::table( 'estat_index' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$indexed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}
		$counts    = (array) wp_count_posts( PostTypes::LISTING );
		$listings  = 0;
		foreach ( array( 'publish', 'draft', 'pending', 'private' ) as $status ) {
			$listings += isset( $counts[ $status ] ) ? (int) $counts[ $status ] : 0;
		}
		return array( 'indexed' => $indexed, 'listings' => $listings, 'in_sync' => $indexed === $listings );
	}

	/**
	 * Reindex when localities or amenities change.
	 *
	 * @param int    $object_id Object ID.
	 * @param array  $terms     Terms.
	 * @param array  $tt_ids    Term taxonomy IDs.
	 * @param string $taxonomy  Taxonomy.
	 * @return void
	 */
	public static function on_terms_changed( $object_id, $terms, $tt_ids, $taxonomy ): void {
		if ( ! in_array( (string) $taxonomy, Taxonomies::all(), true ) ) {
			return;
		}
		if ( PostTypes::LISTING === get_post_type( (int) $object_id ) ) {
			self::index_listing( (int) $object_id );
		}
	}

	/**
	 * Reindex on publication status changes.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public static function on_status_change( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || PostTypes::LISTING !== $post->post_type ) {
			return;
		}
		self::index_listing( (int) $post->ID );
	}
}
