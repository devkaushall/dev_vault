<?php
/**
 * Central submission pipeline: validate, protect, store, convert, notify.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Forms;

use EstatOS\Install\Schema;
use EstatOS\Leads\Leads;
use EstatOS\Notifications\Notifier;
use EstatOS\Settings\Settings;
use EstatOS\Support\Sanitize;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Every submission, from any surface (website, Elementor widget, REST client),
 * goes through handle(). Browser validation is never trusted.
 */
final class Submissions {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {}

	/**
	 * Process one submission.
	 *
	 * @param int                 $form_id Form ID.
	 * @param array<string,mixed> $request Raw request data.
	 * @return array{ok:bool,message:string,lead_id?:int,submission_id?:int,redirect?:string}|WP_Error
	 */
	public static function handle( int $form_id, array $request ) {
		$form = Forms::get( $form_id );
		if ( ! $form || 'active' !== $form['status'] ) {
			return new WP_Error( 'estat_form_unavailable', __( 'This form is not available right now.', 'estat-os' ), array( 'status' => 404 ) );
		}
		$settings = $form['settings'];

		// Spam: honeypot must stay empty and the form must not be submitted instantly.
		if ( '' !== Sanitize::text( $request['estat_website'] ?? '' ) ) {
			return new WP_Error( 'estat_spam', (string) $settings['error_message'], array( 'status' => 400 ) );
		}
		$ts = Sanitize::int( $request['estat_ts'] ?? 0 );
		if ( $ts > 0 && ( time() - $ts ) < 2 ) {
			return new WP_Error( 'estat_too_fast', __( 'That was very quick. Please check the form and send it again.', 'estat-os' ), array( 'status' => 429 ) );
		}

		$ip_hash = self::ip_hash();
		if ( ! self::within_rate_limit( $ip_hash, (int) $settings['rate_limit'] ) ) {
			return new WP_Error( 'estat_rate_limited', __( 'You have sent several enquiries already. Please try again in a few minutes.', 'estat-os' ), array( 'status' => 429 ) );
		}

		$raw_fields = isset( $request['fields'] ) && is_array( $request['fields'] ) ? $request['fields'] : array();
		$result     = self::validate_fields( $form['definition'], $raw_fields );
		if ( ! empty( $result['errors'] ) ) {
			return new WP_Error(
				'estat_validation_failed',
				(string) $settings['error_message'],
				array( 'status' => 422, 'fields' => $result['errors'] )
			);
		}
		$values = $result['values'];

		if ( ! empty( $settings['require_consent'] ) && ! Sanitize::bool( $request['consent'] ?? false ) ) {
			return new WP_Error(
				'estat_consent_required',
				(string) $settings['error_message'],
				array( 'status' => 422, 'fields' => array( 'consent' => __( 'Please tick the box so we may contact you.', 'estat-os' ) ) )
			);
		}

		$idempotency = Sanitize::text( $request['idempotency_key'] ?? '' );
		$key_hash    = '' !== $idempotency ? hash( 'sha256', $form_id . '|' . $idempotency ) : '';

		global $wpdb;
		$sub_table = Schema::table( 'estat_submissions' );
		if ( '' !== $key_hash ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, lead_id FROM {$sub_table} WHERE idempotency_key = %s", $key_hash ), ARRAY_A );
			if ( $existing ) {
				return array(
					'ok'            => true,
					'message'       => (string) $settings['success_message'],
					'lead_id'       => (int) $existing['lead_id'],
					'submission_id' => (int) $existing['id'],
				);
			}
		}

		$listing_id = self::resolve_reference( $settings['listing_field'], $values, $request, 'listing_id' );
		$project_id = self::resolve_reference( $settings['project_field'], $values, $request, 'project_id' );
		$agent_id   = self::resolve_reference( $settings['agent_field'], $values, $request, 'agent_id' );

		$lead_id = 0;
		if ( ! empty( $settings['create_lead'] ) ) {
			$lead = Leads::create(
				array(
					'name'              => self::pick( $values, array( 'name', 'full_name', 'your_name' ) ),
					'phone'             => self::pick( $values, array( 'phone', 'mobile', 'contact' ) ),
					'email'             => self::pick( $values, array( 'email', 'email_address' ) ),
					'message'           => self::pick( $values, array( 'message', 'comments', 'enquiry' ) ),
					'source'            => (string) $settings['lead_source'],
					'submission_source' => Sanitize::url( $request['page_url'] ?? '' ),
					'listing_id'        => $listing_id,
					'project_id'        => $project_id,
					'agent_id'          => $agent_id,
					'form_id'           => $form_id,
					'consent'           => Sanitize::bool( $request['consent'] ?? false ),
					'idempotency_key'   => $idempotency,
				)
			);
			if ( is_wp_error( $lead ) ) {
				$fields = array();
				foreach ( $lead->get_error_codes() as $code ) {
					$data = $lead->get_error_data( $code );
					$name = is_array( $data ) && isset( $data['field'] ) ? (string) $data['field'] : $code;
					$fields[ $name ] = $lead->get_error_message( $code );
				}
				return new WP_Error( 'estat_validation_failed', (string) $settings['error_message'], array( 'status' => 422, 'fields' => $fields ) );
			}
			$lead_id = (int) $lead;
		}

		$submission_id = 0;
		if ( ! empty( $settings['store_submission'] ) && Schema::healthy() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$sub_table,
				array(
					'created_at'      => current_time( 'mysql', true ),
					'form_id'         => $form_id,
					'lead_id'         => $lead_id,
					'payload'         => (string) wp_json_encode( $values ),
					'page_url'        => mb_substr( Sanitize::url( $request['page_url'] ?? '' ), 0, 255 ),
					'ip_hash'         => $ip_hash,
					'idempotency_key' => $key_hash,
					'practice'        => Settings::get( 'practice_mode' ) ? 1 : 0,
				)
			);
			$submission_id = (int) $wpdb->insert_id;
		}

