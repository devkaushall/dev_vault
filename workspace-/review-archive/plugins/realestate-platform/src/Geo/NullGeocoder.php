<?php
/** Disabled geocoder; coordinate search requires no provider. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Geo;
final class NullGeocoder implements GeocoderInterface {
	public function forward( string $address ): array|\WP_Error {
		unset( $address );
		return new \WP_Error( 'geocoder_disabled', 'Geocoding is not configured.' );}
	public function reverse( float $latitude, float $longitude ): array|\WP_Error {
		unset( $latitude, $longitude );
		return new \WP_Error( 'geocoder_disabled', 'Geocoding is not configured.' );}
}
