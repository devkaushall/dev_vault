<?php
/** Public Property search REST adapter. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\REST;

use Mayfair\RealEstatePlatform\Search\SearchCriteria;
use Mayfair\RealEstatePlatform\Search\SearchRequest;

final class PropertySearchController extends \WP_REST_Controller {
	public function __construct( private SearchRequest $search ) {
		$this->namespace = 'realestate-platform/v1';
		$this->rest_base = 'properties';
	}
	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getItems' ),
				'permission_callback' => '__return_true',
				'args'                => $this->getCollectionParams(),
				'schema'              => array( $this, 'getPublicItemSchema' ),
			)
		);
	}
	public function getItems( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$result = $this->search->execute( $request->get_params() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
	/** @return array<string,array<string,mixed>> */
	public function getCollectionParams(): array {
		$params  = array();
		$strings = array( 'keyword', 'reference', 'country', 'state', 'city', 'locality', 'neighborhood', 'postal_code', 'currency', 'furnishing', 'possession', 'availability', 'construction_status', 'developer', 'rera' );
		foreach ( $strings as $key ) {
			$params[ $key ] = array(
				'type'      => 'string',
				'maxLength' => 200,
			);
		}
		foreach ( array( 'price_min', 'price_max', 'area_min', 'area_max', 'plot_area_min', 'plot_area_max' ) as $key ) {
			$params[ $key ] = array(
				'type'    => 'number',
				'minimum' => 0,
			);
		}
		foreach ( array( 'bedrooms', 'bathrooms', 'floors', 'floor', 'parking', 'project' ) as $key ) {
			$params[ $key ] = array(
				'type'    => 'integer',
				'minimum' => 0,
			);
		}
		foreach ( array( 'latitude', 'north', 'south' ) as $key ) {
			$params[ $key ] = array(
				'type'    => 'number',
				'minimum' => -90,
				'maximum' => 90,
			);
		}
		foreach ( array( 'longitude', 'east', 'west' ) as $key ) {
			$params[ $key ] = array(
				'type'    => 'number',
				'minimum' => -180,
				'maximum' => 180,
			);
		}
		$params['radius']      = array(
			'type'             => 'number',
			'exclusiveMinimum' => 0,
			'maximum'          => 500,
		);
		$params['radius_unit'] = array(
			'type' => 'string',
			'enum' => array( 'km', 'mi' ),
		);
		foreach ( array( 'featured', 'verified' ) as $key ) {
			$params[ $key ] = array( 'type' => 'boolean' );
		}
		foreach ( SearchCriteria::TAXONOMIES as $taxonomy ) {
			$params[ $taxonomy ] = array(
				'type'     => 'array',
				'maxItems' => 25,
				'items'    => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			);
		}
		$params['page']     = array(
			'type'    => 'integer',
			'minimum' => 1,
			'default' => 1,
		);
		$params['per_page'] = array(
			'type'    => 'integer',
			'minimum' => 1,
			'maximum' => 100,
			'default' => 20,
		);
		$params['orderby']  = array(
			'type'    => 'string',
			'enum'    => SearchCriteria::SORTS,
			'default' => 'relevance',
		);
		return $params;
	}
	/** @return array<string,mixed> */
	public function getPublicItemSchema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'property-search',
			'type'       => 'object',
			'properties' => array(
				'results'         => array( 'type' => 'array' ),
				'pagination'      => array( 'type' => 'object' ),
				'applied_filters' => array( 'type' => 'object' ),
			),
		);
	}
}
