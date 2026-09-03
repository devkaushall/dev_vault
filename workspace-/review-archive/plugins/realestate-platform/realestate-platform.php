<?php
/**
 * Plugin Name: RealEstate Platform
 * Plugin URI: https://www.mayfairproperties.in/realestate-platform/
 * Description: Secure, extensible real-estate platform foundation for WordPress.
 * Version: 0.9.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Mayfair Properties & Developers
 * License: GPL-2.0-or-later
 * Text Domain: realestate-platform
 * Domain Path: /languages
 */
declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
define( 'REALESTATE_PLATFORM_VERSION', '0.9.0' );
define( 'REALESTATE_PLATFORM_DB_VERSION', '004' );
define( 'REALESTATE_PLATFORM_FILE', __FILE__ );
define( 'REALESTATE_PLATFORM_DIR', plugin_dir_path( __FILE__ ) );
$autoload = REALESTATE_PLATFORM_DIR . 'vendor/autoload.php';
if ( is_readable( $autoload ) ) {
	require $autoload;
} else {
	require REALESTATE_PLATFORM_DIR . 'src/Core/Autoloader.php';
	\Mayfair\RealEstatePlatform\Core\Autoloader::register(); }
\Mayfair\RealEstatePlatform\Core\Lifecycle::maybeUpgrade();
register_activation_hook( __FILE__, array( \Mayfair\RealEstatePlatform\Core\Lifecycle::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Mayfair\RealEstatePlatform\Core\Lifecycle::class, 'deactivate' ) );
\Mayfair\RealEstatePlatform\Core\Bootstrap::instance()->register();
