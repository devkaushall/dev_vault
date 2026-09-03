<?php
/** Indexed database search provider. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Search;

final class DatabaseSearchProvider implements SearchProvider {
	/** @var array<string,string> */
	private const SORT_SQL = array(
		'relevance'  => 'p.post_id DESC',
		'newest'     => 'p.post_modified_gmt DESC, p.post_id DESC',
		'oldest'     => 'p.post_modified_gmt ASC, p.post_id ASC',
		'price_asc'  => 'p.price ASC, p.post_id ASC',
		'price_desc' => 'p.price DESC, p.post_id DESC',
		'area_asc'   => 'p.area ASC, p.post_id ASC',
		'area_desc'  => 'p.area DESC, p.post_id DESC',
		'bedrooms'   => 'p.bedrooms DESC, p.post_id DESC',
		'featured'   => 'p.featured DESC, p.post_id DESC',
		'verified'   => 'p.verified DESC, p.post_id DESC',
	);

	public function search( SearchCriteria $criteria ): SearchPage {
		global $wpdb;
		$table       = $wpdb->prefix . 'rep_search_properties';
		$terms_table = $wpdb->prefix . 'rep_search_terms';
		$where       = array( "wp.post_type = 'property'", "wp.post_status = 'publish'" );
		$args        = array();
		$columns     = array( 'reference', 'country', 'state', 'city', 'locality', 'neighborhood', 'postal_code', 'currency', 'furnishing', 'possession', 'availability', 'construction_status', 'developer', 'rera' );
		foreach ( $columns as $column ) {
			if ( isset( $criteria->filters[ $column ] ) ) {
				$where[] = "p.{$column} = %s";
				$args[]  = $criteria->filters[ $column ]; }
		}
		$ranges = array(
			'price_min'     => array( 'price', '>=' ),
			'price_max'     => array( 'price', '<=' ),
			'area_min'      => array( 'area', '>=' ),
			'area_max'      => array( 'area', '<=' ),
			'plot_area_min' => array( 'plot_area', '>=' ),
			'plot_area_max' => array( 'plot_area', '<=' ),
		);
		foreach ( $ranges as $key => [$column, $operator] ) {
			if ( isset( $criteria->filters[ $key ] ) ) {
				$where[] = "p.{$column} {$operator} %f";
				$args[]  = $criteria->filters[ $key ]; }
		}
		foreach ( array( 'bedrooms', 'bathrooms', 'floors', 'floor', 'parking', 'project' ) as $key ) {
			if ( isset( $criteria->filters[ $key ] ) ) {
				$column  = 'project' === $key ? 'project_id' : $key;
				$where[] = "p.{$column} = %d";
				$args[]  = (int) $criteria->filters[ $key ]; }
		}
		foreach ( array( 'featured', 'verified' ) as $key ) {
			if ( isset( $criteria->filters[ $key ] ) ) {
				$where[] = "p.{$key} = %d";
				$args[]  = (int) $criteria->filters[ $key ]; }
		}
		if ( isset( $criteria->filters['keyword'] ) ) {
			$where[] = 'p.keyword_text LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( (string) $criteria->filters['keyword'] ) . '%'; }
		if ( isset( $criteria->filters['radius_km'] ) ) {
			$where[] = 'p.latitude IS NOT NULL AND p.longitude IS NOT NULL AND (6371.0088 * 2 * ASIN(SQRT(POWER(SIN(RADIANS(p.latitude - %f) / 2), 2) + COS(RADIANS(%f)) * COS(RADIANS(p.latitude)) * POWER(SIN(RADIANS(p.longitude - %f) / 2), 2)))) <= %f';
			$args[]  = $criteria->filters['geo_latitude'];
			$args[]  = $criteria->filters['geo_latitude'];
			$args[]  = $criteria->filters['geo_longitude'];
			$args[]  = $criteria->filters['radius_km'];
		}
		if ( isset( $criteria->filters['north'] ) ) {
			$where[] = 'p.latitude IS NOT NULL AND p.longitude IS NOT NULL AND p.latitude BETWEEN %f AND %f';
			$args[]  = $criteria->filters['south'];
			$args[]  = $criteria->filters['north'];
			if ( $criteria->filters['west'] <= $criteria->filters['east'] ) {
				$where[] = 'p.longitude BETWEEN %f AND %f';
			} else {
				$where[] = '(p.longitude >= %f OR p.longitude <= %f)';
			}
			$args[] = $criteria->filters['west'];
			$args[] = $criteria->filters['east'];
		}
		foreach ( $criteria->terms as $taxonomy => $ids ) {
			if ( in_array( $taxonomy, array( 'property_feature', 'property_amenity' ), true ) ) {
				foreach ( $ids as $id ) {
					$where[] = "EXISTS (SELECT 1 FROM {$terms_table} st WHERE st.post_id=p.post_id AND st.taxonomy=%s AND st.term_id=%d)";
					$args[]  = $taxonomy;
					$args[]  = $id;
				}
			} else {
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$where[]      = "EXISTS (SELECT 1 FROM {$terms_table} st WHERE st.post_id=p.post_id AND st.taxonomy=%s AND st.term_id IN ({$placeholders}))";
				$args[]       = $taxonomy;
				array_push( $args, ...$ids );
			}
		}
		$from = " FROM {$table} p INNER JOIN {$wpdb->posts} wp ON wp.ID=p.post_id WHERE " . implode( ' AND ', $where );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- SQL fragments contain only internal allowlisted identifiers; values are prepared.
		$count  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*)' . $from, ...$args ) );
		$order  = self::SORT_SQL[ $criteria->sort ];
		$offset = ( $criteria->page - 1 ) * $criteria->per_page;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- ORDER BY and SQL fragments come exclusively from internal allowlists; values are prepared.
		$sql = $wpdb->prepare( 'SELECT p.*' . $from . " ORDER BY {$order} LIMIT %d OFFSET %d", ...array_merge( $args, array( $criteria->per_page, $offset ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		$rows    = $wpdb->get_results( $sql, ARRAY_A );
		$results = array();
		foreach ( $rows as $row ) {
			$results[] = new SearchResult(
				array(
					'id'        => (int) $row['post_id'],
					'title'     => $row['title'],
					'slug'      => $row['slug'],
					'url'       => get_permalink( (int) $row['post_id'] ),
					'price'     => null === $row['price'] ? null : (float) $row['price'],
					'currency'  => $row['currency'],
					'country'   => $row['country'],
					'state'     => $row['state'],
					'city'      => $row['city'],
					'bedrooms'  => null === $row['bedrooms'] ? null : (int) $row['bedrooms'],
					'bathrooms' => null === $row['bathrooms'] ? null : (int) $row['bathrooms'],
					'area'      => null === $row['area'] ? null : (float) $row['area'],
					'featured'  => (bool) $row['featured'],
					'verified'  => (bool) $row['verified'],
				)
			);}
		return new SearchPage( $results, $count, $criteria->page, $criteria->per_page );
	}
}
