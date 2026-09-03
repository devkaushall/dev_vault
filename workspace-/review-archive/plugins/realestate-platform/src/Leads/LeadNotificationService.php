<?php
/** Asynchronous notification outbox for lead workflows. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Leads;

final class LeadNotificationService {
	public function __construct( private LeadNotificationProviderInterface $provider ) {}

	/** @param array<string,mixed> $payload */
	public function enqueue( string $event_type, string $aggregate_type, int $aggregate_id, array $payload, int $recipient_user_id, string $recipient_email, string $dedupe_key ): int|\WP_Error {
		if ( $aggregate_id < 1 || '' === $event_type || '' === $aggregate_type || '' === $dedupe_key || strlen( $dedupe_key ) > 191 ) {
			return new \WP_Error( 'invalid_notification', 'Notification event is invalid.', array( 'status' => 400 ) );
		}
		if ( '' !== $recipient_email && ! is_email( $recipient_email ) ) {
			return new \WP_Error( 'invalid_notification_recipient', 'Notification recipient is invalid.', array( 'status' => 400 ) );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'rep_notification_events';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is an internal plugin identifier; the dedupe key is prepared.
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE dedupe_key=%s LIMIT 1", $dedupe_key ) );
		if ( null !== $existing ) {
			return (int) $existing;
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		$ok  = $wpdb->insert(
			$table,
			array(
				'event_type'        => sanitize_key( $event_type ),
				'aggregate_type'    => sanitize_key( $aggregate_type ),
				'aggregate_id'      => $aggregate_id,
				'recipient_user_id' => max( 0, $recipient_user_id ),
				'recipient_email'   => sanitize_email( $recipient_email ),
				'payload_json'      => (string) wp_json_encode( $payload ),
				'dedupe_key'        => sanitize_text_field( $dedupe_key ),
				'status'            => 'pending',
				'attempts'          => 0,
				'next_attempt_at'   => null,
				'last_error'        => '',
				'created_at'        => $now,
				'updated_at'        => $now,
				'sent_at'           => null,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $ok ) {
			// A concurrent enqueue can win the unique dedupe key between the read and insert.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is internal; the dedupe key is prepared.
			$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $table . ' WHERE dedupe_key=%s LIMIT 1', $dedupe_key ) );
			return null !== $existing ? (int) $existing : new \WP_Error( 'notification_enqueue_failed', 'Notification event could not be queued.', array( 'status' => 500 ) );
		}
		return (int) $wpdb->insert_id;
	}

	/** @return array{processed:int,sent:int,failed:int} */
	public function dispatch( int $limit = 25 ): array {
		$limit = max( 1, min( 100, $limit ) );
		global $wpdb;
		$table        = $wpdb->prefix . 'rep_notification_events';
		$now          = gmdate( 'Y-m-d H:i:s' );
		$stale_before = gmdate( 'Y-m-d H:i:s', time() - 900 );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is an internal plugin identifier; all values are prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ((status=%s OR status=%s) AND (next_attempt_at IS NULL OR next_attempt_at<=%s) OR (status=%s AND updated_at<=%s)) ORDER BY id ASC LIMIT %d",
				'pending',
				'failed',
				$now,
				'processing',
				$stale_before,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$summary = array(
			'processed' => 0,
			'sent'      => 0,
			'failed'    => 0,
		);
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$id = (int) ( $row['id'] ?? 0 );
			// Claim before delivery so overlapping cron workers cannot send the same event twice.
			$claimed = $wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is an internal plugin identifier; all values are prepared.
					"UPDATE {$table} SET status=%s,attempts=attempts+1,next_attempt_at=NULL,updated_at=%s WHERE id=%d AND ((status=%s OR status=%s) AND (next_attempt_at IS NULL OR next_attempt_at<=%s) OR (status=%s AND updated_at<=%s))",
					'processing',
					$now,
					$id,
					'pending',
					'failed',
					$now,
					'processing',
					$stale_before
				)
			);
			if ( 1 !== (int) $claimed ) {
				continue;
			}
			++$summary['processed'];
			$payload = json_decode( (string) ( $row['payload_json'] ?? '' ), true );
			$event   = array(
				'event_type'        => (string) ( $row['event_type'] ?? '' ),
				'aggregate_type'    => (string) ( $row['aggregate_type'] ?? '' ),
				'aggregate_id'      => (int) ( $row['aggregate_id'] ?? 0 ),
				'recipient_user_id' => (int) ( $row['recipient_user_id'] ?? 0 ),
				'recipient_email'   => (string) ( $row['recipient_email'] ?? '' ),
				'payload'           => is_array( $payload ) ? $payload : array(),
			);
			$sent    = false;
			try {
				$sent = $this->provider->send( $event );
			} catch ( \Throwable $exception ) {
				$sent = false;
			}
			$attempts = (int) ( $row['attempts'] ?? 0 ) + 1;
			if ( $sent ) {
				++$summary['sent'];
				$wpdb->query(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is an internal plugin identifier; all values are prepared.
						"UPDATE {$table} SET status=%s,attempts=%d,last_error=%s,sent_at=%s,updated_at=%s WHERE id=%d AND status=%s",
						'sent',
						$attempts,
						'',
						$now,
						$now,
						$id,
						'processing'
					)
				);
			} else {
				++$summary['failed'];
				$retry = gmdate( 'Y-m-d H:i:s', time() + min( 86400, 300 * ( 2 ** min( 8, $attempts - 1 ) ) ) );
				$wpdb->query(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is an internal plugin identifier; all values are prepared.
						"UPDATE {$table} SET status=%s,attempts=%d,last_error=%s,next_attempt_at=%s,updated_at=%s WHERE id=%d AND status=%s",
						'failed',
						$attempts,
						'Provider delivery failed.',
						$retry,
						$now,
						$id,
						'processing'
					)
				);
			}
		}
		return $summary;
	}
}
