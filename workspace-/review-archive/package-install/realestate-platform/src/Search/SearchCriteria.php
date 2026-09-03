<?php
/**
 * Normalized, bounded public property-search criteria.
 *
 * @package RealEstatePlatform
 */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Search;

use InvalidArgumentException;

final class SearchCriteria {
	public const SORTS      = array( 'relevance', 'newest', 'oldest', 'price_asc', 'price_desc', 'area_asc', 'area_desc', 'bedrooms', 'featured', 'verified' );
	public const TAXONOMIES = array( 'property_type', 'property_status', 'property_category', 'property_label', 'property_feature', 'property_amenity', 'location' );

	/** @param array<string,mixed> $filters @param array<string,list<int>> $terms */
	private function __construct(
		public readonly array $filters,
		public readonly array $terms,
		public readonly int $page,
		public readonly int $per_page,
		public readonly string $sort
	) {}

	/** @param array<string,mixed> $input */
	public static function fromArray( array $input ): self {
		$allowed = array_merge(
			array( 'keyword', 'reference', 'country', 'state', 'city', 'locality', 'neighborhood', 'postal_code', 'currency', 'furnishing', 'possession', 'availability', 'construction_status', 'developer', 'rera', 'price_min', 'price_max', 'area_min', 'area_max', 'plot_area_min', 'plot_area_max', 'bedrooms', 'bathrooms', 'floors', 'floor', 'parking', 'project', 'featured', 'verified', 'latitude', 'longitude', 'radius', 'radius_unit', 'north', 'south', 'east', 'west', 'page', 'per_page', 'orderby' ),
			self::TAXONOMIES
		);
		$unknown = array_diff( array_keys( $input ), $allowed );
		foreach ( $input as $key => $value ) {
			if ( null === $value ) {
				throw new InvalidArgumentException( "Invalid {$key}." );
			}
		}
		if ( $unknown ) {
			throw new InvalidArgumentException( 'Unsupported search parameter: ' . (string) reset( $unknown ) . '.' );
		}
		$page = self::integer( $input, 'page', 1, 1 );
		$per  = self::integer( $input, 'per_page', 20, 1 );
		if ( $per > 100 ) {
			throw new InvalidArgumentException( 'per_page must not exceed 100.' );
		}
		$sort = isset( $input['orderby'] ) ? sanitize_key( (string) $input['orderby'] ) : 'relevance';
		if ( ! in_array( $sort, self::SORTS, true ) ) {
			throw new InvalidArgumentException( 'Invalid orderby value.' );
		}
		$filters = array();
		foreach ( array( 'keyword', 'reference', 'country', 'state', 'city', 'locality', 'neighborhood', 'postal_code', 'currency', 'furnishing', 'possession', 'availability', 'construction_status', 'developer', 'rera' ) as $key ) {
			if ( isset( $input[ $key ] ) && '' !== $input[ $key ] ) {
				if ( ! is_scalar( $input[ $key ] ) || mb_strlen( (string) $input[ $key ] ) > 200 ) {
					throw new InvalidArgumentException( "Invalid {$key}." );
				}
				$raw = (string) $input[ $key ];
				if ( preg_match( '/[<>]|--|\/\*|\*\/|;/', $raw ) ) {
					throw new InvalidArgumentException( "Invalid {$key}." );
				}
				$filters[ $key ] = sanitize_text_field( $raw );
			}
		}
		foreach ( array( 'price_min', 'price_max', 'area_min', 'area_max', 'plot_area_min', 'plot_area_max', 'bedrooms', 'bathrooms', 'floors', 'floor', 'parking', 'project' ) as $key ) {
			if ( isset( $input[ $key ] ) && '' !== $input[ $key ] ) {
				if ( ! is_numeric( $input[ $key ] ) || (float) $input[ $key ] < 0 ) {
					throw new InvalidArgumentException( "Invalid {$key}." );
				}
				$filters[ $key ] = (float) $input[ $key ];
			}
		}
		self::addGeospatialFilters( $input, $filters );
		foreach ( array( 'featured', 'verified' ) as $key ) {
			if ( isset( $input[ $key ] ) && '' !== $input[ $key ] ) {
				$value = filter_var( $input[ $key ], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				if ( null === $value ) {
					throw new InvalidArgumentException( "Invalid {$key}." );
				}
				$filters[ $key ] = $value;
			}
		}
		foreach ( array( array( 'price_min', 'price_max' ), array( 'area_min', 'area_max' ), array( 'plot_area_min', 'plot_area_max' ) ) as $range ) {
			if ( isset( $filters[ $range[0] ], $filters[ $range[1] ] ) && $filters[ $range[0] ] > $filters[ $range[1] ] ) {
				throw new InvalidArgumentException( "Invalid {$range[0]}/{$range[1]} range." );
			}
		}
		$terms = array();
		foreach ( self::TAXONOMIES as $taxonomy ) {
			if ( ! isset( $input[ $taxonomy ] ) || '' === $input[ $taxonomy ] ) {
				continue;
			}
			$values = is_array( $input[ $taxonomy ] ) ? $input[ $taxonomy ] : explode( ',', (string) $input[ $taxonomy ] );
			if ( count( $values ) > 25 ) {
				throw new InvalidArgumentException( "Too many {$taxonomy} terms." );
			}
			$ids = array_values( array_unique( array_map( 'absint', $values ) ) );
			if ( in_array( 0, $ids, true ) ) {
				throw new InvalidArgumentException( "Invalid {$taxonomy}." );
			}
			$terms[ $taxonomy ] = $ids;
		}
		return new self( $filters, $terms, $page, $per, $sort );
	}

	/** @param array<string,mixed> $input */
	private static function integer( array $input, string $key, int $default_value, int $minimum ): int {
		if ( ! isset( $input[ $key ] ) ) {
			return $default_value;
		}
		$value = filter_var( $input[ $key ], FILTER_VALIDATE_INT );
		if ( false === $value || $value < $minimum ) {
			throw new InvalidArgumentException( "Invalid {$key}." );
		}
		return $value;
	}

	/** @param array<string,mixed> $input @param array<string,mixed> $filters */
	private static function addGeospatialFilters( array $input, array &$filters ): void {
		$radius_keys = array( 'latitude', 'longitude', 'radius' );
		$bounds_keys = array( 'north', 'south', 'east', 'west' );
		$radius_set  = array_filter( $radius_keys, static fn( string $key ): bool => array_key_exists( $key, $input ) );
		$bounds_set  = array_filter( $bounds_keys, static fn( string $key ): bool => array_key_exists( $key, $input ) );
		if ( $radius_set && count( $radius_set ) !== count( $radius_keys ) ) {
			throw new InvalidArgumentException( 'Latitude, longitude and radius must be supplied together.' );
		}
		if ( $bounds_set && count( $bounds_set ) !== count( $bounds_keys ) ) {
			throw new InvalidArgumentException( 'North, south, east and west must be supplied together.' );
		}
		if ( $radius_set && $bounds_set ) {
			throw new InvalidArgumentException( 'Radius and bounds searches cannot be combined.' );
		}
		if ( $radius_set ) {
			$latitude  = self::finiteNumber( $input['latitude'], 'latitude', -90.0, 90.0 );
			$longitude = self::finiteNumber( $input['longitude'], 'longitude', -180.0, 180.0 );
			$radius    = self::finiteNumber( $input['radius'], 'radius', 0.000001, 500.0 );
			$unit      = isset( $input['radius_unit'] ) ? sanitize_key( (string) $input['radius_unit'] ) : 'km';
			if ( ! in_array( $unit, array( 'km', 'mi' ), true ) ) {
				throw new InvalidArgumentException( 'Invalid radius_unit.' );
			}
			$filters['geo_latitude']  = $latitude;
			$filters['geo_longitude'] = $longitude;
			$filters['radius_km']     = 'mi' === $unit ? $radius * 1.609344 : $radius;
			$filters['radius_unit']   = $unit;
		}
		if ( $bounds_set ) {
			$north = self::finiteNumber( $input['north'], 'north', -90.0, 90.0 );
			$south = self::finiteNumber( $input['south'], 'south', -90.0, 90.0 );
			$east  = self::finiteNumber( $input['east'], 'east', -180.0, 180.0 );
			$west  = self::finiteNumber( $input['west'], 'west', -180.0, 180.0 );
			if ( $north < $south ) {
				throw new InvalidArgumentException( 'North must be greater than or equal to south.' );
			}
			$filters += compact( 'north', 'south', 'east', 'west' );
		}
		if ( isset( $input['radius_unit'] ) && ! $radius_set ) {
			throw new InvalidArgumentException( 'radius_unit requires a radius search.' );
		}
	}

	private static function finiteNumber( mixed $value, string $key, float $minimum, float $maximum ): float {
		if ( ! is_numeric( $value ) ) {
			throw new InvalidArgumentException( "Invalid {$key}." );
		}
		$number = (float) $value;
		if ( ! is_finite( $number ) || $number < $minimum || $number > $maximum ) {
			throw new InvalidArgumentException( "Invalid {$key}." );
		}
		return $number;
	}

	/** @return array<string,mixed> */
	public function canonical(): array {
		$data = $this->filters + $this->terms;
		if ( isset( $data['radius_km'] ) ) {
			$unit              = (string) $data['radius_unit'];
			$data['latitude']  = $data['geo_latitude'];
			$data['longitude'] = $data['geo_longitude'];
			$data['radius']    = 'mi' === $unit ? $data['radius_km'] / 1.609344 : $data['radius_km'];
			unset( $data['geo_latitude'], $data['geo_longitude'], $data['radius_km'] );
		}
		ksort( $data );
		return $data + array(
			'page'     => $this->page,
			'per_page' => $this->per_page,
			'orderby'  => $this->sort,
		);
	}
}
