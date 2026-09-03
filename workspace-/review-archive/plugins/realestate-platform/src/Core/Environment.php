<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Core;

use RuntimeException;
final class Environment {
	public const PHP_MIN = '8.1';
	public const WP_MIN  = '6.4';
	/** @return list<string> */
	public static function errors(): array {
		global $wp_version;
		$e = array();
		if ( version_compare( PHP_VERSION, self::PHP_MIN, '<' ) ) {
			$e[] = 'PHP ' . self::PHP_MIN . ' or newer is required.';
		} if ( isset( $wp_version ) && version_compare( (string) $wp_version, self::WP_MIN, '<' ) ) {
			$e[] = 'WordPress ' . self::WP_MIN . ' or newer is required.';
		} if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$e[] = 'WordPress Multisite is not supported in this release.';
		} return $e;
	} public static function assertSupported(): void {
		$e = self::errors();
		if ( $e ) {
			throw new RuntimeException( implode( ' ', $e ) );
		} }
}
