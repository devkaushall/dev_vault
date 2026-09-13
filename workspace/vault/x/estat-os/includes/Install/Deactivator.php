<?php
/**
 * Deactivation routine. Business data is never touched.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Install;

use EstatOS\Cron\Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Deactivation only stops scheduled work and clears rewrite rules.
 */
final class Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		Scheduler::unschedule();
		flush_rewrite_rules();
	}
}
