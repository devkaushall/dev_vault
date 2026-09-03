<?php
/**
 * Optional, read-only ACF value adapter.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Compatibility;

final class AcfValueAdapter {
	public function available(): bool {
		return function_exists( 'get_field' );
	}

	public function read( string $source_key, int $post_id, mixed $fallback = null ): mixed {
		if ( ! $this->available() || ! preg_match( '/^[A-Za-z0-9_-]+$/', $source_key ) ) {
			return $fallback;
		}
		$value = get_field( $source_key, $post_id, false );
		return false === $value || null === $value ? $fallback : $value;
	}
}
