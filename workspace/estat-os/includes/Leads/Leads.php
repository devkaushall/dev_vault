<?php
/**
 * Enquiry (lead) repository with duplicate protection and privacy controls.
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
 * Every enquiry, wherever it comes from, is created here.
 *
 * Duplicate protection has two layers:
 *  - idempotency_key: an exact repeat of one submission (retry, double click).
 *  - dedupe_hash: the same person asking about the same property within a
 *    short window; the existing enquiry is updated instead of duplicated.
 */
final class Leads {

	/**
	 * Window in which the same person + property is treated as one enquiry.
	 */
	public const DEDUPE_WINDOW = DAY_IN_SECONDS;

	/**
	 * How many trailing digits identify a caller.
	 *
	 * Ten covers Indian mobile and landline numbers with or without a country
	 * code. It is a constant so a country with a different length can change
	 * it in one place.
	 */
	public const PHONE_IDENTITY_DIGITS = 10;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {}

	/**
	 * Create or merge an enquiry.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return int|WP_Error Lead ID (existing one when merged) or error.
	 */
	public static function create( array $input ) {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return new WP_Error( 'estat_no_storage', __( 'The office database is not ready yet. Please try again shortly.', 'estat-os' ) );
		}

		$data = self::prepare( $input );
		$errors = self::validate( $data );
		if ( $errors ) {
			$error = new WP_Error();
			foreach ( $errors as $field => $message ) {
				$error->add( 'estat_invalid_' . $field, $message, array( 'field' => $field ) );
			}
			return $error;
		}

		$table = Schema::table( 'estat_leads' );

		// Layer 1: exact repeat of the same submission.
		if ( '' !== $data['idempotency_key'] ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE idempotency_key = %s", $data['idempotency_key'] ) );
			if ( $existing ) {
				return $existing;
			}
		}

