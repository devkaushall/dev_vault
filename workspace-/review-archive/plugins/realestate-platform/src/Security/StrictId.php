<?php
/**
 * Strict positive integer identifier parsing.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\Security;

final class StrictId {
	public static function parse( mixed $value ): int {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : 0;
		}
		if ( ! is_string( $value ) || '' === $value || ! ctype_digit( $value ) ) {
			return 0;
		}
		$digits = ltrim( $value, '0' );
		if ( '' === $digits ) {
			return 0;
		}
		$maximum = (string) PHP_INT_MAX;
		if ( strlen( $digits ) > strlen( $maximum ) || ( strlen( $digits ) === strlen( $maximum ) && strcmp( $digits, $maximum ) > 0 ) ) {
			return 0;
		}
		return (int) $digits;
	}
}
