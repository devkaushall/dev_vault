<?php
/**
 * Property search built on the plugin index. Always paginated, always prepared.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Search;

use EstatOS\Install\Schema;
use EstatOS\Settings\Settings;
use EstatOS\Support\Sanitize;
use EstatOS\Support\Vocabulary;

defined( 'ABSPATH' ) || exit;

/**
 * Search never uses posts_per_page = -1 and never concatenates user values.
 */
final class Query {

	/**
	 * Maximum results per page, whatever a caller asks for.
	 */
	public const MAX_PER_PAGE = 60;

	/**
	 * Register hooks (nothing global needed; kept for module symmetry).
	 *
	 * @return void
	 */
	public function register(): void {}

	/**
	 * Normalise raw request arguments into a safe filter set.
	 *
	 * @param array<string,mixed> $raw Raw arguments.
	 * @return array<string,mixed>
	 */
	public static function normalize( array $raw ): array {
		$defaults = array(
			'keyword'       => '',
			'locality'      => array(),
			'property_type' => array(),
			'offer'         => '',
			'bedrooms_min'  => 0,
			'bedrooms_max'  => 0,
			'price_min'     => 0.0,
			'price_max'     => 0.0,
			'area_min'      => 0.0,
			'area_max'      => 0.0,
			'furnishing'    => '',
			'construction'  => '',
			'availability'  => '',
			'featured'      => false,
			'investment'    => false,
			'project_id'    => 0,
			'agent_id'      => 0,
			'orderby'       => 'recent',
			'page'          => 1,
			'per_page'      => (int) Settings::get( 'listings_per_page', 12 ),
			'include_practice' => true,
		);
		$args = array_merge( $defaults, $raw );

		return array(
			'keyword'          => mb_substr( Sanitize::text( $args['keyword'] ), 0, 120 ),
			'locality'         => array_slice( array_map( 'absint', (array) $args['locality'] ), 0, 20 ),
			'property_type'    => array_values( array_intersect( array_map( array( Sanitize::class, 'text' ), (array) $args['property_type'] ), Vocabulary::keys( 'property_types' ) ) ),
			'offer'            => Sanitize::choice( $args['offer'], Vocabulary::keys( 'offers' ), '' ),
			'bedrooms_min'     => Sanitize::int( $args['bedrooms_min'], 0, 30 ),
			'bedrooms_max'     => Sanitize::int( $args['bedrooms_max'], 0, 30 ),
			'price_min'        => Sanitize::float( $args['price_min'] ),
			'price_max'        => Sanitize::float( $args['price_max'] ),
			'area_min'         => Sanitize::float( $args['area_min'] ),
			'area_max'         => Sanitize::float( $args['area_max'] ),
			'furnishing'       => Sanitize::choice( $args['furnishing'], Vocabulary::keys( 'furnishing' ), '' ),
			'construction'     => Sanitize::choice( $args['construction'], Vocabulary::keys( 'construction' ), '' ),
			'availability'     => Sanitize::choice( $args['availability'], Vocabulary::keys( 'availability' ), '' ),
			'featured'         => Sanitize::bool( $args['featured'] ),
			'investment'       => Sanitize::bool( $args['investment'] ),
			'project_id'       => Sanitize::int( $args['project_id'] ),
			'agent_id'         => Sanitize::int( $args['agent_id'] ),
			'orderby'          => Sanitize::choice( $args['orderby'], array( 'recent', 'price_asc', 'price_desc', 'area_asc', 'area_desc', 'featured' ), 'recent' ),
			'page'             => Sanitize::int( $args['page'], 1, 10000 ),
			'per_page'         => Sanitize::int( $args['per_page'], 1, self::MAX_PER_PAGE ),
			'include_practice' => Sanitize::bool( $args['include_practice'] ),
		);
	}