		// Layer 2: same person, same property, recently.
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::DEDUPE_WINDOW );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$recent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE dedupe_hash = %s AND created_at >= %s ORDER BY id DESC LIMIT 1", $data['dedupe_hash'], $cutoff ) );
		if ( $recent ) {
			$note = trim( (string) $data['message'] );
			if ( '' !== $note ) {
				self::append_note( $recent, sprintf( /* translators: %s: message text */ __( 'Asked again: %s', 'estat-os' ), $note ) );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, array( 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $recent ), array( '%s' ), array( '%d' ) );
			return $recent;
		}

		$now = current_time( 'mysql', true );
		$row = array(
			'created_at'        => $now,
			'updated_at'        => $now,
			'name'              => $data['name'],
			'phone'             => $data['phone'],
			'email'             => $data['email'],
			'message'           => $data['message'],
			'source'            => $data['source'],
			'submission_source' => $data['submission_source'],
			'listing_id'        => $data['listing_id'],
			'project_id'        => $data['project_id'],
			'agent_id'          => $data['agent_id'],
			'form_id'           => $data['form_id'],
			'status'            => 'new',
			'consent'           => $data['consent'] ? 1 : 0,
			'practice'          => Settings::get( 'practice_mode' ) ? 1 : 0,
			'dedupe_hash'       => $data['dedupe_hash'],
			'idempotency_key'   => $data['idempotency_key'],
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert( $table, $row );
		if ( ! $inserted ) {
			// A parallel request may have won the unique key race; return that row.
			if ( '' !== $data['idempotency_key'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE idempotency_key = %s", $data['idempotency_key'] ) );
				if ( $existing ) {
					return $existing;
				}
			}
			return new WP_Error( 'estat_save_failed', __( 'We could not save this enquiry. Please try again.', 'estat-os' ) );
		}

		$lead_id = (int) $wpdb->insert_id;

		if ( $data['agent_id'] <= 0 ) {
			$auto = self::auto_agent( $data['listing_id'] );
			if ( $auto > 0 ) {
				self::assign( $lead_id, $auto, false );
			}
		}

		AuditLog::record( 'lead.created', 'lead', $lead_id, array( 'listing_id' => $data['listing_id'], 'source' => $data['source'] ) );

		/**
		 * Fires after a new enquiry is stored.
		 *
		 * @param int                 $lead_id Lead ID.
		 * @param array<string,mixed> $row     Stored row.
		 */
		do_action( 'estat_lead_created', $lead_id, $row );

		return $lead_id;
	}

	/**
	 * The part of a phone number that identifies a person.
	 *
	 * The dedupe hash used every digit, so "9876543210" and "+919876543210"
	 * produced different hashes and the same person became two enquiries. In
	 * India both forms are typed constantly, and the office then rang them
	 * twice.
	 *
	 * Keeping the LAST ten digits solves it without a country-code table:
	 * a national number, the same number with +91, with 0091, or with a
	 * leading 0 all reduce to the same ten. Numbers shorter than ten are left
	 * alone rather than guessed at.
	 *
	 * @param string $phone Phone number as typed.
	 * @return string Digits used for identity.
	 */
	private static function phone_identity( string $phone ): string {
		$digits = (string) preg_replace( '/\D/', '', $phone );

		if ( strlen( $digits ) <= self::PHONE_IDENTITY_DIGITS ) {
			return $digits;
		}

		return substr( $digits, -self::PHONE_IDENTITY_DIGITS );
	}

	/**
	 * Normalise raw input into the stored shape.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	private static function prepare( array $input ): array {
		$name    = Sanitize::text( $input['name'] ?? '' );
		$phone   = Sanitize::phone( $input['phone'] ?? '' );
		$email   = Sanitize::email( $input['email'] ?? '' );
		$listing = Sanitize::int( $input['listing_id'] ?? 0 );

		$identity = strtolower( $email ) . '|' . self::phone_identity( $phone );
		$hash     = hash( 'sha256', $identity . '|' . $listing . '|' . Sanitize::int( $input['project_id'] ?? 0 ) );

		$key = Sanitize::text( $input['idempotency_key'] ?? '' );
		$key = '' !== $key ? hash( 'sha256', $key ) : '';

		return array(
			'name'              => $name,
			'phone'             => $phone,
			'email'             => $email,
			'message'           => Sanitize::textarea( $input['message'] ?? '' ),
			'source'            => Sanitize::choice( $input['source'] ?? 'website', array( 'website', 'form', 'phone', 'walkin', 'import', 'api', 'other' ), 'website' ),
			'submission_source' => mb_substr( Sanitize::url( $input['submission_source'] ?? '' ), 0, 191 ),
			'listing_id'        => $listing,
			'project_id'        => Sanitize::int( $input['project_id'] ?? 0 ),
			'agent_id'          => Sanitize::int( $input['agent_id'] ?? 0 ),
			'form_id'           => Sanitize::int( $input['form_id'] ?? 0 ),
			'consent'           => Sanitize::bool( $input['consent'] ?? false ),
			'dedupe_hash'       => $hash,
			'idempotency_key'   => $key,
		);
	}

	/**
	 * Validate a prepared enquiry.
	 *
	 * @param array<string,mixed> $data Prepared data.
	 * @return array<string,string>
	 */
	public static function validate( array $data ): array {
		$errors = array();
		if ( '' === trim( (string) $data['name'] ) ) {
			$errors['name'] = __( 'Please tell us your name.', 'estat-os' );
		}
		$digits = preg_replace( '/\D/', '', (string) $data['phone'] );
		if ( '' === (string) $data['phone'] && '' === (string) $data['email'] ) {
			$errors['phone'] = __( 'Please add a phone number or an email address so we can reply.', 'estat-os' );
		} elseif ( '' !== (string) $data['phone'] && strlen( (string) $digits ) < 7 ) {
			$errors['phone'] = __( 'That phone number looks too short. Please check it.', 'estat-os' );
		}
		if ( mb_strlen( (string) $data['message'] ) > 5000 ) {
			$errors['message'] = __( 'Your message is very long. Please shorten it a little.', 'estat-os' );
		}
		return $errors;
	}

	/**
	 * Pick the agent responsible for a listing.
	 *
	 * @param int $listing_id Listing ID.
	 * @return int
	 */
	private static function auto_agent( int $listing_id ): int {
		return $listing_id > 0 ? (int) get_post_meta( $listing_id, '_estat_agent_id', true ) : 0;
	}

	/**
	 * Fetch one enquiry.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( int $lead_id ): ?array {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return null;
		}
		$table = Schema::table( 'estat_leads' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $lead_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * List enquiries with filters and pagination.
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int,page:int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;
		$out = array( 'items' => array(), 'total' => 0, 'pages' => 0, 'page' => 1 );
		if ( ! Schema::healthy() ) {
			return $out;
		}
		$table    = Schema::table( 'estat_leads' );
		$per_page = Sanitize::int( $args['per_page'] ?? 20, 1, 100 );
		$page     = Sanitize::int( $args['page'] ?? 1, 1, 10000 );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = Sanitize::choice( $args['status'], Vocabulary::keys( 'lead_statuses' ), 'new' );
		}
		if ( ! empty( $args['agent_id'] ) ) {
			$where[]  = 'agent_id = %d';
			$params[] = Sanitize::int( $args['agent_id'] );
		}
		if ( ! empty( $args['listing_id'] ) ) {
			$where[]  = 'listing_id = %d';
			$params[] = Sanitize::int( $args['listing_id'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( Sanitize::text( $args['search'] ) ) . '%';
			$where[]  = '(name LIKE %s OR phone LIKE %s OR email LIKE %s)';
			$params   = array_merge( $params, array( $like, $like, $like ) );
		}
		if ( ! empty( $args['due_followup'] ) ) {
			$where[]  = 'followup_at IS NOT NULL AND followup_at <= %s AND status NOT IN (\'converted\',\'lost\')';
			$params[] = current_time( 'mysql', true );
		}
		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );
		$items     = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ),
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
	 * Update an enquiry's working fields.
	 *
	 * @param int                 $lead_id Lead ID.
	 * @param array<string,mixed> $input   Fields to change.
	 * @return bool|WP_Error
	 */
	public static function update( int $lead_id, array $input ) {
		global $wpdb;
		$lead = self::get( $lead_id );
		if ( ! $lead ) {
			return new WP_Error( 'estat_not_found', __( 'That enquiry could not be found.', 'estat-os' ) );
		}
		$fields = array( 'updated_at' => current_time( 'mysql', true ) );

		if ( isset( $input['status'] ) ) {
			$fields['status'] = Sanitize::choice( $input['status'], Vocabulary::keys( 'lead_statuses' ), (string) $lead['status'] );
		}
		if ( isset( $input['agent_id'] ) ) {
			$fields['agent_id'] = Sanitize::int( $input['agent_id'] );
		}
		if ( isset( $input['followup_at'] ) ) {
			$date                  = Sanitize::datetime( $input['followup_at'] );
			$fields['followup_at'] = '' !== $date ? $date : null;
		}
		if ( isset( $input['listing_id'] ) ) {
			$fields['listing_id'] = Sanitize::int( $input['listing_id'] );
		}
		if ( isset( $input['notes'] ) ) {
			$fields['notes'] = Sanitize::textarea( $input['notes'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( Schema::table( 'estat_leads' ), $fields, array( 'id' => $lead_id ) );
		AuditLog::record( 'lead.updated', 'lead', $lead_id, array( 'status' => $fields['status'] ?? $lead['status'] ) );

		/**
		 * Fires after an enquiry is updated.
		 *
		 * @param int                 $lead_id Lead ID.
		 * @param array<string,mixed> $fields  Changed fields.
		 */
		do_action( 'estat_lead_updated', $lead_id, $fields );
		return true;
	}

	/**
	 * Assign an enquiry to a team member.
	 *
	 * @param int  $lead_id  Lead ID.
	 * @param int  $agent_id Agent post ID.
	 * @param bool $log      Whether to write an audit entry.
	 * @return bool
	 */
	public static function assign( int $lead_id, int $agent_id, bool $log = true ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			Schema::table( 'estat_leads' ),
			array( 'agent_id' => $agent_id, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $lead_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		if ( $log ) {
			AuditLog::record( 'lead.assigned', 'lead', $lead_id, array( 'agent_id' => $agent_id ) );
		}
		return true;
	}

	/**
	 * Append a timestamped line to the notes.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param string $note    Note text.
	 * @return void
	 */
	public static function append_note( int $lead_id, string $note ): void {
		global $wpdb;
		$lead = self::get( $lead_id );
		if ( ! $lead ) {
			return;
		}
		$line  = '[' . current_time( 'mysql' ) . '] ' . Sanitize::textarea( $note );
		$notes = trim( (string) $lead['notes'] . "\n" . $line );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( Schema::table( 'estat_leads' ), array( 'notes' => $notes, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $lead_id ), array( '%s', '%s' ), array( '%d' ) );
	}

	/**
	 * Erase personal information but keep the business record.
	 *
	 * @param int $lead_id Lead ID.
	 * @return bool
	 */
	public static function erase( int $lead_id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			Schema::table( 'estat_leads' ),
			array(
				'name'        => __( 'Erased', 'estat-os' ),
				'phone'       => '',
				'email'       => '',
				'message'     => '',
				'notes'       => '',
				'erased'      => 1,
				'dedupe_hash' => '',
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => $lead_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
		AuditLog::record( 'lead.erased', 'lead', $lead_id, array() );
		return true;
	}

	/**
	 * Counts used by the Today screen.
	 *
	 * @return array<string,int>
	 */
	public static function counts(): array {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return array( 'new' => 0, 'due' => 0, 'total' => 0 );
		}
		$table = Schema::table( 'estat_leads' );
		$now   = current_time( 'mysql', true );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'new'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'new'" ),
			'due'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE followup_at IS NOT NULL AND followup_at <= %s AND status NOT IN ('converted','lost')", $now ) ),
			'total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
		);
		// phpcs:enable
	}

	/**
	 * Remove enquiries older than the retention period.
	 *
	 * @param int $days Retention days.
	 * @return int Rows erased.
	 */
	public static function apply_retention( int $days ): int {
		if ( $days <= 0 ) {
			return 0;
		}
		global $wpdb;
		$table  = Schema::table( 'estat_leads' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE created_at < %s AND erased = 0 LIMIT 500", $cutoff ) );
		foreach ( $ids as $id ) {
			self::erase( (int) $id );
		}
		return count( $ids );
	}
}
