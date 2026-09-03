<?php
/** Canonical search query-string parser and serializer. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Search;

final class SearchUrlState {
	public function parse( string $query ): SearchCriteria|\WP_Error {
		$query = ltrim( $query, '?' );
		if ( '' === $query ) {
			return SearchCriteria::fromArray( array() );
		}
		$seen = array();
		foreach ( explode( '&', $query ) as $pair ) {
			$key = rawurldecode( explode( '=', $pair, 2 )[0] );
			if ( isset( $seen[ $key ] ) || str_contains( $key, '[' ) ) {
				return new \WP_Error( 'realestate_platform_invalid_url_state', 'Duplicate or nested search parameter.', array( 'status' => 400 ) );
			}
			$seen[ $key ] = true;
		}
		parse_str( $query, $input );
		try {
			return SearchCriteria::fromArray( $input );
		} catch ( \InvalidArgumentException $exception ) {
			return new \WP_Error( 'realestate_platform_invalid_url_state', $exception->getMessage(), array( 'status' => 400 ) );
		}
	}
	public function serialize( SearchCriteria $criteria ): string {
		$data = $criteria->canonical();
		foreach ( SearchCriteria::TAXONOMIES as $taxonomy ) {
			if ( isset( $data[ $taxonomy ] ) ) {
				$data[ $taxonomy ] = implode( ',', $data[ $taxonomy ] );
			}
		}
		ksort( $data );
		return http_build_query( $data, '', '&', PHP_QUERY_RFC3986 );
	}
}
