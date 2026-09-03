<?php
/** WordPress mail provider for lead-workflow notifications. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Leads;

final class WordPressLeadNotificationProvider implements LeadNotificationProviderInterface {
	/** @param array<string,mixed> $event */
	public function send( array $event ): bool {
		$email = $event['recipient_email'] ?? '';
		if ( ! is_string( $email ) || ! is_email( $email ) ) {
			return false;
		}
		$type      = isset( $event['event_type'] ) && is_string( $event['event_type'] ) ? $event['event_type'] : 'workflow_update';
		$aggregate = isset( $event['aggregate_id'] ) ? (int) $event['aggregate_id'] : 0;
		// translators: %s is an allowlisted workflow event type.
		$subject = sprintf( __( 'Real-estate workflow update: %s', 'realestate-platform' ), sanitize_key( $type ) );
		// translators: %d is an internal aggregate record ID.
		$body = sprintf( __( 'A real-estate workflow record was updated. Record ID: %d.', 'realestate-platform' ), $aggregate );
		return wp_mail( $email, $subject, $body );
	}
}
