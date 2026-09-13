<?php
/**
 * PSR-4 style autoloader (no Composer required in production).
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS;

defined( 'ABSPATH' ) || exit;

/**
 * Maps EstatOS\Foo\Bar to includes/Foo/Bar.php.
 */
final class Autoloader {

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function load( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strncmp( $prefix, $class_name, strlen( $prefix ) ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = ESTAT_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
