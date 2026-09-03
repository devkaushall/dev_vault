<?php
/**
 * Provider-neutral location normalization.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Locations;

use InvalidArgumentException;

final class LocationNormalizer {
	/** @param array<string, mixed> $location
	 * @return array<string, mixed> */
	public function normalize( array $location ): array {
		$out = array();
		foreach ( array( 'country', 'state', 'city', 'locality', 'neighborhood', 'micro_market', 'postal_code' ) as $key ) {
			$out[ $key ] = isset( $location[ $key ] ) && '' !== $location[ $key ] ? sanitize_text_field( (string) $location[ $key ] ) : null;
		}
		$out['latitude']  = $this->coordinate( $location['latitude'] ?? null, -90, 90, 'latitude' );
		$out['longitude'] = $this->coordinate( $location['longitude'] ?? null, -180, 180, 'longitude' );
		return $out;
	}

	private function coordinate( mixed $value, float $minimum, float $maximum, string $name ): ?float {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_numeric( $value ) || (float) $value < $minimum || (float) $value > $maximum ) {
			throw new InvalidArgumentException( "Invalid {$name}." );
		}
		return (float) $value;
	}
}
