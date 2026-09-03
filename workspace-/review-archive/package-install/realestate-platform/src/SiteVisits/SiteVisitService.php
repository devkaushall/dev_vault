<?php
/** Canonical site-visit workflow service. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\SiteVisits;

use Mayfair\RealEstatePlatform\Forms\SubmissionValidator;
use Mayfair\RealEstatePlatform\Leads\LeadNotificationService;
use Mayfair\RealEstatePlatform\Leads\LeadService;

final class SiteVisitService {
	private const TRANSITIONS = array(
		'requested'            => array( 'scheduled', 'cancelled' ),
		'scheduled'            => array( 'confirmed', 'reschedule_requested', 'cancelled' ),
		'confirmed'            => array( 'completed', 'reschedule_requested', 'cancelled' ),
		'reschedule_requested' => array( 'scheduled', 'confirmed', 'cancelled' ),
		'completed'            => array(),
		'cancelled'            => array(),
	);

	public function __construct( private LeadService $leads, private LeadNotificationService $notifications, private SubmissionValidator $validator ) {}

	/** @return list<string> */
	public static function statuses(): array {
		return array_keys( self::TRANSITIONS );
	}

	/** @param array<string,mixed> $input @return array{id:int}|\WP_Error */
	public function createFromArray( array $input, int $actor_id ): array|\WP_Error {
		$request = SiteVisitRequest::fromArray( $input, $this->validator );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		return $this->create( $request, $actor_id );
	}

	/** @return array{id:int}|\WP_Error */
	public function create( SiteVisitRequest $request, int $actor_id ): array|\WP_Error {
		$assignment = $this->leads->propertyAssignment( $request->submission->property_id );
		if ( is_wp_error( $assignment ) ) {
			return $assignment;
		}
		global $wpdb;
		$dedupe = $this->dedupeKey( $request );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is an internal identifier; the dedupe key is prepared.
		$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'rep_site_visits WHERE dedupe_key=%s LIMIT 1', $dedupe ) );
		if ( $existing > 0 ) {
			return array( 'id' => $existing );
		}
		$lead_id = $request->lead_id;
		if ( $lead_id > 0 ) {
			if ( $actor_id < 1 ) {
				return $this->error( 'lead_forbidden', 403 );
			}
			$lead = $this->leads->get( $lead_id, $actor_id );
			if ( is_wp_error( $lead ) ) {
				return $lead;
			}
			if ( (int) ( $lead['property_id'] ?? 0 ) !== $request->submission->property_id ) {
				return $this->error( 'visit_context_mismatch', 409 );
			}
		} else {
			$created = $this->leads->create( $request->submission, $actor_id, 'site_visit' );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$lead_id = $created['id'];
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		$ok  = $wpdb->insert(
			$wpdb->prefix . 'rep_site_visits',
			array(
				'lead_id'             => $lead_id,
				'property_id'         => $request->submission->property_id,
				'requester_user_id'   => (int) ( $this->userIdFromLead( $lead_id ) ),
				'agent_id'            => $assignment['agent_id'],
				'agency_id'           => $assignment['agency_id'],
				'dedupe_key'          => $dedupe,
				'status'              => 'requested',
				'requested_start_at'  => $request->requested_start_at,
				'requested_end_at'    => $request->requested_end_at,
				'scheduled_start_at'  => null,
				'scheduled_end_at'    => null,
				'cancellation_reason' => '',
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $ok ) {
			// A concurrent replay can win the unique dedupe key between the read and insert.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is internal; the dedupe key is prepared.
			$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'rep_site_visits WHERE dedupe_key=%s LIMIT 1', $dedupe ) );
			return $existing > 0 ? array( 'id' => $existing ) : $this->error( 'visit_create_failed', 500 );
		}
		$visit_id = (int) $wpdb->insert_id;
		$history  = $wpdb->insert(
			$wpdb->prefix . 'rep_site_visit_history',
			array(
				'visit_id'      => $visit_id,
				'from_status'   => null,
				'to_status'     => 'requested',
				'actor_user_id' => max( 0, $actor_id ),
				'note'          => '',
				'created_at'    => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( false === $history ) {
			$wpdb->delete( $wpdb->prefix . 'rep_site_visits', array( 'id' => $visit_id ), array( '%d' ) );
			return $this->error( 'visit_create_failed', 500 );
		}
		$this->queue(
			'site_visit.requested',
			$visit_id,
			array(
				'lead_id'     => $lead_id,
				'property_id' => $request->submission->property_id,
			)
		);
		return array( 'id' => $visit_id );
	}

	/** @return array<string,mixed>|\WP_Error */
	public function get( int $id, int $actor_id ): array|\WP_Error {
		$row = $this->record( $id );
		if ( ! is_array( $row ) || ! $this->canView( $row, $actor_id ) ) {
			return $this->error( 'visit_not_found', 404 );
		}
		return $this->decorate( $row );
	}

	/** @return array<string,mixed>|\WP_Error */
	public function list( int $actor_id, int $page = 1, int $per_page = 20, string $status = '' ): array|\WP_Error {
		if ( ! $this->canManage( $actor_id ) ) {
			return $this->error( 'visit_forbidden', 403 );
		}
		if ( $page < 1 || $per_page < 1 || $per_page > 100 || ( '' !== $status && ! in_array( $status, self::statuses(), true ) ) ) {
			return $this->error( 'invalid_visit_query', 400 );
		}
		global $wpdb;
		$table  = $wpdb->prefix . 'rep_site_visits';
		$limit  = min( 100, $per_page );
		$offset = max( 0, ( $page - 1 ) * $per_page );
		if ( '' !== $status ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is an internal identifier; status and pagination values are prepared.
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table . ' WHERE status=%s', $status ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is an internal identifier; status and pagination values are prepared.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE status=%s ORDER BY id DESC LIMIT %d OFFSET %d', $status, $limit, $offset ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is an internal plugin identifier and no user value is present.
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is an internal plugin identifier; pagination is normalized and bounded.
			$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}", ARRAY_A );
		}
		$items = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( is_array( $row ) ) {
				$items[] = $this->decorate( $row, false );
			}
		}
		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/** @return array<string,mixed>|\WP_Error */
	public function transition( int $id, string $to_status, int $actor_id, string $note = '' ): array|\WP_Error {
		if ( ! $this->canEdit( $actor_id ) || ! in_array( $to_status, self::statuses(), true ) ) {
			return $this->error( 'visit_transition_forbidden', 403 );
		}
		$row = $this->record( $id );
		if ( ! is_array( $row ) ) {
			return $this->error( 'visit_not_found', 404 );
		}
		$from = (string) $row['status'];
		if ( ! in_array( $to_status, self::TRANSITIONS[ $from ] ?? array(), true ) ) {
			return $this->error( 'invalid_visit_transition', 409 );
		}
		if ( strlen( $note ) > 1000 ) {
			return $this->error( 'invalid_visit_note', 400 );
		}
		$note                     = sanitize_textarea_field( $note );
		$now                      = gmdate( 'Y-m-d H:i:s' );
		$previous_scheduled_start = (string) ( $row['scheduled_start_at'] ?? '' );
		$previous_scheduled_end   = (string) ( $row['scheduled_end_at'] ?? '' );
		global $wpdb;
		if ( 'scheduled' === $to_status ) {
			$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'rep_site_visits SET status=%s,scheduled_start_at=%s,scheduled_end_at=%s,updated_at=%s,cancellation_reason=%s WHERE id=%d AND status=%s', $to_status, $row['requested_start_at'], $row['requested_end_at'], $now, 'cancelled' === $to_status ? $note : (string) $row['cancellation_reason'], $id, $from ) );
		} else {
			$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'rep_site_visits SET status=%s,updated_at=%s,cancellation_reason=%s WHERE id=%d AND status=%s', $to_status, $now, 'cancelled' === $to_status ? $note : (string) $row['cancellation_reason'], $id, $from ) );
		}
		if ( 1 !== (int) $updated ) {
			return $this->error( 'visit_state_conflict', 409 );
		}
		$history = $wpdb->insert(
			$wpdb->prefix . 'rep_site_visit_history',
			array(
				'visit_id'      => $id,
				'from_status'   => $from,
				'to_status'     => $to_status,
				'actor_user_id' => max( 0, $actor_id ),
				'note'          => $note,
				'created_at'    => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( false === $history ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'rep_site_visits SET status=%s,scheduled_start_at=%s,scheduled_end_at=%s,updated_at=%s WHERE id=%d', $from, $previous_scheduled_start, $previous_scheduled_end, $now, $id ) );
			return $this->error( 'visit_transition_failed', 500 );
		}
		$this->queue(
			'site_visit.' . $to_status,
			$id,
			array(
				'from' => $from,
				'to'   => $to_status,
			)
		);
		return $this->get( $id, $actor_id );
	}

	/** @return array<string,mixed>|\WP_Error */
	public function reschedule( int $id, string $start, string $end, int $actor_id ): array|\WP_Error {
		if ( ! $this->canEdit( $actor_id ) ) {
			return $this->error( 'visit_transition_forbidden', 403 );
		}
		$window = SiteVisitRequest::parseWindow( $start, $end );
		if ( is_wp_error( $window ) ) {
			return $window;
		}
		$row = $this->record( $id );
		if ( ! is_array( $row ) ) {
			return $this->error( 'visit_not_found', 404 );
		}
		$from = (string) $row['status'];
		if ( ! in_array( $from, array( 'scheduled', 'confirmed', 'reschedule_requested' ), true ) ) {
			return $this->error( 'invalid_visit_transition', 409 );
		}
		$to  = 'reschedule_requested' === $from ? $this->rescheduleReturnStatus( $id ) : $from;
		$now = gmdate( 'Y-m-d H:i:s' );
		global $wpdb;
		$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'rep_site_visits SET requested_start_at=%s,requested_end_at=%s,scheduled_start_at=%s,scheduled_end_at=%s,status=%s,updated_at=%s WHERE id=%d AND status=%s', $window['start'], $window['end'], $window['start'], $window['end'], $to, $now, $id, $from ) );
		if ( 1 !== (int) $updated ) {
			return $this->error( 'visit_reschedule_failed', 409 );
		}
		$history = $wpdb->insert(
			$wpdb->prefix . 'rep_site_visit_history',
			array(
				'visit_id'      => $id,
				'from_status'   => $from,
				'to_status'     => $to,
				'actor_user_id' => max( 0, $actor_id ),
				'note'          => 'Rescheduled.',
				'created_at'    => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( false === $history ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'rep_site_visits SET requested_start_at=%s,requested_end_at=%s,scheduled_start_at=%s,scheduled_end_at=%s,status=%s,updated_at=%s WHERE id=%d', $row['requested_start_at'], $row['requested_end_at'], $row['scheduled_start_at'], $row['scheduled_end_at'], $from, $now, $id ) );
			return $this->error( 'visit_reschedule_failed', 500 );
		}
		$this->queue(
			'site_visit.rescheduled',
			$id,
			array(
				'start' => $window['start'],
				'end'   => $window['end'],
			)
		);
		return $this->get( $id, $actor_id );
	}

	public function cleanupUser( int $user_id ): void {
		if ( $user_id < 1 ) {
			return;
		}
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		// Cancel queued visit notices before removing the requester relationship.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal table names; the user ID is prepared.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}rep_notification_events SET status=%s,payload_json=%s,recipient_user_id=%d,recipient_email=%s,last_error=%s,updated_at=%s WHERE aggregate_type=%s AND aggregate_id IN (SELECT id FROM {$wpdb->prefix}rep_site_visits WHERE requester_user_id=%d)", 'cancelled', '{}', 0, '', 'Privacy erasure requested.', $now, 'site_visit', $user_id ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'rep_site_visits SET requester_user_id=0,dedupe_key=NULL,updated_at=%s WHERE requester_user_id=%d', $now, $user_id ) );
	}

	public function cleanupProfile( int $post_id ): void {
		if ( $post_id < 1 || ! in_array( get_post_type( $post_id ), array( 'agent', 'agency' ), true ) ) {
			return;
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'rep_site_visits SET agent_id=NULL,agency_id=NULL,updated_at=%s WHERE agent_id=%d OR agency_id=%d', gmdate( 'Y-m-d H:i:s' ), $post_id, $post_id ) );
	}

	public function cleanupProperty( int $post_id ): void {
		if ( $post_id < 1 ) {
			return;
		}
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}rep_site_visits SET property_id=NULL,status=CASE WHEN status IN ('requested','scheduled','confirmed','reschedule_requested') THEN 'cancelled' ELSE status END,cancellation_reason=%s,updated_at=%s WHERE property_id=%d", 'Property removed.', $now, $post_id ) );
	}

	/** @return list<array<string,mixed>> */
	public function exportForEmail( string $email, int $user_id ): array {
		global $wpdb;
		$email = sanitize_email( $email );
		if ( $user_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table names are internal identifiers; both data values are prepared.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT v.id,v.lead_id,v.property_id,v.status,v.requested_start_at,v.requested_end_at,v.scheduled_start_at,v.scheduled_end_at,v.created_at,v.updated_at FROM ' . $wpdb->prefix . 'rep_site_visits AS v INNER JOIN ' . $wpdb->prefix . 'rep_leads AS l ON l.id=v.lead_id WHERE l.user_id=%d OR l.email=%s ORDER BY v.id', $user_id, $email ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table names are internal identifiers; the email value is prepared.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT v.id,v.lead_id,v.property_id,v.status,v.requested_start_at,v.requested_end_at,v.scheduled_start_at,v.scheduled_end_at,v.created_at,v.updated_at FROM ' . $wpdb->prefix . 'rep_site_visits AS v INNER JOIN ' . $wpdb->prefix . 'rep_leads AS l ON l.id=v.lead_id WHERE l.email=%s ORDER BY v.id', $email ), ARRAY_A );
		}
		return is_array( $rows ) ? $rows : array();
	}

	public function eraseForEmail( string $email, int $user_id ): int {
		global $wpdb;
		$email = sanitize_email( $email );
		if ( $user_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is an internal identifier; both data values are prepared.
			$lead_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'rep_leads WHERE user_id=%d OR email=%s', $user_id, $email ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is an internal identifier; the email value is prepared.
			$lead_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'rep_leads WHERE email=%s', $email ) );
		}
		$lead_ids = array_values( array_filter( array_map( 'intval', is_array( $lead_ids ) ? $lead_ids : array() ) ) );
		if ( empty( $lead_ids ) ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $lead_ids ), '%d' ) );
		$now          = gmdate( 'Y-m-d H:i:s' );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders -- Placeholder lists are generated locally and every ID is normalized and prepared.
		$visit_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}rep_site_visits WHERE lead_id IN ({$placeholders})", ...$lead_ids ) );
		$visit_ids = array_values( array_filter( array_map( 'intval', is_array( $visit_ids ) ? $visit_ids : array() ) ) );
		if ( empty( $visit_ids ) ) {
			return 0;
		}
		$visit_placeholders = implode( ',', array_fill( 0, count( $visit_ids ), '%d' ) );
		$result             = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}rep_site_visits SET requester_user_id=0,status=CASE WHEN status IN ('requested','scheduled','confirmed','reschedule_requested') THEN 'cancelled' ELSE status END,cancellation_reason=%s,dedupe_key=NULL,updated_at=%s WHERE id IN ({$visit_placeholders})", ...array_merge( array( 'Privacy erasure requested.', $now ), $visit_ids ) ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}rep_site_visit_history SET note='' WHERE visit_id IN ({$visit_placeholders})", ...$visit_ids ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}rep_notification_events SET status=%s,payload_json=%s,recipient_user_id=%d,recipient_email=%s,last_error=%s,updated_at=%s WHERE aggregate_type=%s AND aggregate_id IN ({$visit_placeholders})", ...array_merge( array( 'cancelled', '{}', 0, '', 'Privacy erasure requested.', $now, 'site_visit' ), $visit_ids ) ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders
		return $result;
	}

	private function dedupeKey( SiteVisitRequest $request ): string {
		return hash( 'sha256', strtolower( $request->submission->email ) . '|visit|' . $request->submission->property_id . '|' . $request->requested_start_at . '|' . $request->requested_end_at . '|' . $request->submission->idempotency_key );
	}

	private function rescheduleReturnStatus( int $visit_id ): string {
		global $wpdb;
		$previous = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT from_status FROM ' . $wpdb->prefix . 'rep_site_visit_history WHERE visit_id=%d AND to_status=%s ORDER BY id DESC LIMIT 1', $visit_id, 'reschedule_requested' ) );
		return in_array( $previous, array( 'scheduled', 'confirmed' ), true ) ? $previous : 'scheduled';
	}

	private function userIdFromLead( int $lead_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . $wpdb->prefix . 'rep_leads WHERE id=%d', $lead_id ) );
	}

	private function record( int $id ): array|false {
		if ( $id < 1 ) {
			return false;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'rep_site_visits WHERE id=%d LIMIT 1', $id ), ARRAY_A );
		return is_array( $row ) ? $row : false;
	}

	/** @param array<string,mixed> $row */
	private function canView( array $row, int $actor_id ): bool {
		if ( $actor_id < 1 ) {
			return false;
		}
		if ( $this->canManage( $actor_id ) || (int) ( $row['requester_user_id'] ?? 0 ) === $actor_id ) {
			return true;
		}
		foreach ( array( 'agent_id', 'agency_id' ) as $key ) {
			$post = get_post( (int) ( $row[ $key ] ?? 0 ) );
			if ( $post instanceof \WP_Post && (int) $post->post_author === $actor_id ) {
				return true;
			}
		}
		return false;
	}

	private function canManage( int $actor_id ): bool {
		return $actor_id > 0 && ( current_user_can( 'manage_site_visits' ) || current_user_can( 'manage_leads' ) || current_user_can( 'view_site_visits' ) );
	}

	private function canEdit( int $actor_id ): bool {
		return $actor_id > 0 && ( current_user_can( 'manage_site_visits' ) || current_user_can( 'manage_leads' ) || current_user_can( 'edit_leads' ) );
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private function decorate( array $row, bool $include_history = true ): array {
		$data = array(
			'id'                  => (int) $row['id'],
			'lead_id'             => (int) $row['lead_id'],
			'property_id'         => (int) $row['property_id'] > 0 ? (int) $row['property_id'] : null,
			'agent_id'            => (int) $row['agent_id'] > 0 ? (int) $row['agent_id'] : null,
			'agency_id'           => (int) $row['agency_id'] > 0 ? (int) $row['agency_id'] : null,
			'status'              => (string) $row['status'],
			'requested_start_at'  => (string) $row['requested_start_at'],
			'requested_end_at'    => (string) $row['requested_end_at'],
			'scheduled_start_at'  => '' !== (string) $row['scheduled_start_at'] ? (string) $row['scheduled_start_at'] : null,
			'scheduled_end_at'    => '' !== (string) $row['scheduled_end_at'] ? (string) $row['scheduled_end_at'] : null,
			'cancellation_reason' => (string) $row['cancellation_reason'],
			'created_at'          => (string) $row['created_at'],
			'updated_at'          => (string) $row['updated_at'],
		);
		if ( $include_history ) {
			global $wpdb;
			$history         = $wpdb->get_results( $wpdb->prepare( 'SELECT id,from_status,to_status,note,created_at FROM ' . $wpdb->prefix . 'rep_site_visit_history WHERE visit_id=%d ORDER BY id ASC', (int) $row['id'] ), ARRAY_A );
			$data['history'] = is_array( $history ) ? $history : array();
		}
		return $data;
	}

	/** @param array<string,mixed> $payload */
	private function queue( string $event, int $visit_id, array $payload ): void {
		$recipient = sanitize_email( (string) get_option( 'admin_email', '' ) );
		$this->notifications->enqueue( $event, 'site_visit', $visit_id, $payload, 0, $recipient, hash( 'sha256', $event . '|site_visit|' . $visit_id . '|' . (string) wp_json_encode( $payload ) ) );
	}

	private function error( string $code, int $status ): \WP_Error {
		return new \WP_Error( $code, 'Site-visit request could not be completed.', array( 'status' => $status ) );
	}
}
