<?php
/** Bounded HTTPS client for future server-side geocoders. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Geo;

use Mayfair\RealEstatePlatform\Security\Security;
final class SecureGeoHttpClient {
	private const MAX_BYTES = 262144;
	/** @return array<string,mixed>|\WP_Error */
	public function getJson( string $url ): array|\WP_Error {
		$safe = Security::validateRemoteUrl( $url );
		if ( is_wp_error( $safe ) ) {
			return $safe;
		}
		$response = wp_safe_remote_get(
			$safe,
			array(
				'timeout'             => 5,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_BYTES,
				'headers'             => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'provider_unavailable', 'Geocoding provider is unavailable.' );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 300 && $code < 400 ) {
			return new \WP_Error( 'provider_redirect', 'Provider redirects are not followed.' );
		}
		if ( 200 !== $code ) {
			return new \WP_Error( 'provider_response', 'Geocoding provider returned an invalid response.' );
		}
		$type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
		$body = wp_remote_retrieve_body( $response );
		if ( ! str_contains( $type, 'application/json' ) || strlen( $body ) > self::MAX_BYTES ) {
			return new \WP_Error( 'provider_content', 'Geocoding provider content is invalid.' );
		}
		$data = json_decode( $body, true );
		return is_array( $data ) ? $data : new \WP_Error( 'provider_json', 'Geocoding provider JSON is invalid.' );
	}
}
