<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Core;

final class Autoloader {
	public static function register(): void {
		spl_autoload_register(
			static function ( string $class_name ): void {
				$prefix = 'Mayfair\\RealEstatePlatform\\';
				if ( ! str_starts_with( $class_name, $prefix ) ) {
					return;
				} $file = dirname( __DIR__ ) . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
				if ( is_readable( $file ) ) {
					require $file;
				} }
		); }
}
