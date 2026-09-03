<?php
/** @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\UserFeatures;
interface NotificationProviderInterface {
	/** @param list<int> $property_ids */
	public function send( int $user_id, string $saved_search_title, array $property_ids ): bool;
}