	/**
	 * Run a search.
	 *
	 * @param array<string,mixed> $raw    Raw filters.
	 * @param string              $status Publication status to search.
	 * @return array{ids:int[],total:int,page:int,per_page:int,pages:int}
	 */
	public static function search( array $raw, string $status = 'publish' ): array {
		global $wpdb;
		$args  = self::normalize( $raw );
		$empty = array( 'ids' => array(), 'total' => 0, 'page' => $args['page'], 'per_page' => $args['per_page'], 'pages' => 0 );
		if ( ! Schema::healthy() ) {
			return $empty;
		}

		$table  = Schema::table( 'estat_index' );
		$where  = array( 'post_status = %s' );
		$params = array( Sanitize::choice( $status, array( 'publish', 'draft', 'pending', 'private' ), 'publish' ) );

		if ( '' !== $args['keyword'] ) {
			$where[]  = '(title LIKE %s OR search_text LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['keyword'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		if ( $args['locality'] ) {
			$parts = array();
			foreach ( $args['locality'] as $term_id ) {
				$parts[]  = 'locality_ids LIKE %s';
				$params[] = '%,' . (int) $term_id . ',%';
			}
			$where[] = '(' . implode( ' OR ', $parts ) . ')';
		}
		if ( $args['property_type'] ) {
			$placeholders = implode( ',', array_fill( 0, count( $args['property_type'] ), '%s' ) );
			$where[]      = "property_type IN ({$placeholders})";
			$params       = array_merge( $params, $args['property_type'] );
		}
		foreach ( array( 'offer' => 'offer', 'furnishing' => 'furnishing', 'construction' => 'construction', 'availability' => 'availability' ) as $key => $column ) {
			if ( '' !== $args[ $key ] ) {
				$where[]  = "{$column} = %s";
				$params[] = $args[ $key ];
			}
		}
		if ( $args['bedrooms_min'] > 0 ) {
			$where[]  = 'bedrooms >= %d';
			$params[] = $args['bedrooms_min'];
		}
		if ( $args['bedrooms_max'] > 0 ) {
			$where[]  = 'bedrooms <= %d';
			$params[] = $args['bedrooms_max'];
		}
		if ( $args['price_min'] > 0 ) {
			$where[]  = 'price_sort >= %f';
			$params[] = $args['price_min'];
		}
		if ( $args['price_max'] > 0 ) {
			$where[]  = 'price_sort <= %f';
			$params[] = $args['price_max'];
		}
		if ( $args['area_min'] > 0 ) {
			$where[]  = 'area_sqft >= %f';
			$params[] = $args['area_min'];
		}
		if ( $args['area_max'] > 0 ) {
			$where[]  = 'area_sqft <= %f';
			$params[] = $args['area_max'];
		}
		if ( $args['featured'] ) {
			$where[] = 'featured = 1';
		}
		if ( $args['investment'] ) {
			$where[] = 'investment = 1';
		}
		if ( $args['project_id'] > 0 ) {
			$where[]  = 'project_id = %d';
			$params[] = $args['project_id'];
		}
		if ( $args['agent_id'] > 0 ) {
			$where[]  = 'agent_id = %d';
			$params[] = $args['agent_id'];
		}
		if ( ! $args['include_practice'] ) {
			$where[] = 'practice = 0';
		}

		$where_sql = implode( ' AND ', $where );
		$order_sql = self::order_sql( $args['orderby'] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params ) );

		/*
		 * Count first, then clamp. Asking for page 100000 of a two-page result
		 * used to be answered with page=100000, pages=2 - so anything building
		 * "page X of Y" printed a number that could not exist, and
		 * paginate_links() was handed a current page outside its own range.
		 */
		$pages = (int) ceil( $total / max( 1, $args['per_page'] ) );

		if ( $args['page'] > $pages ) {
			$args['page'] = max( 1, $pages );
		}

		$offset = ( $args['page'] - 1 ) * $args['per_page'];
		$rows  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT listing_id FROM {$table} WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d OFFSET %d",
				array_merge( $params, array( $args['per_page'], $offset ) )
			)
		);
		// phpcs:enable

		return array(
			'ids'      => array_map( 'intval', (array) $rows ),
			'total'    => $total,
			'page'     => $args['page'],
			'per_page' => $args['per_page'],
			'pages'    => $pages,
		);
	}

	/**
	 * Translate an order key into a safe ORDER BY clause.
	 *
	 * @param string $orderby Order key (already validated).
	 * @return string
	 */
	private static function order_sql( string $orderby ): string {
		switch ( $orderby ) {
			case 'price_asc':
				return 'price_sort ASC, listing_id DESC';
			case 'price_desc':
				return 'price_sort DESC, listing_id DESC';
			case 'area_asc':
				return 'area_sqft ASC, listing_id DESC';
			case 'area_desc':
				return 'area_sqft DESC, listing_id DESC';
			case 'featured':
				return 'featured DESC, published_at DESC, listing_id DESC';
			case 'recent':
			default:
				return 'published_at DESC, listing_id DESC';
		}
	}

	/**
	 * Facet counts for the filter sidebar (cached briefly).
	 *
	 * @return array<string,array<string,int>>
	 */
	public static function facets(): array {
		$cached = wp_cache_get( 'estat_search_counts', 'estat' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return array();
		}
		$table  = Schema::table( 'estat_index' );
		$facets = array();
		foreach ( array( 'offer', 'property_type', 'availability', 'construction' ) as $column ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( "SELECT {$column} AS k, COUNT(*) AS c FROM {$table} WHERE post_status = 'publish' GROUP BY {$column}", ARRAY_A );
			$map  = array();
			foreach ( (array) $rows as $row ) {
				if ( '' !== (string) $row['k'] ) {
					$map[ (string) $row['k'] ] = (int) $row['c'];
				}
			}
			$facets[ $column ] = $map;
		}
		wp_cache_set( 'estat_search_counts', $facets, 'estat', 5 * MINUTE_IN_SECONDS );
		return $facets;
	}
}
