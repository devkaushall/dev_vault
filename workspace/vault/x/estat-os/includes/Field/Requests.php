<?php
/**
 * Change requests.
 *
 * An agent out on the road can edit their own properties freely, but a change
 * to somebody else's has to be asked for rather than made. This holds those
 * requests until the office approves them.
 *
 * The rule the office asked for, in plain terms:
 *
 *   - Your own property: change it, it changes.
 *   - Somebody else's: you can see it and you can propose a change, but
 *     nothing moves until an owner or manager says yes.
 *
 * Why a request rather than a lock: an agent standing in a flat that has just
 * sold should be able to say so immediately, even if the listing belongs to a
 * colleague who is on leave. Refusing them outright means the website stays
 * wrong. Queuing it means the office finds out and decides.
 *
 * A request stores only the fields being changed, never a whole record. If two
 * requests touch the same property, approving one does not silently undo the
 * other's untouched fields.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Field;

use EstatOS\Audit\AuditLog;
use EstatOS\Data\Listings;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Projects;
use EstatOS\Install\Schema;
use EstatOS\Support\Sanitize;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Proposed changes waiting for the office to approve them.
 */
final class Requests {

	/**
	 * The table, without the site prefix.
	 */
	public const TABLE = 'estat_requests';

	/**
	 * Where a request can be in its life.
	 *
	 * @return array<string,string>
	 */
	public static function statuses(): array {
		return array(
			'pending'  => __( 'Waiting for the office', 'estat-os' ),
			'approved' => __( 'Approved and applied', 'estat-os' ),
			'declined' => __( 'Declined', 'estat-os' ),
		);
	}

	/**
	 * The kinds of change an agent may propose.
	 *
	 * Deliberately short. A field agent needs to correct a price, mark
	 * something sold, or add a photograph they have just taken. Anything
	 * beyond that is office work, and offering it here would only produce
	 * requests nobody wants to review.
	 *
	 * @return array<string,array{label:string,type:string}>
	 */
	public static function editable_fields(): array {
		return array(
			'price'        => array(
				'label' => __( 'Price', 'estat-os' ),
				'type'  => 'money',
			),
			'rent'         => array(
				'label' => __( 'Monthly rent', 'estat-os' ),
				'type'  => 'money',
			),
			'availability' => array(
				'label' => __( 'Is it still available?', 'estat-os' ),
				'type'  => 'availability',
			),
			'description'  => array(
				'label' => __( 'Description', 'estat-os' ),
				'type'  => 'textarea',
			),
			'note'         => array(
				'label' => __( 'A note for the office', 'estat-os' ),
				'type'  => 'textarea',
			),
		);
	}

	/**
	 * Ask the office to change somebody else's record.
	 *
	 * @param array $input Raw input.
	 * @return int|WP_Error Request ID, or an error.
	 */
	public static function create( array $input ) {
		global $wpdb;

		if ( ! Schema::healthy() ) {
			return new WP_Error( 'estat_no_storage', __( 'The office database is not ready yet. Please try again shortly.', 'estat-os' ) );
		}

		$record_id = Sanitize::int( $input['record_id'] ?? 0 );
		$record    = $record_id > 0 ? get_post( $record_id ) : null;

		if ( ! $record || ! in_array( $record->post_type, array( PostTypes::LISTING, PostTypes::PROJECT ), true ) ) {
			return new WP_Error( 'estat_not_found', __( 'That property could not be found.', 'estat-os' ) );
		}

		$changes = self::clean_changes( $input['changes'] ?? array() );

		if ( ! $changes ) {
			return new WP_Error( 'estat_empty_request', __( 'Nothing was filled in, so there is nothing to ask for.', 'estat-os' ) );
		}

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			Schema::table( self::TABLE ),
			array(
				'created_at'  => $now,
				'updated_at'  => $now,
				'record_id'   => $record_id,
				'record_type' => $record->post_type,
				'requested_by' => get_current_user_id(),
				'reviewed_by' => 0,
				'status'      => 'pending',
				'changes'     => (string) wp_json_encode( $changes ),
				'message'     => Sanitize::textarea( $input['message'] ?? '' ),
			),
			array( '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'estat_save_failed', __( 'That request could not be sent. Please try again.', 'estat-os' ) );
		}

		$request_id = (int) $wpdb->insert_id;

		AuditLog::record(
			'request.created',
			$record->post_type,
			$record_id,
			array(
				'request_id' => $request_id,
				'fields'     => implode( ', ', array_keys( $changes ) ),
			)
		);

		return $request_id;
	}