		if ( ! empty( $settings['notify'] ) ) {
			Notifier::new_lead( $lead_id, $form_id, (string) $settings['notify_email'] );
		}

		/**
		 * Fires after a successful submission.
		 *
		 * @param int                 $form_id       Form ID.
		 * @param int                 $lead_id       Lead ID (0 if none).
		 * @param array<string,mixed> $values        Sanitised values.
		 * @param int                 $submission_id Submission ID.
		 */
		do_action( 'estat_form_submitted', $form_id, $lead_id, $values, $submission_id );

		$out = array(
			'ok'            => true,
			'message'       => (string) $settings['success_message'],
			'lead_id'       => $lead_id,
			'submission_id' => $submission_id,
		);
		if ( '' !== (string) $settings['redirect_url'] ) {
			$out['redirect'] = (string) $settings['redirect_url'];
		}
		return $out;
	}

	/**
	 * Validate and sanitize submitted values against the definition.
	 *
	 * @param array<string,mixed> $definition Form definition.
	 * @param array<string,mixed> $raw        Raw values keyed by field id.
	 * @return array{values:array<string,mixed>,errors:array<string,string>}
	 */
	public static function validate_fields( array $definition, array $raw ): array {
		$values = array();
		$errors = array();

		foreach ( Forms::flatten( $definition ) as $field ) {
			$id   = (string) $field['id'];
			$type = (string) $field['type'];
			if ( in_array( $type, FieldTypes::decorative(), true ) ) {
				continue;
			}
			$present = array_key_exists( $id, $raw );
			$value   = $present ? $raw[ $id ] : '';

			switch ( $type ) {
				case 'email':
					$clean = Sanitize::email( $value );
					if ( $present && '' !== trim( (string) $value ) && '' === $clean ) {
						$errors[ $id ] = __( 'Please check this email address. Example: name@example.com', 'estat-os' );
					}
					break;
				case 'phone':
					$clean  = Sanitize::phone( $value );
					$digits = preg_replace( '/\D/', '', $clean );
					if ( '' !== $clean && strlen( (string) $digits ) < 7 ) {
						$errors[ $id ] = __( 'Please check this phone number.', 'estat-os' );
					}
					break;
				case 'number':
					$clean = '' === trim( (string) $value ) ? '' : Sanitize::float( $value );
					break;
				case 'url':
					$clean = Sanitize::url( $value );
					break;
				case 'date':
					$clean = Sanitize::date( $value );
					if ( $present && '' !== trim( (string) $value ) && '' === $clean ) {
						$errors[ $id ] = __( 'Please choose a valid date.', 'estat-os' );
					}
					break;
				case 'datetime':
					$clean = Sanitize::datetime( $value );
					break;
				case 'checkbox':
				case 'consent':
					$clean = Sanitize::bool( $value ) ? 1 : 0;
					break;
				case 'checklist':
					$allowed = wp_list_pluck( (array) $field['options'], 'value' );
					$clean   = array_values( array_intersect( array_map( array( Sanitize::class, 'text' ), (array) $value ), $allowed ) );
					break;
				case 'select':
				case 'radio':
					$allowed = wp_list_pluck( (array) $field['options'], 'value' );
					$clean   = Sanitize::text( $value );
					if ( $allowed && '' !== $clean && ! in_array( $clean, $allowed, true ) ) {
						$errors[ $id ] = __( 'Please choose one of the available options.', 'estat-os' );
						$clean         = '';
					}
					break;
				case 'property':
				case 'project':
				case 'agent':
					$clean = Sanitize::int( $value );
					break;
				case 'textarea':
				case 'message':
				case 'address':
					$clean = Sanitize::textarea( $value );
					break;
				default:
					$clean = Sanitize::text( $value );
					break;
			}

			$is_empty = ( '' === $clean || array() === $clean || 0 === $clean && in_array( $type, array( 'checkbox', 'consent' ), true ) );
			if ( ! empty( $field['required'] ) && $is_empty && ! isset( $errors[ $id ] ) ) {
				$errors[ $id ] = sprintf(
					/* translators: %s: field label */
					__( 'Please fill in "%s".', 'estat-os' ),
					'' !== (string) $field['label'] ? (string) $field['label'] : $id
				);
			}

			$values[ $id ] = $clean;
		}

		return array( 'values' => $values, 'errors' => $errors );
	}

	/**
	 * Resolve a related record id from a mapped field or the request context.
	 *
	 * @param string              $mapped_field Field id configured in settings.
	 * @param array<string,mixed> $values       Submitted values.
	 * @param array<string,mixed> $request      Raw request.
	 * @param string              $fallback_key Request key fallback.
	 * @return int
	 */
	private static function resolve_reference( string $mapped_field, array $values, array $request, string $fallback_key ): int {
		if ( '' !== $mapped_field && isset( $values[ $mapped_field ] ) ) {
			$id = Sanitize::int( $values[ $mapped_field ] );
			if ( $id > 0 ) {
				return $id;
			}
		}
		return Sanitize::int( $request[ $fallback_key ] ?? 0 );
	}

	/**
	 * Pick the first non-empty value among candidate field ids.
	 *
	 * @param array<string,mixed> $values     Values.
	 * @param string[]            $candidates Field ids.
	 * @return string
	 */
	private static function pick( array $values, array $candidates ): string {
		foreach ( $candidates as $candidate ) {
			foreach ( $values as $key => $value ) {
				if ( $key === $candidate || 0 === strpos( (string) $key, $candidate ) ) {
					if ( is_scalar( $value ) && '' !== (string) $value ) {
						return (string) $value;
					}
				}
			}
		}
		return '';
	}

	/**
	 * A salted, non-reversible visitor fingerprint for rate limiting.
	 *
	 * @return string
	 */
	private static function ip_hash(): string {
		$ip = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ); // phpcs:ignore
			$ip = is_string( $ip ) ? $ip : '';
		}
		return hash( 'sha256', $ip . '|' . wp_salt( 'auth' ) );
	}

	/**
	 * Simple transient based rate limit.
	 *
	 * @param string $ip_hash Fingerprint.
	 * @param int    $limit   Allowed submissions per 10 minutes.
	 * @return bool
	 */
	private static function within_rate_limit( string $ip_hash, int $limit ): bool {
		$key   = 'estat_rl_' . substr( $ip_hash, 0, 32 );
		$count = (int) get_transient( $key );
		if ( $count >= max( 1, $limit ) ) {
			return false;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * List submissions for the admin screen.
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return array( 'items' => array(), 'total' => 0 );
		}
		$table    = Schema::table( 'estat_submissions' );
		$per_page = Sanitize::int( $args['per_page'] ?? 20, 1, 100 );
		$page     = Sanitize::int( $args['page'] ?? 1, 1, 10000 );
		$offset   = ( $page - 1 ) * $per_page;
		$form_id  = Sanitize::int( $args['form_id'] ?? 0 );

		if ( $form_id > 0 ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE form_id = %d", $form_id ) );
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE form_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", $form_id, $per_page, $offset ), ARRAY_A );
			// phpcs:enable
		} else {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
			// phpcs:enable
		}
		return array( 'items' => is_array( $items ) ? $items : array(), 'total' => $total );
	}
}
