<?php
/** Bounded user mutation limiter. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\UserFeatures;
final class MutationRateLimiter {
	public function allow( int $user_id, string $action, int $limit = 30 ): bool {
		$key   = 'rep_rate_' . hash( 'sha256', $user_id . '|' . $action . '|' . gmdate( 'YmdHi' ) );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}set_transient( $key, $count + 1, 2 * MINUTE_IN_SECONDS );
		return true;}
}
