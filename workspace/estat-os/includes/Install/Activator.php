<?php
/**
 * Activation routine. Everything here is idempotent.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Install;

use EstatOS\Cron\Scheduler;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Taxonomies;
use EstatOS\Forms\Forms;
use EstatOS\Security\Roles;
use EstatOS\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Running activation twice must not duplicate roles, terms, tables or forms.
 */
final class Activator {

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Schema::install();
		Roles::install();

		PostTypes::register_post_types();
		Taxonomies::register_taxonomies();
		Taxonomies::seed();

		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', false );
		}

		Forms::seed();
		Scheduler::schedule();

		// A first install should land on the welcome screen, so the office is
		// not left guessing which of thirteen menus to open. An upgrade of an
		// office that is already running must never be interrupted by it.
		$fresh = false === get_option( 'estat_installed_at', false );
		if ( $fresh ) {
			add_option( 'estat_show_setup', '1', '', false );
		}

		update_option( 'estat_db_version', ESTAT_DB_VERSION, false );
		update_option( 'estat_version', ESTAT_VERSION, false );
		if ( false === get_option( 'estat_installed_at', false ) ) {
			add_option( 'estat_installed_at', current_time( 'mysql', true ), '', false );
		}

		flush_rewrite_rules();
	}
}
