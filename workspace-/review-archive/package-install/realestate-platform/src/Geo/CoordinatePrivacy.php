<?php
/** Coordinate exposure policy. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Geo;
final class CoordinatePrivacy {
	public const MODES = array( 'exact', 'rounded', 'approximate', 'hidden' );
	/** @return array{latitude:float,longitude:float}|null */
	public function expose( float $latitude, float $longitude, string $mode ): ?array {
		if ( ! in_array( $mode, self::MODES, true ) ) {
			$mode = 'hidden';
		}
		return match ( $mode ) {
			'exact' => array(
				'latitude'  => $latitude,
				'longitude' => $longitude,
			),
			'rounded' => array(
				'latitude'  => round( $latitude, 3 ),
				'longitude' => round( $longitude, 3 ),
			),
			'approximate' => array(
				'latitude'  => round( $latitude, 2 ),
				'longitude' => round( $longitude, 2 ),
			),
			default => null,
		};
	}
}
