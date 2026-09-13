<?php
/**
 * Versioned, idempotent, non-destructive upgrades.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Install;

use EstatOS\Audit\AuditLog;
use EstatOS\Data\Taxonomies;
use EstatOS\Cron\Scheduler;
use EstatOS\Security\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Migrations only ever add. Stored data never silently changes meaning; if a
 * value must change shape, a new key is introduced alongside the old one.
 */
final class Migrator {

	/**
	 * Run any pending upgrade steps.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$stored_db = (string) get_option( 'estat_db_version', '0' );
		$stored_pl = (string) get_option( 'estat_version', '0' );

		if ( $stored_db === ESTAT_DB_VERSION && $stored_pl === ESTAT_VERSION ) {
			return;
		}

		// Tables and roles are always re-synced; both operations are safe repeats.
		Schema::install();
		Roles::install();
		Scheduler::schedule();

		if ( version_compare( $stored_db, ESTAT_DB_VERSION, '<' ) ) {
			self::run_steps( $stored_db );
			update_option( 'estat_db_version', ESTAT_DB_VERSION, false );
		}

		if ( $stored_pl !== ESTAT_VERSION ) {
			update_option( 'estat_version', ESTAT_VERSION, false );
			AuditLog::record( 'plugin.upgraded', 'plugin', 0, array( 'from' => $stored_pl, 'to' => ESTAT_VERSION ) );
		}
	}

	/**
	 * Run numbered migration steps newer than the stored version.
	 *
	 * @param string $from Stored database version.
	 * @return void
	 */
	private static function run_steps( string $from ): void {
		$steps = array(
			'2' => array( __CLASS__, 'step_2' ),
			'3' => array( __CLASS__, 'step_3' ),
			'4' => array( __CLASS__, 'step_4' ),
		);
		foreach ( $steps as $version => $callback ) {
			if ( version_compare( $from, (string) $version, '<' ) && is_callable( $callback ) ) {
				call_user_func( $callback );
			}
		}
	}

	/**
	 * Version 2: the office can now publish blogs, articles and insights,
	 * which needs a new permission and its starter categories. Nothing is
	 * removed or rewritten, so this is safe to repeat.
	 *
	 * @return void
	 */
	private static function step_3(): void {
		// Version 3 fixes a permission fault that made most of the Office menu
		// disappear. Roles::install() has already run and adds the corrected
		// permissions; the stored menu positions just need refreshing so the
		// entries come back without the site owner doing anything.
		delete_option( 'estat_index_repair_offset' );

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Version 4: the change-request table.
	 *
	 * Agents working from a phone can propose changes to properties that are
	 * not theirs, and those wait here for the office. An existing site has no
	 * such table, so it has to be created on upgrade rather than only on a
	 * fresh install.
	 *
	 * @return void
	 */
	private static function step_4(): void {
		// install() is written to be safe to run again: dbDelta creates what
		// is missing and leaves what is already correct alone.
		Schema::install();
	}

	/**
	 * Version 2: the office can now publish blogs, articles and insights.
	 *
	 * @return void
	 */
	private static function step_2(): void {
		// Roles::install() has already run above and grants the new permission.
		if ( is_callable( array( Taxonomies::class, 'seed_insight_kinds' ) ) ) {
			Taxonomies::seed_insight_kinds();
		}
	}
}
