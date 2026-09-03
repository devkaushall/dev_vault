<?php
/** Canonical Phase 7 Lead Engine. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Leads;

use Mayfair\RealEstatePlatform\Forms\Submission;

final class LeadService {
	private const REQUEST_TYPES = array( 'inquiry', 'contact', 'site_visit' );
	private const SOURCES       = array( 'rest', 'ajax', 'elementor', 'website', 'admin', 'mayfair', 'unknown' );
	private const TRANSITIONS   = array(
		'new'       => array( 'contacted', 'lost', 'spam' ),
		'contacted' => array( 'qualified', 'lost', 'spam' ),
		'qualified' => array( 'converted', 'lost' ),
		'converted' => array(),
		'lost'      => array(),
		'spam'      => array(),
	);

	public function __construct( private LeadNotificationService $notifications ) {}

	/** @return list<string> */
	public static function statuses(): array {
		return array_keys( self::TRANSITIONS );
	}

	/**
	 * Create a Lead and its first child request. This is the sole Lead Engine entry point.
	 *
	 * @return array{id:int,request_id:int,duplicate:bool}|\WP_Error
	 */
	public function create( Submission $submission, int $actor_id, string $request_type = 'inquiry' ): array|\WP_Error {
		if ( ! in_array( $request_type, self::REQUEST_TYPES, true ) ) {
			return $this->error( 'invalid_request_type', 400 );
		}
		$context = $this->validateContext( $submission );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$dedupe = $this->dedupeKey( $submission );
		global $wpdb;
		$lead_table = $wpdb->prefix . 'rep_leads';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is an internal plugin identifier; the dedupe key is prepared.
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id,status FROM {$lead_table} WHERE dedupe_key=%s LIMIT 1", $dedupe ), ARRAY_A );
		if ( is_array( $existing ) && isset( $existing['id'] ) ) {
			$request_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'rep_lead_requests WHERE lead_id=%d ORDER BY id DESC LIMIT 1', (int) $existing['id'] ) );
			return array(
				'id'         => (int) $existing['id'],
				'request_id' => $request_id,
				'duplicate'  => true,
			);
		}
		$now      = gmdate( 'Y-m-d H:i:s' );
		$user_id  = max( 0, $actor_id );
		$inserted = $wpdb->insert(
			$lead_table,
			array(
				'user_id'           => $user_id,
				'name'              => $submission->name,
				'email'             => $submission->email,
				'phone'             => $submission->phone,
				'source'            => $submission->source,
				'status'            => 'new',
				'property_id'       => $submission->property_id > 0 ? $submission->property_id : null,
				'project_id'        => $submission->project_id > 0 ? $submission->project_id : null,
				'agent_id'          => null,
				'agency_id'         => null,
				'consent_granted'   => 1,
				'consent_at'        => $now,
				'ip_hash'           => $this->clientHash( 'REMOTE_ADDR' ),
				'user_agent_hash'   => $this->clientHash( 'HTTP_USER_AGENT' ),
				'dedupe_key'        => $dedupe,
				'privacy_erased_at' => null,
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			// A concurrent replay can win the unique dedupe key between the read and insert.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is internal; the dedupe key is prepared.
			$existing_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $lead_table . ' WHERE dedupe_key=%s LIMIT 1', $dedupe ) );
			if ( $existing_id > 0 ) {
				$request_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'rep_lead_requests WHERE lead_id=%d ORDER BY id DESC LIMIT 1', $existing_id ) );
				return array(
					'id'         => $existing_id,
					'request_id' => $request_id,
					'duplicate'  => true,
				);
			}
			return $this->error( 'lead_create_failed', 500 );
		}
		$lead_id       = (int) $wpdb->insert_id;
		$request_table = $wpdb->prefix . 'rep_lead_requests';
		$request_ok    = $wpdb->insert(
			$request_table,
			array(
				'lead_id'       => $lead_id,
				'request_type'  => $request_type,
				'message'       => $submission->message,
				'metadata_json' => (string) wp_json_encode(
					array(
						'source'      => in_array( $submission->source, self::SOURCES, true ) ? $submission->source : 'unknown',
						'property_id' => $submission->property_id,
						'project_id'  => $submission->project_id,
					)
				),
				'created_at'    => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
		if ( false === $request_ok ) {
			$this->removeCreatedLead( $lead_id );
			return $this->error( 'lead_create_failed', 500 );
		}
		$request_id = (int) $wpdb->insert_id;
		$history_ok = $wpdb->insert(
			$wpdb->prefix . 'rep_lead_status_history',
			array(
				'lead_id'       => $lead_id,
				'from_status'   => null,
				'to_status'     => 'new',
				'actor_user_id' => $user_id,
				'note'          => '',
				'created_at'    => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( false === $history_ok ) {
			$this->removeCreatedLead( $lead_id );
			return $this->error( 'lead_create_failed', 500 );
		}
		$this->queue( 'lead.created', 'lead', $lead_id, array( 'request_id' => $request_id ) );
		return array(
			'id'         => $lead_id,
			'request_id' => $request_id,
			'duplicate'  => false,
		);
	}

	/** @return array<string,mixed>|\WP_Error */
	public function get( int $id, int $actor_id ): array|\WP_Error {
		$row = $this->record( $id );
		if ( ! is_array( $row ) || ! $this->canViewRecord( $row, $actor_id ) ) {
			return $this->error( 'lead_not_found', 404 );
		}
		return $this->decorate( $row );
	}

	/** @return array<string,mixed>|\WP_Error */
	public function list( int $actor_id, int $page = 1, int $per_page = 20, string $status = '' ): array|\WP_Error {
		if ( ! $this->canManage( $actor_id ) ) {
			return $this->error( 'lead_forbidden', 403 );
		}
		if ( $page < 1 || $per_page < 1 || $per_page > 100 || ( '' !== $status && ! in_array( $status, self::statuses(), true ) ) ) {
			return $this->error( 'invalid_lead_query', 400 );
		}
		global $wpdb;
		$table  = $wpdb->prefix . 'rep_leads';
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
			return $this->error( 'lead_transition_forbidden', 403 );
		}
		$row = $this->record( $id );
		if ( ! is_array( $row ) ) {
			return $this->error( 'lead_not_found', 404 );
		}
		$from = (string) ( $row['status'] ?? '' );
		if ( ! isset( self::TRANSITIONS[ $from ] ) || ! in_array( $to_status, self::TRANSITIONS[ $from ], true ) ) {
			return $this->error( 'invalid_lead_transition', 409 );
		}
		if ( strlen( $note ) > 1000 ) {
			return $this->error( 'invalid_lead_note', 400 );
		}
		$note = sanitize_textarea_field( $note );
		global $wpdb;
		$now     = gmdate( 'Y-m-d H:i:s' );
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $wpdb->prefix . 'rep_leads SET status=%s,updated_at=%s WHERE id=%d AND status=%s',
				$to_status,
				$now,
				$id,
				$from
			)
		);
		if ( 1 !== (int) $updated ) {
			return $this->error( 'lead_state_conflict', 409 );
		}
		$history = $wpdb->insert(
			$wpdb->prefix . 'rep_lead_status_history',
			array(
				'lead_id'       => $id,
				'from_status'   => $from,
				'to_status'     => $to_status,
				'actor_user_id' => max( 0, $actor_id ),
				'note'          => $note,
				'created_at'    => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( false === $history ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'rep_leads SET status=%s,updated_at=%s WHERE id=%d', $from, $now, $id ) );
			return $this->error( 'lead_transition_failed', 500 );
		}
		$this->queue(
			'lead.status_changed',
			'lead',
			$id,
			array(
				'from' => $from,
				'to'   => $to_status,
			)
		);
		return $this->get( $id, $actor_id );
	}

	/** @return array<string,mixed>|\WP_Error */
	public function assign( int $id, int $agent_id, int $agency_id, int $actor_id ): array|\WP_Error {
		if ( ! $this->canAssign( $actor_id ) ) {
			return $this->error( 'lead_assignment_forbidden', 403 );
		}
		$row = $this->record( $id );
		if ( ! is_array( $row ) ) {
			return $this->error( 'lead_not_found', 404 );
		}
		$target = $this->validateAssignment( $agent_id, $agency_id );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		$from_agent  = $this->nullableId( $row['agent_id'] ?? null );
		$from_agency = $this->nullableId( $row['agency_id'] ?? null );
		if ( $from_agent === $target['agent_id'] && $from_agency === $target['agency_id'] ) {
			return $this->decorate( $row );
		}
		global $wpdb;
		$now     = gmdate( 'Y-m-d H:i:s' );
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $wpdb->prefix . 'rep_leads SET agent_id=%d,agency_id=%d,updated_at=%s WHERE id=%d',
				$target['agent_id'],
				$target['agency_id'],
				$now,
				$id
			)
		);
		if ( 1 !== (int) $updated ) {
			return $this->error( 'lead_assignment_failed', 500 );
		}
		$history = $wpdb->insert(
			$wpdb->prefix . 'rep_lead_assignment_history',
			array(
				'lead_id'        => $id,
				'from_agent_id'  => $from_agent,
				'to_agent_id'    => $target['agent_id'],
				'from_agency_id' => $from_agency,
				'to_agency_id'   => $target['agency_id'],
				'actor_user_id'  => max( 0, $actor_id ),
				'created_at'     => $now,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
		);
		if ( false === $history ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'rep_leads SET agent_id=%d,agency_id=%d,updated_at=%s WHERE id=%d', $from_agent ?? 0, $from_agency ?? 0, $now, $id ) );
			return $this->error( 'lead_assignment_failed', 500 );
		}
		$this->queue(
			'lead.assignment_changed',
			'lead',
			$id,
			array(
				'agent_id'  => $target['agent_id'],
				'agency_id' => $target['agency_id'],
			)
		);
		return $this->get( $id, $actor_id );
	}

	/** @return array<string,mixed>|\WP_Error */
	public function getRequest( int $request_id, int $actor_id ): array|\WP_Error {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT r.*,l.user_id,l.name,l.email,l.phone,l.property_id,l.project_id,l.agent_id,l.agency_id,l.status AS lead_status FROM ' . $wpdb->prefix . 'rep_lead_requests AS r INNER JOIN ' . $wpdb->prefix . 'rep_leads AS l ON l.id=r.lead_id WHERE r.id=%d',
				$request_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) || ! $this->canViewRecord( $row, $actor_id ) ) {
			return $this->error( 'request_not_found', 404 );
		}
		return array(
			'id'           => $request_id,
			'lead_id'      => (int) $row['lead_id'],
			'request_type' => (string) $row['request_type'],
			'message'      => (string) $row['message'],
			'metadata'     => $this->decodeMetadata( (string) $row['metadata_json'] ),
			'created_at'   => (string) $row['created_at'],
			'lead_status'  => (string) $row['lead_status'],
		);
	}

	/** @return array{agent_id:int,agency_id:int}|\WP_Error */
	public function propertyAssignment( int $property_id ): array|\WP_Error {
		if ( $property_id < 1 || 'property' !== get_post_type( $property_id ) || 'publish' !== get_post_status( $property_id ) ) {
			return $this->error( 'invalid_property', 400 );
		}
		$agent_id  = (int) get_post_meta( $property_id, 'rep_agent_id', true );
		$agency_id = (int) get_post_meta( $property_id, 'rep_agency_id', true );
		if ( 0 === $agent_id && 0 === $agency_id ) {
			return array(
				'agent_id'  => 0,
				'agency_id' => 0,
			);
		}
		return $this->validateAssignment( $agent_id, $agency_id );
	}

	public function canViewRecord( array $row, int $actor_id ): bool {
		if ( $actor_id < 1 ) {
			return false;
		}
		if ( $this->canManage( $actor_id ) ) {
			return true;
		}
		if ( (int) ( $row['user_id'] ?? 0 ) === $actor_id ) {
			return true;
		}
		foreach ( array( 'agent_id', 'agency_id' ) as $key ) {
			$profile_id = (int) ( $row[ $key ] ?? 0 );
			$post       = $profile_id > 0 ? get_post( $profile_id ) : null;
			if ( $post instanceof \WP_Post && (int) $post->post_author === $actor_id ) {
				return true;
			}
		}
		return false;
	}

	/** @return list<array<string,mixed>> */
	public function exportForEmail( string $email, int $user_id ): array {
		global $wpdb;
		$email = sanitize_email( $email );
		if ( $user_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is an internal identifier; both data values are prepared.
			$leads = $wpdb->get_results( $wpdb->prepare( 'SELECT l.id,l.name,l.email,l.phone,l.source,l.status,l.property_id,l.project_id,l.consent_granted,l.consent_at,l.created_at,l.updated_at FROM ' . $wpdb->prefix . 'rep_leads AS l WHERE l.user_id=%d OR l.email=%s ORDER BY l.id', $user_id, $email ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is an internal identifier; the email value is prepared.
			$leads = $wpdb->get_results( $wpdb->prepare( 'SELECT l.id,l.name,l.email,l.phone,l.source,l.status,l.property_id,l.project_id,l.consent_granted,l.consent_at,l.created_at,l.updated_at FROM ' . $wpdb->prefix . 'rep_leads AS l WHERE l.email=%s ORDER BY l.id', $email ), ARRAY_A );
		}
		$out = array();
		foreach ( is_array( $leads ) ? $leads : array() as $lead ) {
			$id       = (int) $lead['id'];
			$requests = $wpdb->get_results( $wpdb->prepare( 'SELECT id,request_type,message,metadata_json,created_at FROM ' . $wpdb->prefix . 'rep_lead_requests WHERE lead_id=%d ORDER BY id', $id ), ARRAY_A );
			$out[]    = array(
				'lead'     => $lead,
				'requests' => is_array( $requests ) ? $requests : array(),
			);
		}
		return $out;
	}

	public function eraseForEmail( string $email, int $user_id ): int {
		global $wpdb;
		$email = sanitize_email( $email );
		if ( $user_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is an internal identifier; both data values are prepared.
			$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'rep_leads WHERE user_id=%d OR email=%s', $user_id, $email ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is an internal identifier; the email value is prepared.
			$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'rep_leads WHERE email=%s', $email ) );
		}
		$ids = array_values( array_filter( array_map( 'intval', is_array( $ids ) ? $ids : array() ) ) );
		if ( empty( $ids ) ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$now          = gmdate( 'Y-m-d H:i:s' );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders -- Placeholder list is generated locally and every ID is normalized and prepared.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}rep_leads SET user_id=0,name=%s,email=%s,phone=%s,ip_hash=%s,user_agent_hash=%s,dedupe_key=NULL,privacy_erased_at=%s,updated_at=%s WHERE id IN ({$placeholders})", ...array_merge( array( '[erased]', '', '', '', '', $now, $now ), $ids ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholder list is generated locally and every ID is normalized and prepared.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}rep_lead_requests SET message='',metadata_json='{}' WHERE lead_id IN ({$placeholders})", ...$ids ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholder list is generated locally and every ID is normalized and prepared.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}rep_lead_status_history SET note='' WHERE lead_id IN ({$placeholders})", ...$ids ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}rep_notification_events SET status=%s,payload_json=%s,recipient_user_id=%d,recipient_email=%s,last_error=%s,updated_at=%s WHERE aggregate_type=%s AND aggregate_id IN ({$placeholders})", ...array_merge( array( 'cancelled', '{}', 0, '', 'Privacy erasure requested.', $now, 'lead' ), $ids ) ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders
		return count( $ids );
	}

	public function cleanupUser( int $user_id ): void {
		if ( $user_id > 0 ) {
			$this->eraseForEmail( '', $user_id );
		}
	}

	public function cleanupProfile( int $post_id ): void {
		if ( $post_id < 1 || ! in_array( get_post_type( $post_id ), array( 'agent', 'agency' ), true ) ) {
			return;
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'rep_leads SET agent_id=NULL,agency_id=NULL,updated_at=%s WHERE agent_id=%d OR agency_id=%d', gmdate( 'Y-m-d H:i:s' ), $post_id, $post_id ) );
	}

	public function cleanupProperty( int $post_id ): void {
		if ( $post_id < 1 ) {
			return;
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'rep_leads SET property_id=NULL,updated_at=%s WHERE property_id=%d', gmdate( 'Y-m-d H:i:s' ), $post_id ) );
	}

	private function record( int $id ): array|false {
		if ( $id < 1 ) {
			return false;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'rep_leads WHERE id=%d LIMIT 1', $id ), ARRAY_A );
		return is_array( $row ) ? $row : false;
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private function decorate( array $row, bool $include_requests = true ): array {
		$data = array(
			'id'              => (int) ( $row['id'] ?? 0 ),
			'name'            => (string) ( $row['name'] ?? '' ),
			'email'           => (string) ( $row['email'] ?? '' ),
			'phone'           => (string) ( $row['phone'] ?? '' ),
			'source'          => (string) ( $row['source'] ?? '' ),
			'status'          => (string) ( $row['status'] ?? '' ),
			'property_id'     => $this->nullableId( $row['property_id'] ?? null ),
			'project_id'      => $this->nullableId( $row['project_id'] ?? null ),
			'agent_id'        => $this->nullableId( $row['agent_id'] ?? null ),
			'agency_id'       => $this->nullableId( $row['agency_id'] ?? null ),
			'consent_granted' => 1 === (int) ( $row['consent_granted'] ?? 0 ),
			'consent_at'      => (string) ( $row['consent_at'] ?? '' ),
			'created_at'      => (string) ( $row['created_at'] ?? '' ),
			'updated_at'      => (string) ( $row['updated_at'] ?? '' ),
		);
		if ( $include_requests ) {
			global $wpdb;
			$requests         = $wpdb->get_results( $wpdb->prepare( 'SELECT id,request_type,message,metadata_json,created_at FROM ' . $wpdb->prefix . 'rep_lead_requests WHERE lead_id=%d ORDER BY id ASC', (int) $row['id'] ), ARRAY_A );
			$data['requests'] = array();
			foreach ( is_array( $requests ) ? $requests : array() as $request ) {
				$data['requests'][] = array(
					'id'           => (int) $request['id'],
					'request_type' => (string) $request['request_type'],
					'message'      => (string) $request['message'],
					'metadata'     => $this->decodeMetadata( (string) $request['metadata_json'] ),
					'created_at'   => (string) $request['created_at'],
				);
			}
		}
		return $data;
	}

	/** @return array<string,mixed>|\WP_Error */
	private function validateContext( Submission $submission ): array|\WP_Error {
		foreach ( array(
			'property_id' => 'property',
			'project_id'  => 'project',
		) as $field => $type ) {
			$id = $submission->{$field};
			if ( $id < 1 ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post || $type !== $post->post_type || 'publish' !== $post->post_status ) {
				return $this->error( 'invalid_' . $field, 400 );
			}
		}
		return array(
			'property_id' => $submission->property_id,
			'project_id'  => $submission->project_id,
		);
	}

	/** @return array{agent_id:int,agency_id:int}|\WP_Error */
	private function validateAssignment( int $agent_id, int $agency_id ): array|\WP_Error {
		if ( 0 === $agent_id && 0 === $agency_id ) {
			return array(
				'agent_id'  => 0,
				'agency_id' => 0,
			);
		}
		if ( $agent_id < 1 || $agency_id < 1 ) {
			return $this->error( 'invalid_relationship', 400 );
		}
		$agent  = get_post( $agent_id );
		$agency = get_post( $agency_id );
		if ( ! $agent instanceof \WP_Post || ! $agency instanceof \WP_Post || 'agent' !== $agent->post_type || 'agency' !== $agency->post_type ) {
			return $this->error( 'invalid_relationship', 400 );
		}
		if ( (int) get_post_meta( $agent_id, 'rep_agency_id', true ) !== $agency_id ) {
			return $this->error( 'invalid_relationship', 400 );
		}
		return array(
			'agent_id'  => $agent_id,
			'agency_id' => $agency_id,
		);
	}

	private function canManage( int $actor_id ): bool {
		return $actor_id > 0 && ( current_user_can( 'manage_leads' ) || current_user_can( 'view_leads' ) );
	}

	private function canEdit( int $actor_id ): bool {
		return $actor_id > 0 && ( current_user_can( 'manage_leads' ) || current_user_can( 'edit_leads' ) );
	}

	private function canAssign( int $actor_id ): bool {
		return $actor_id > 0 && ( current_user_can( 'manage_leads' ) || current_user_can( 'assign_leads' ) );
	}

	private function dedupeKey( Submission $submission ): string {
		if ( '' !== $submission->idempotency_key ) {
			return hash( 'sha256', strtolower( $submission->email ) . '|key|' . $submission->idempotency_key );
		}
		$bucket = (string) floor( time() / 600 );
		return hash( 'sha256', strtolower( $submission->email ) . '|' . $submission->property_id . '|' . $submission->project_id . '|' . hash( 'sha256', $submission->message ) . '|' . $bucket );
	}

	private function clientHash( string $server_key ): string {
		$value = $_SERVER[ $server_key ] ?? '';
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'realestate-platform';
		return hash_hmac( 'sha256', $value, $salt );
	}

	private function nullableId( mixed $value ): ?int {
		$id = (int) $value;
		return $id > 0 ? $id : null;
	}

	/** @param array<string,mixed> $payload */
	private function queue( string $event, string $aggregate, int $id, array $payload ): void {
		$recipient = sanitize_email( (string) get_option( 'admin_email', '' ) );
		$this->notifications->enqueue( $event, $aggregate, $id, $payload, 0, $recipient, hash( 'sha256', $event . '|' . $aggregate . '|' . $id . '|' . (string) wp_json_encode( $payload ) ) );
	}

	/** @return array<string,mixed> */
	private function decodeMetadata( string $json ): array {
		$value = json_decode( $json, true );
		return is_array( $value ) ? $value : array();
	}

	private function removeCreatedLead( int $id ): void {
		if ( $id < 1 ) {
			return;
		}
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'rep_lead_status_history', array( 'lead_id' => $id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'rep_lead_requests', array( 'lead_id' => $id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'rep_leads', array( 'id' => $id ), array( '%d' ) );
	}

	private function error( string $code, int $status ): \WP_Error {
		return new \WP_Error( $code, 'Lead request could not be completed.', array( 'status' => $status ) );
	}
}
