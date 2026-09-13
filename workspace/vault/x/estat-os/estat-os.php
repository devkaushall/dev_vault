<?php
/**
 * Plugin Name:       Estat.OS — Real Estate Operating System
 * Plugin URI:        https://github.com/devkaushall
 * Description:       A complete, beginner-friendly operating system for a real-estate office: listings, projects, team, enquiries, site visits, forms, reports and a documented API.
 * Version:           2.6.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            DEVil
 * Author URI:        https://github.com/devkaushall
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       estat-os
 * Domain Path:       /languages
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS;

defined( 'ABSPATH' ) || exit;

define( 'ESTAT_VERSION', '2.6.0' );
define( 'ESTAT_DB_VERSION', '4' );
define( 'ESTAT_FILE', __FILE__ );
define( 'ESTAT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ESTAT_URL', plugin_dir_url( __FILE__ ) );
define( 'ESTAT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum supported platform.
 *
 * The codebase is verified against PHP 7.4 syntax, so the floor is set where
 * the plugin genuinely runs rather than at an arbitrarily higher version. If
 * the host is older, show a notice instead of crashing the whole site.
 */
const ESTAT_MIN_PHP = '7.4';
const ESTAT_MIN_WP  = '6.0';

if ( version_compare( PHP_VERSION, ESTAT_MIN_PHP, '<' ) || version_compare( get_bloginfo( 'version' ), ESTAT_MIN_WP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: 1: required PHP version, 2: required WordPress version. */
				esc_html__( 'Estat.OS needs PHP %1$s or newer and WordPress %2$s or newer. Please ask your host to update, then activate the plugin again.', 'estat-os' ),
				esc_html( ESTAT_MIN_PHP ),
				esc_html( ESTAT_MIN_WP )
			);
			echo '</p></div>';
		}
	);
	return;
}

require_once ESTAT_DIR . 'includes/Autoloader.php';
Autoloader::register();

register_activation_hook( __FILE__, array( Install\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Install\Deactivator::class, 'deactivate' ) );

/**
 * Main plugin accessor.
 *
 * @return Plugin
 */
function estat(): Plugin {
	return Plugin::instance();
}

estat()->boot();
