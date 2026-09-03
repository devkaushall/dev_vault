<?php
/** Optional Elementor query adapters backed by canonical REP services. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Elementor;

use Mayfair\RealEstatePlatform\Search\SearchCriteria;
use Mayfair\RealEstatePlatform\Search\SearchRequest;

final class QueryAdapter {
	private const ENTITY_QUERY_IDS = array(
		'project' => 'rep_projects',
		'agent'   => 'rep_agents',
		'agency'  => 'rep_agencies',
		'insight' => 'rep_insights',
	);
	private const CRITERIA         = array( 'reference', 'country', 'state', 'city', 'locality', 'neighborhood', 'postal_code', 'currency', 'furnishing', 'possession', 'availability', 'construction_status', 'developer', 'rera', 'price_min', 'price_max', 'area_min', 'area_max', 'plot_area_min', 'plot_area_max', 'bedrooms', 'bathrooms', 'floors', 'floor', 'parking', 'project', 'featured', 'verified', 'latitude', 'longitude', 'radius', 'radius_unit', 'north', 'south', 'east', 'west', 'property_type', 'property_status', 'property_category', 'property_label', 'property_feature', 'property_amenity', 'location' );

	public function __construct( private SearchRequest $search ) {}

	public function register(): void {
		add_action( 'elementor/query/rep_properties', array( $this, 'filterProperties' ), 10, 1 );
		foreach ( self::ENTITY_QUERY_IDS as $entity => $query_id ) {
			add_action(
				'elementor/query/' . $query_id,
				function ( \WP_Query $query ) use ( $entity ): void {
					$this->filterEntity( $query, $entity );
				},
				10,
				1
			);
		}
	}

	public function filterProperties( \WP_Query $query ): void {
		$input    = self::inputFromVars( $query->query_vars );
		$response = $this->search->execute( $input );
		$query->set( 'post_type', 'property' );
		$query->set( 'post_status', 'publish' );
		$query->set( 'ignore_sticky_posts', true );
		$query->set( 'no_found_rows', true );
		if ( is_wp_error( $response ) ) {
			$query->set( 'post__in', array( 0 ) );
			$query->set( 'posts_per_page', 1 );
			$query->set( 'paged', 1 );
			return;
		}
		$ids = array();
		foreach ( is_array( $response['results'] ?? null ) ? $response['results'] : array() as $item ) {
			if ( is_object( $item ) && method_exists( $item, 'jsonSerialize' ) ) {
				$item = $item->jsonSerialize();
			}
			if ( is_array( $item ) && isset( $item['id'] ) ) {
				$id = (int) $item['id'];
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}
		$query->set( 'post__in', empty( $ids ) ? array( 0 ) : array_values( array_unique( $ids ) ) );
		$query->set( 'orderby', 'post__in' );
		$query->set( 'posts_per_page', self::perPage( $input['per_page'] ?? 20 ) );
		// SearchRequest already applied the canonical page. Prevent WP_Query from slicing that page again.
		$query->set( 'paged', 1 );
	}

	public function filterEntity( \WP_Query $query, string $entity ): void {
		if ( ! array_key_exists( $entity, self::ENTITY_QUERY_IDS ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}
		$query->set( 'post_type', $entity );
		$query->set( 'post_status', 'publish' );
		$query->set( 'ignore_sticky_posts', true );
		$query->set( 'posts_per_page', self::perPage( $query->get( 'posts_per_page', 20 ) ) );
	}

	/** @param array<string,mixed> $vars @return array<string,mixed> */
	public static function inputFromVars( array $vars ): array {
		$input   = array();
		$keyword = $vars['rep_keyword'] ?? $vars['s'] ?? null;
		if ( is_scalar( $keyword ) && '' !== (string) $keyword ) {
			$input['keyword'] = (string) $keyword;
		}
		foreach ( self::CRITERIA as $key ) {
			$value = $vars[ 'rep_' . $key ] ?? null;
			if ( null !== $value && '' !== $value ) {
				$input[ $key ] = $value;
			}
		}
		$orderby = $vars['rep_orderby'] ?? null;
		if ( is_scalar( $orderby ) && '' !== (string) $orderby && in_array( (string) $orderby, SearchCriteria::SORTS, true ) ) {
			$input['orderby'] = (string) $orderby;
		}
		$input['page']     = self::perPage( $vars['rep_page'] ?? $vars['paged'] ?? 1 );
		$input['per_page'] = self::perPage( $vars['rep_per_page'] ?? $vars['posts_per_page'] ?? 20 );
		return $input;
	}

	private static function perPage( mixed $value ): int {
		$number = filter_var( $value, FILTER_VALIDATE_INT );
		return false === $number ? 20 : max( 1, min( 100, $number ) );
	}
}
