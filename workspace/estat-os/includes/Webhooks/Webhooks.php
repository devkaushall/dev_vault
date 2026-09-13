<?php
/**
 * Optional outgoing CRM connection with HMAC signing and retries.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Webhooks;

use EstatOS\Audit\AuditLog;
use EstatOS\Install\Schema;
use EstatOS\Leads\Leads;
use EstatOS\Notifications\Notifier;
use EstatOS\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Payloads contain only what an integration needs. The shared secret never
 * leaves the server and is never printed anywhere.
 */
final class Webhooks {

	/**
	 * Cron hook used for retries.
	 */
	public const RETRY_HOOK = 'estat_webhook_retry';

	/**
	 * Maximum delivery attempts.
	 */
	private const MAX_ATTEMPTS = 3;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'estat_lead_created', array( __CLASS__, 'on_lead_created' ), 20, 1 );
		add_action( 'estat_visit_scheduled', array( __CLASS__, 'on_visit_scheduled' ), 20, 1 );
		add_action( self::RETRY_HOOK, array( __CLASS__, 'deliver' ), 10, 3 );
	}

	/**
	 * Whether an event is enabled.
	 *
	 * @param string $event Event key.
	 * @return bool
	 */
	private static function enabled( string $event ): bool {
		if ( ! Settings::get( 'webhook_enabled' ) ) {
			return false;
		}
		if ( '' === (string) Settings::get( 'webhook_url' ) ) {
			return false;
		}
		return in_array( $event, (array) Settings::get( 'webhook_events', array() ), true );
	}

	/**
	 * Events an office can subscribe to, in plain language.
	 *
	 * @return array<string,string>
	 */
	public static function events(): array {
		return array(
			'lead.created'      => __( 'A new enquiry arrives', 'estat-os' ),
			'lead.updated'      => __( 'An enquiry changes status or owner', 'estat-os' ),
			'visit.scheduled'   => __( 'A site visit is arranged', 'estat-os' ),
			'visit.completed'   => __( 'A site visit is marked as done', 'estat-os' ),
			'listing.published' => __( 'A property goes live on the website', 'estat-os' ),
		);
	}

	/**
	 * Forward a new enquiry.
	 *
	 * @param int $lead_id Lead ID.
	 * @return void
	 */
	public static function on_lead_created( int $lead_id ): void {
		if ( ! self::enabled( 'lead.created' ) ) {
			return;
		}
		$lead = Leads::get( $lead_id );
		if ( ! $lead ) {
			return;
		}
		self::deliver(
			'lead.created',
			array(
				'lead_id'    => $lead_id,
				'created_at' => $lead['created_at'],
				'name'       => $lead['name'],
				'phone'      => $lead['phone'],
				'email'      => $lead['email'],
				'message'    => $lead['message'],
				'listing_id' => (int) $lead['listing_id'],
				'listing'    => (int) $lead['listing_id'] > 0 ? get_the_title( (int) $lead['listing_id'] ) : '',
				'source'     => $lead['source'],
			),
			1
		);
	}

	/**
	 * Forward a scheduled visit (no personal data beyond the reference).
	 *
	 * @param int $visit_id Visit ID.
	 * @return void
	 */
	public static function on_visit_scheduled( int $visit_id ): void {
		if ( ! self::enabled( 'visit.scheduled' ) ) {
			return;
		}
		$visit = \EstatOS\Leads\Visits::get( $visit_id );
		if ( ! $visit ) {
			return;
		}
		self::deliver(
			'visit.scheduled',
			array(
				'visit_id'     => $visit_id,
				'lead_id'      => (int) $visit['lead_id'],
				'listing_id'   => (int) $visit['listing_id'],
				'scheduled_at' => $visit['scheduled_at'],
			),
			1
		);
	}

	/**
	 * Deliver a signed payload, retrying transient failures.
	 *
	 * @param string              $event   Event key.
	 * @param array<string,mixed> $payload Payload.
	 * @param int                 $attempt Attempt number.
	 * @return void
	 */
	public static function deliver( string $event, array $payload, int $attempt = 1 ): void {
		$url    = (string) Settings::get( 'webhook_url' );
		$secret = (string) Settings::get( 'webhook_secret' );
		if ( '' === $url ) {
			return;
		}

		$body      = (string) wp_json_encode(
			array(
				'event'     => $event,
				'sent_at'   => gmdate( 'c' ),
				'office'    => Settings::office_name(),
				'data'      => $payload,
			)
		);
		$timestamp = (string) time();
		$signature = self::sign( $timestamp . '.' . $body, $secret );

		$response = wp_remote_post(
			$url,
			array(
				'timeout'  => 10,
				'blocking' => true,
				'headers'  => array(
					'Content-Type'      => 'application/json',
					'X-Estat-Event'      => $event,
					'X-Estat-Timestamp'  => $timestamp,
					'X-Estat-Signature'  => 'sha256=' . $signature,
					'User-Agent'        => 'EstatOS/' . ESTAT_VERSION,
				),
				'body'     => $body,
			)
		);

		$code    = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$error   = is_wp_error( $response ) ? $response->get_error_message() : '';
		$success = $code >= 200 && $code < 300;

		self::log( $event, (int) ( $payload['lead_id'] ?? $payload['visit_id'] ?? 0 ), $code, $success, $attempt, $error );
		AuditLog::record( 'webhook.attempt', 'webhook', 0, array( 'event' => $event, 'status' => $code, 'attempt' => $attempt ) );

		if ( $success ) {
			return;
		}
		if ( $attempt < self::MAX_ATTEMPTS ) {
			wp_schedule_single_event( time() + ( 300 * $attempt ), self::RETRY_HOOK, array( $event, $payload, $attempt + 1 ) );
			return;
		}
		Notifier::webhook_failed( $event, '' !== $error ? $error : sprintf( /* translators: %d: HTTP status */ __( 'HTTP status %d', 'estat-os' ), $code ) );
	}

	/**
	 * Compute the HMAC signature for a payload.
	 *
	 * @param string $message Message to sign.
	 * @param string $secret  Shared secret.
	 * @return string Hex signature.
	 */
	public static function sign( string $message, string $secret ): string {
		return hash_hmac( 'sha256', $message, $secret );
	}

	/**
	 * Constant-time signature verification helper for integrators.
	 *
	 * @param string $message   Message.
	 * @param string $secret    Secret.
	 * @param string $signature Provided signature (hex, no prefix).
	 * @return bool
	 */
	public static function verify( string $message, string $secret, string $signature ): bool {
		return hash_equals( self::sign( $message, $secret ), $signature );
	}

	/**
	 * Record a delivery attempt.
	 *
	 * @param string $event     Event.
	 * @param int    $object_id Object ID.
	 * @param int    $code      HTTP status.
	 * @param bool   $success   Success.
	 * @param int    $attempt   Attempt number.
	 * @param string $error     Error message.
	 * @return void
	 */
	private static function log( string $event, int $object_id, int $code, bool $success, int $attempt, string $error ): void {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			Schema::table( 'estat_webhook_log' ),
			array(
				'created_at'  => current_time( 'mysql', true ),
				'event'       => $event,
				'object_id'   => $object_id,
				'status_code' => $code,
				'success'     => $success ? 1 : 0,
				'attempt'     => $attempt,
				'error'       => mb_substr( $error, 0, 255 ),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Recent delivery attempts for the settings screen.
	 *
	 * @param int $limit Rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 20 ): array {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return array();
		}
		$table = Schema::table( 'estat_webhook_log' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", max( 1, min( 100, $limit ) ) ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}
