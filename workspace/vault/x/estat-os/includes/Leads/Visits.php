<?php
/**
 * Site visit management.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Leads;

use EstatOS\Audit\AuditLog;
use EstatOS\Install\Schema;
use EstatOS\Settings\Settings;
use EstatOS\Support\Sanitize;
use EstatOS\Support\Vocabulary;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A visit connects a lead, a property and a team member at a point in time.
 * The same combination at the same time is stored once.
 */
final class Visits {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {}

	/**
	 * Schedule a visit.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return int|WP_Error Visit ID.
	 */
	public static function schedule( array $input ) {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return new WP_Error( 'estat_no_storage', __( 'The office database is not ready yet.', 'estat-os' ) );
		}

		$lead_id    = Sanitize::int( $input['lead_id'] ?? 0 );
		$listing_id = Sanitize::int( $input['listing_id'] ?? 0 );
		$agent_id   = Sanitize::int( $input['agent_id'] ?? 0 );
		$when       = Sanitize::datetime( $input['scheduled_at'] ?? '' );

		if ( '' === $when ) {
			return new WP_Error( 'estat_invalid_scheduled_at', __( 'Please choose the date and time of the visit.', 'estat-os' ) );
		}
		if ( $lead_id <= 0 && $listing_id <= 0 ) {
			return new WP_Error( 'estat_invalid_visit', __( 'A visit needs either an enquiry or a property.', 'estat-os' ) );
		}
		if ( strtotime( $when ) < time() - DAY_IN_SECONDS ) {
			return new WP_Error( 'estat_invalid_scheduled_at', __( 'That date is in the past. Please pick a future date and time.', 'estat-os' ) );
		}

		$hash  = hash( 'sha256', $lead_id . '|' . $listing_id . '|' . $when );
		$table = Schema::table( 'estat_visits' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE dedupe_hash = %s", $hash ) );
		if ( $existing ) {
			return $existing;
		}

		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert(
			$table,
			array(
				'created_at'   => $now,
				'updated_at'   => $now,
				'lead_id'      => $lead_id,
				'listing_id'   => $listing_id,
				'agent_id'     => $agent_id,
				'scheduled_at' => $when,
				'outcome'      => 'pending',
				'notes'        => Sanitize::textarea( $input['notes'] ?? '' ),
				'practice'     => Settings::get( 'practice_mode' ) ? 1 : 0,
				'dedupe_hash'  => $hash,
			)
		);
		if ( ! $ok ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE dedupe_hash = %s", $hash ) );
			if ( $existing ) {
				return $existing;
			}
			return new WP_Error( 'estat_save_failed', __( 'We could not save this visit. Please try again.', 'estat-os' ) );
		}

		$visit_id = (int) $wpdb->insert_id;

		if ( $lead_id > 0 ) {
			Leads::update( $lead_id, array( 'status' => 'visit_scheduled' ) );
		}

		AuditLog::record( 'visit.scheduled', 'visit', $visit_id, array( 'listing_id' => $listing_id ) );

		/**
		 * Fires after a visit is scheduled.
		 *
		 * @param int $visit_id Visit ID.
		 */
		do_action( 'estat_visit_scheduled', $visit_id );

		return $visit_id;
	}

