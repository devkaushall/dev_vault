<?php
/**
 * Scheduled housekeeping. Every job is safe to run twice.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Cron;

use EstatOS\Audit\AuditLog;
use EstatOS\Data\PostTypes;
use EstatOS\Leads\Leads;
use EstatOS\Leads\Visits;
use EstatOS\Notifications\Notifier;
use EstatOS\Search\SearchIndex;
use EstatOS\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Housekeeping never deletes a business record. Expired listings are moved to
 * draft, old enquiries are anonymised, and the index is repaired, not rebuilt
 * destructively.
 */
final class Scheduler {

	/**
	 * Daily job hook.
	 */
	public const DAILY = 'estat_daily_maintenance';

	/**
	 * Hourly job hook.
	 */
	public const HOURLY = 'estat_hourly_maintenance';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::DAILY, array( __CLASS__, 'run_daily' ) );
		add_action( self::HOURLY, array( __CLASS__, 'run_hourly' ) );
	}

	/**
	 * Schedule jobs idempotently.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::DAILY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY );
		}
		if ( ! wp_next_scheduled( self::HOURLY ) ) {
			wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'hourly', self::HOURLY );
		}
	}

	/**
	 * Remove scheduled jobs.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		foreach ( array( self::DAILY, self::HOURLY, \EstatOS\Webhooks\Webhooks::RETRY_HOOK ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Daily housekeeping.
	 *
	 * @return void
	 */
	public static function run_daily(): void {
		$expired = self::expire_listings();
		if ( $expired > 0 ) {
			Notifier::listings_expired( $expired );
		}

		$retention = (int) Settings::get( 'retention_days', 0 );
		if ( $retention > 0 ) {
			Leads::apply_retention( $retention );
			AuditLog::purge_older_than( max( $retention, 365 ) );
		}

		$due = Leads::counts()['due'];
		Notifier::followups_due( $due );

		self::repair_index();

		AuditLog::record( 'maintenance.run', 'cron', 0, array( 'job' => 'daily', 'expired' => $expired ) );
	}

	/**
	 * Hourly housekeeping: visit reminders.
	 *
	 * @return void
	 */
	public static function run_hourly(): void {
		foreach ( Visits::due_reminders() as $visit ) {
			Notifier::new_visit( (int) $visit['id'] );
			Visits::mark_reminded( (int) $visit['id'] );
		}
	}

	/**
	 * Move listings past their removal date back to draft.
	 *
	 * @return int Number of listings taken offline.
	 */
	public static function expire_listings(): int {
		$today = current_time( 'Y-m-d' );
		$ids   = get_posts(
			array(
				'post_type'      => PostTypes::LISTING,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => '_estat_expiry_date',
						'value'   => $today,
						'compare' => '<',
						'type'    => 'DATE',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			wp_update_post( array( 'ID' => (int) $id, 'post_status' => 'draft' ) );
		}
		return count( $ids );
	}

	/**
	 * Repair a slice of the search index each day.
	 *
	 * @return void
	 */
	public static function repair_index(): void {
		$status = SearchIndex::status();
		if ( $status['in_sync'] ) {
			return;
		}
		$offset = (int) get_option( 'estat_index_repair_offset', 0 );
		$done   = SearchIndex::rebuild_batch( 200, $offset );
		update_option( 'estat_index_repair_offset', $done < 200 ? 0 : $offset + 200, false );
	}
}
