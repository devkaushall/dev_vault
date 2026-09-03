<?php
/** @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Geo;
interface GeocoderInterface {
	/** @return array{latitude:float,longitude:float,label:string}|\WP_Error */
	public function forward( string $address ): array|\WP_Error;
	/** @return array{latitude:float,longitude:float,label:string}|\WP_Error */
	public function reverse( float $latitude, float $longitude ): array|\WP_Error;
}