	/**
	 * Update outcome or notes.
	 *
	 * @param int                 $visit_id Visit ID.
	 * @param array<string,mixed> $input    Fields.
	 * @return bool|WP_Error
	 */
	public static function update( int $visit_id, array $input ) {
		global $wpdb;
		$visit = self::get( $visit_id );
		if ( ! $visit ) {
			return new WP_Error( 'estat_not_found', __( 'That visit could not be found.', 'estat-os' ) );
		}
		$fields = array( 'updated_at' => current_time( 'mysql', true ) );
		if ( isset( $input['outcome'] ) ) {
			$fields['outcome'] = Sanitize::choice( $input['outcome'], Vocabulary::keys( 'visit_outcomes' ), (string) $visit['outcome'] );
		}
		if ( isset( $input['notes'] ) ) {
			$fields['notes'] = Sanitize::textarea( $input['notes'] );
		}
		if ( isset( $input['agent_id'] ) ) {
			$fields['agent_id'] = Sanitize::int( $input['agent_id'] );
		}
		if ( isset( $input['scheduled_at'] ) ) {
			$when = Sanitize::datetime( $input['scheduled_at'] );
			if ( '' !== $when ) {
				$fields['scheduled_at'] = $when;
				$fields['dedupe_hash']  = hash( 'sha256', (int) $visit['lead_id'] . '|' . (int) $visit['listing_id'] . '|' . $when );
			}
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( Schema::table( 'estat_visits' ), $fields, array( 'id' => $visit_id ) );
		AuditLog::record( 'visit.updated', 'visit', $visit_id, array( 'outcome' => $fields['outcome'] ?? $visit['outcome'] ) );

		if ( isset( $fields['outcome'] ) && 'done' === $fields['outcome'] && (int) $visit['lead_id'] > 0 ) {
			Leads::append_note( (int) $visit['lead_id'], __( 'Site visit completed.', 'estat-os' ) );
		}
		return true;
	}

	/**
	 * Fetch one visit.
	 *
	 * @param int $visit_id Visit ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( int $visit_id ): ?array {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return null;
		}
		$table = Schema::table( 'estat_visits' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $visit_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * List visits.
	 *
	 * @param array<string,mixed> $args Filters: outcome, agent_id, from, to, page, per_page.
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int,page:int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;
		$out = array( 'items' => array(), 'total' => 0, 'pages' => 0, 'page' => 1 );
		if ( ! Schema::healthy() ) {
			return $out;
		}
		$table    = Schema::table( 'estat_visits' );
		$per_page = Sanitize::int( $args['per_page'] ?? 20, 1, 100 );
		$page     = Sanitize::int( $args['page'] ?? 1, 1, 10000 );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['outcome'] ) ) {
			$where[]  = 'outcome = %s';
			$params[] = Sanitize::choice( $args['outcome'], Vocabulary::keys( 'visit_outcomes' ), 'pending' );
		}
		if ( ! empty( $args['agent_id'] ) ) {
			$where[]  = 'agent_id = %d';
			$params[] = Sanitize::int( $args['agent_id'] );
		}
		if ( ! empty( $args['lead_id'] ) ) {
			$where[]  = 'lead_id = %d';
			$params[] = Sanitize::int( $args['lead_id'] );
		}
		if ( ! empty( $args['from'] ) ) {
			$where[]  = 'scheduled_at >= %s';
			$params[] = Sanitize::datetime( $args['from'] );
		}
		if ( ! empty( $args['to'] ) ) {
			$where[]  = 'scheduled_at <= %s';
			$params[] = Sanitize::datetime( $args['to'] );
		}
		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );
		$items     = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY scheduled_at ASC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
			'page'  => $page,
		);
	}

	/**
	 * Counts for the Today screen.
	 *
	 * @return array<string,int>
	 */
	public static function counts(): array {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return array( 'today' => 0, 'upcoming' => 0, 'pending' => 0 );
		}
		$table = Schema::table( 'estat_visits' );
		$start = get_gmt_from_date( current_time( 'Y-m-d' ) . ' 00:00:00' );
		$end   = get_gmt_from_date( current_time( 'Y-m-d' ) . ' 23:59:59' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'today'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE scheduled_at BETWEEN %s AND %s AND outcome = 'pending'", $start, $end ) ),
			'upcoming' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE scheduled_at > %s AND outcome = 'pending'", $end ) ),
			'pending'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE outcome = 'pending'" ),
		);
		// phpcs:enable
	}

	/**
	 * Visits needing a reminder in the next 24 hours.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function due_reminders(): array {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return array();
		}
		$table = Schema::table( 'estat_visits' );
		$now   = current_time( 'mysql', true );
		$limit = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE outcome = 'pending' AND reminder_sent = 0 AND scheduled_at BETWEEN %s AND %s LIMIT 50", $now, $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Mark a reminder as sent (idempotent).
	 *
	 * @param int $visit_id Visit ID.
	 * @return void
	 */
	public static function mark_reminded( int $visit_id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( Schema::table( 'estat_visits' ), array( 'reminder_sent' => 1 ), array( 'id' => $visit_id ), array( '%d' ), array( '%d' ) );
	}
}
