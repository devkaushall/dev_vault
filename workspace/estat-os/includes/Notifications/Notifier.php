<?php
/**
 * Office notifications.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Notifications;

use EstatOS\Field\Requests;
use EstatOS\Leads\Leads;
use EstatOS\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Plain, short emails. Every notification can be switched off in settings and
 * the office is never emailed twice for the same event.
 */
final class Notifier {

	/**
	 * Enquiry IDs already announced this request, so an enquiry is never
	 * emailed twice.
	 *
	 * @var array<int,bool>
	 */
	private static $announced = array();


	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'estat_lead_created', array( __CLASS__, 'on_lead_created' ), 10, 1 );
		add_action( 'estat_visit_scheduled', array( __CLASS__, 'new_visit' ), 10, 1 );
		add_action( 'estat_request_created', array( __CLASS__, 'new_request' ), 10, 2 );
	}

	/**
	 * Announce an enquiry that arrived from anywhere: the REST API, an import,
	 * the admin screens or another plugin.
	 *
	 * Form submissions call new_lead() directly so they can honour the
	 * per-form recipient, so this guards against sending the same enquiry
	 * twice.
	 *
	 * @param int $lead_id Lead ID.
	 * @return void
	 */
	public static function on_lead_created( int $lead_id ): void {
		if ( isset( self::$announced[ $lead_id ] ) ) {
			return;
		}
		self::new_lead( $lead_id );
	}

	/**
	 * Send the mail with a consistent from-name and plain text body.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $body    Body.
	 * @return bool
	 */
	private static function send( string $to, string $subject, string $body ): bool {
		if ( ! is_email( $to ) ) {
			return false;
		}
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		return (bool) wp_mail( $to, wp_specialchars_decode( $subject ), $body, $headers );
	}

	/**
	 * Notify the office about a new enquiry.
	 *
	 * @param int    $lead_id  Lead ID.
	 * @param int    $form_id  Form ID.
	 * @param string $override Optional recipient override.
	 * @return void
	 */
	public static function new_lead( int $lead_id, int $form_id = 0, string $override = '' ): void {
		if ( isset( self::$announced[ $lead_id ] ) ) {
			return;
		}
		self::$announced[ $lead_id ] = true;

		if ( ! Settings::get( 'notify_new_lead' ) ) {
			return;
		}
		$lead = $lead_id > 0 ? Leads::get( $lead_id ) : null;
		$to   = is_email( $override ) ? $override : Settings::notification_email();

		$lines   = array();
		$lines[] = __( 'A new enquiry has arrived.', 'estat-os' );
		$lines[] = '';
		if ( $lead ) {
			$lines[] = __( 'Name:', 'estat-os' ) . ' ' . $lead['name'];
			if ( '' !== (string) $lead['phone'] ) {
				$lines[] = __( 'Phone:', 'estat-os' ) . ' ' . $lead['phone'];
			}
			if ( '' !== (string) $lead['email'] ) {
				$lines[] = __( 'Email:', 'estat-os' ) . ' ' . $lead['email'];
			}
			if ( (int) $lead['listing_id'] > 0 ) {
				$lines[] = __( 'Property:', 'estat-os' ) . ' ' . get_the_title( (int) $lead['listing_id'] );
			}
			if ( '' !== (string) $lead['message'] ) {
				$lines[] = '';
				$lines[] = __( 'Message:', 'estat-os' );
				$lines[] = (string) $lead['message'];
			}
		}
		$lines[] = '';
		$lines[] = __( 'Open your office dashboard to reply:', 'estat-os' );
		$lines[] = admin_url( 'admin.php?page=estat-enquiries' );

		self::send( $to, sprintf( /* translators: %s: office name */ __( '[%s] New enquiry', 'estat-os' ), Settings::office_name() ), implode( "\n", $lines ) );
	}

	/**
	 * Notify about a newly scheduled visit.
	 *
	 * @param int $visit_id Visit ID.
	 * @return void
	 */
	public static function new_visit( int $visit_id ): void {
		if ( ! Settings::get( 'notify_new_visit' ) ) {
			return;
		}
		$visit = \EstatOS\Leads\Visits::get( $visit_id );
		if ( ! $visit ) {
			return;
		}
		$body = implode(
			"\n",
			array(
				__( 'A site visit has been scheduled.', 'estat-os' ),
				'',
				__( 'When:', 'estat-os' ) . ' ' . get_date_from_gmt( (string) $visit['scheduled_at'] ),
				__( 'Property:', 'estat-os' ) . ' ' . ( (int) $visit['listing_id'] > 0 ? get_the_title( (int) $visit['listing_id'] ) : __( 'Not set', 'estat-os' ) ),
				'',
				admin_url( 'admin.php?page=estat-visits' ),
			)
		);
		self::send( Settings::notification_email(), sprintf( /* translators: %s: office name */ __( '[%s] Site visit scheduled', 'estat-os' ), Settings::office_name() ), $body );
	}

	/**
	 * Tell the office somebody on the ground found something the website has
	 * wrong.
	 *
	 * Deliberately short, and no more than that: the detail belongs on the
	 * screen where it gets decided, and a mail that tries to be the screen ends
	 * up being answered badly from a phone.
	 *
	 * @param int $request_id Request ID.
	 * @param int $record_id  The property concerned.
	 * @return void
	 */
	public static function new_request( int $request_id, int $record_id = 0 ): void {
		if ( ! Settings::get( 'notify_change_request' ) ) {
			return;
		}
		$request = \EstatOS\Field\Requests::get( $request_id );
		if ( ! $request ) {
			return;
		}

		$lines = array();

		foreach ( Requests::editable_fields() as $field => $meta ) {
			if ( 'note' === $field || ! isset( $request['changes'][ $field ] ) ) {
				continue;
			}
			$lines[] = esc_html( (string) $meta['label'] ) . ': ' . Requests::describe( (string) $field, (string) $request['changes'][ $field ] );
		}

		$agent = get_userdata( (int) $request['requested_by'] );
		$name  = is_object( $agent ) && isset( $agent->display_name ) ? (string) $agent->display_name : '';

		$body = implode(
			"\n",
			array_merge(
				array(
					__( 'A change has been proposed to a property:', 'estat-os' ),
					'',
					__( 'Property:', 'estat-os' ) . ' ' . (string) get_the_title( (int) $request['record_id'] ),
					__( 'From:', 'estat-os' ) . ' ' . ( '' !== $name ? $name : __( 'someone on your team', 'estat-os' ) ),
				),
				$lines,
				array(
					'',
					admin_url( 'admin.php?page=estat-requests' ),
				)
			)
		);

		self::send( Settings::notification_email(), sprintf( /* translators: %s: office name */ __( '[%s] A property needs a decision', 'estat-os' ), Settings::office_name() ), $body );
	}

	/**
	 * Daily follow-up digest.
	 *
	 * @param int $count Number of follow-ups due.
	 * @return void
	 */
	public static function followups_due( int $count ): void {
		if ( ! Settings::get( 'notify_followup' ) || $count <= 0 ) {
			return;
		}
		$body = sprintf(
			/* translators: %d: number of follow-ups */
			_n( '%d enquiry needs a follow-up today.', '%d enquiries need a follow-up today.', $count, 'estat-os' ),
			$count
		) . "\n\n" . admin_url( 'admin.php?page=estat-enquiries&filter=followup' );
		self::send( Settings::notification_email(), sprintf( /* translators: %s: office name */ __( '[%s] Follow-ups due today', 'estat-os' ), Settings::office_name() ), $body );
	}

	/**
	 * Listing expiry notice.
	 *
	 * @param int $count Number expired.
	 * @return void
	 */
	public static function listings_expired( int $count ): void {
		if ( ! Settings::get( 'notify_expiry' ) || $count <= 0 ) {
			return;
		}
		$body = sprintf(
			/* translators: %d: number of listings */
			_n( '%d listing was taken off the website because it reached its removal date.', '%d listings were taken off the website because they reached their removal date.', $count, 'estat-os' ),
			$count
		);
		self::send( Settings::notification_email(), sprintf( /* translators: %s: office name */ __( '[%s] Listings taken offline', 'estat-os' ), Settings::office_name() ), $body );
	}

	/**
	 * Import finished notice.
	 *
	 * @param array<string,int> $summary Summary counts.
	 * @return void
	 */
	public static function import_finished( array $summary ): void {
		if ( ! Settings::get( 'notify_import' ) ) {
			return;
		}
		$body = implode(
			"\n",
			array(
				__( 'Your spreadsheet import has finished.', 'estat-os' ),
				'',
				__( 'Added:', 'estat-os' ) . ' ' . (int) ( $summary['created'] ?? 0 ),
				__( 'Updated:', 'estat-os' ) . ' ' . (int) ( $summary['updated'] ?? 0 ),
				__( 'Skipped:', 'estat-os' ) . ' ' . (int) ( $summary['skipped'] ?? 0 ),
			)
		);
		self::send( Settings::notification_email(), sprintf( /* translators: %s: office name */ __( '[%s] Import finished', 'estat-os' ), Settings::office_name() ), $body );
	}

	/**
	 * Webhook failure notice.
	 *
	 * @param string $event Event name.
	 * @param string $error Error text.
	 * @return void
	 */
	public static function webhook_failed( string $event, string $error ): void {
		if ( ! Settings::get( 'notify_webhook_fail' ) ) {
			return;
		}
		$body = __( 'A connection to your other system did not work.', 'estat-os' ) . "\n\n"
			. __( 'Event:', 'estat-os' ) . ' ' . $event . "\n"
			. __( 'Details:', 'estat-os' ) . ' ' . $error;
		self::send( Settings::notification_email(), sprintf( /* translators: %s: office name */ __( '[%s] Connection problem', 'estat-os' ), Settings::office_name() ), $body );
	}
}