	/**
	 * Keep only fields we allow, and sanitise each by its own kind.
	 *
	 * Everything arriving here came from a phone browser, so nothing in it is
	 * trusted. An unknown field name is dropped rather than stored, because a
	 * request holding a field nobody will apply is just a trap for whoever
	 * reviews it.
	 *
	 * @param mixed $raw Raw changes.
	 * @return array<string,string>
	 */
	private static function clean_changes( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$allowed = self::editable_fields();
		$clean   = array();

		foreach ( $raw as $field => $value ) {
			$field = sanitize_key( (string) $field );

			if ( ! isset( $allowed[ $field ] ) ) {
				continue;
			}

			$value = self::clean_value( (string) $allowed[ $field ]['type'], $value );

			if ( '' === $value ) {
				continue;
			}

			$clean[ $field ] = $value;
		}

		return $clean;
	}

	/**
	 * Sanitise one proposed value by its declared kind.
	 *
	 * @param string $type  Field kind.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	private static function clean_value( string $type, $value ): string {
		switch ( $type ) {
			case 'money':
				$number = Sanitize::float( $value );

				// The same ceiling the editor enforces, so a request can never
				// carry a figure that would be refused on approval.
				if ( $number <= 0 || $number > Listings::MAX_MONEY ) {
					return '';
				}

				return (string) $number;

			case 'availability':
				$choice = Sanitize::choice(
					$value,
					array( 'available', 'on_hold', 'sold', 'rented' ),
					''
				);

				return $choice;

			case 'textarea':
				return Sanitize::textarea( $value );
		}

		return '';
	}

	/**
	 * One request.
	 *
	 * @param int $request_id Request ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( int $request_id ): ?array {
		global $wpdb;

		if ( ! Schema::healthy() || $request_id <= 0 ) {
			return null;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$table = Schema::table( self::TABLE );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $request_id ),
			ARRAY_A
		);
		// phpcs:enable

		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Requests, newest first.
	 *
	 * @param array $args status, record_id, requested_by, page, per_page.
	 * @return array{items:array,total:int,page:int,pages:int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;

		$empty = array(
			'items' => array(),
			'total' => 0,
			'page'  => 1,
			'pages' => 0,
		);

		if ( ! Schema::healthy() ) {
			return $empty;
		}

		$per_page = Sanitize::int( $args['per_page'] ?? 20, 1, 100 );
		$page     = Sanitize::int( $args['page'] ?? 1, 1, 10000 );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$status = Sanitize::choice( $args['status'], array_keys( self::statuses() ), '' );

			if ( '' !== $status ) {
				$where[]  = 'status = %s';
				$params[] = $status;
			}
		}

		if ( ! empty( $args['record_id'] ) ) {
			$where[]  = 'record_id = %d';
			$params[] = Sanitize::int( $args['record_id'] );
		}

		if ( ! empty( $args['requested_by'] ) ) {
			$where[]  = 'requested_by = %d';
			$params[] = Sanitize::int( $args['requested_by'] );
		}

		$where_sql = implode( ' AND ', $where );
		$table     = Schema::table( self::TABLE );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			$params
				? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params )
				: "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}"
		);

		$pages = (int) ceil( $total / max( 1, $per_page ) );

		// Clamp after counting, so "page 50 of 2" can never be reported.
		if ( $page > $pages ) {
			$page = max( 1, $pages );
		}

		$offset = ( $page - 1 ) * $per_page;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d",
				array_merge( $params, array( $per_page, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'items' => array_map( array( __CLASS__, 'shape' ), (array) $rows ),
			'total' => $total,
			'page'  => $page,
			'pages' => $pages,
		);
	}

	/**
	 * How many are waiting, for the badge on the office menu.
	 *
	 * @return int
	 */
	public static function pending_count(): int {
		global $wpdb;

		if ( ! Schema::healthy() ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$table = Schema::table( self::TABLE );

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
		// phpcs:enable
	}

	/**
	 * Approve a request and apply it.
	 *
	 * The change is written by the same Listings::save() the office editor
	 * uses, so validation, the search index, the audit trail and the undo
	 * snapshot all behave exactly as they would if the office had typed it.
	 * A separate write path here would be a second place for bugs to live.
	 *
	 * @param int $request_id Request ID.
	 * @return true|WP_Error
	 */
	public static function approve( int $request_id ) {
		$request = self::get( $request_id );

		if ( ! $request ) {
			return new WP_Error( 'estat_not_found', __( 'That request could not be found.', 'estat-os' ) );
		}

		if ( 'pending' !== $request['status'] ) {
			return new WP_Error(
				'estat_already_reviewed',
				__( 'That request has already been dealt with.', 'estat-os' )
			);
		}

		$record = get_post( (int) $request['record_id'] );

		if ( ! $record ) {
			// The property was deleted while the request sat waiting. Close it
			// rather than leaving something un-actionable in the queue.
			self::mark( $request_id, 'declined' );

			return new WP_Error(
				'estat_gone',
				__( 'That property no longer exists, so the request was closed.', 'estat-os' )
			);
		}

		$changes = $request['changes'];
		$note    = isset( $changes['note'] ) ? (string) $changes['note'] : '';

		// The note is a message to the office, not a field on the record.
		unset( $changes['note'] );

		if ( $changes ) {
			$payload = array();

			foreach ( $changes as $field => $value ) {
				$payload[ $field ] = $value;
			}

			$saved = PostTypes::PROJECT === $record->post_type
				? Projects::save( $payload, (int) $request['record_id'] )
				: Listings::save( $payload, (int) $request['record_id'] );

			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		if ( '' !== $note ) {
			self::append_note( (int) $request['record_id'], $note );
		}

		self::mark( $request_id, 'approved' );

		AuditLog::record(
			'request.approved',
			(string) $request['record_type'],
			(int) $request['record_id'],
			array(
				'request_id' => $request_id,
				'fields'     => implode( ', ', array_keys( $changes ) ),
			)
		);

		return true;
	}

	/**
	 * Turn a request down, changing nothing.
	 *
	 * @param int    $request_id Request ID.
	 * @param string $reason     Optional reason for the agent.
	 * @return true|WP_Error
	 */
	public static function decline( int $request_id, string $reason = '' ) {
		$request = self::get( $request_id );

		if ( ! $request ) {
			return new WP_Error( 'estat_not_found', __( 'That request could not be found.', 'estat-os' ) );
		}

		if ( 'pending' !== $request['status'] ) {
			return new WP_Error(
				'estat_already_reviewed',
				__( 'That request has already been dealt with.', 'estat-os' )
			);
		}

		self::mark( $request_id, 'declined', Sanitize::textarea( $reason ) );

		AuditLog::record(
			'request.declined',
			(string) $request['record_type'],
			(int) $request['record_id'],
			array( 'request_id' => $request_id )
		);

		return true;
	}

	/**
	 * Record the outcome.
	 *
	 * @param int    $request_id Request ID.
	 * @param string $status     New status.
	 * @param string $reason     Optional reason.
	 * @return void
	 */
	private static function mark( int $request_id, string $status, string $reason = '' ): void {
		global $wpdb;

		if ( ! Schema::healthy() ) {
			return;
		}

		$data = array(
			'status'      => $status,
			'reviewed_by' => get_current_user_id(),
			'updated_at'  => current_time( 'mysql', true ),
		);

		$format = array( '%s', '%d', '%s' );

		if ( '' !== $reason ) {
			$data['reason'] = $reason;
			$format[]       = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( Schema::table( self::TABLE ), $data, array( 'id' => $request_id ), $format, array( '%d' ) );
	}

	/**
	 * Add the agent's note to the record's private notes.
	 *
	 * @param int    $record_id Record ID.
	 * @param string $note      Note text.
	 * @return void
	 */
	private static function append_note( int $record_id, string $note ): void {
		$existing = (string) get_post_meta( $record_id, '_estat_internal_notes', true );
		$stamp    = gmdate( 'Y-m-d' );
		$line     = sprintf(
			/* translators: 1: date, 2: the note text. */
			__( '%1$s (from the field): %2$s', 'estat-os' ),
			$stamp,
			$note
		);

		update_post_meta( $record_id, '_estat_internal_notes', trim( $existing . "\n" . $line ) );
	}

	/**
	 * Turn a database row into something a screen can read.
	 *
	 * @param array $row Raw row.
	 * @return array<string,mixed>
	 */
	private static function shape( array $row ): array {
		$changes = json_decode( (string) ( $row['changes'] ?? '' ), true );

		return array(
			'id'           => (int) $row['id'],
			'created_at'   => (string) $row['created_at'],
			'updated_at'   => (string) $row['updated_at'],
			'record_id'    => (int) $row['record_id'],
			'record_type'  => (string) $row['record_type'],
			'record_title' => (string) get_the_title( (int) $row['record_id'] ),
			'requested_by' => (int) $row['requested_by'],
			'reviewed_by'  => (int) $row['reviewed_by'],
			'status'       => (string) $row['status'],
			'changes'      => is_array( $changes ) ? $changes : array(),
			'message'      => (string) ( $row['message'] ?? '' ),
			'reason'       => (string) ( $row['reason'] ?? '' ),
		);
	}
}
