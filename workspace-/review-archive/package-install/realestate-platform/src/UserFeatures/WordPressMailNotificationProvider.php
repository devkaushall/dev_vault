<?php
/** WordPress-native alert notification provider. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\UserFeatures;
final class WordPressMailNotificationProvider implements NotificationProviderInterface {
	public function send( int $user_id, string $saved_search_title, array $property_ids ): bool {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof \WP_User || ! $property_ids ) {
			return false;
		}
		$subject = sprintf( 'New Properties for %s', wp_strip_all_tags( $saved_search_title ) );
		$body    = sprintf( '%d new public Properties match your saved search.', count( $property_ids ) );
		return wp_mail( $user->user_email, $subject, $body );
	}
}
