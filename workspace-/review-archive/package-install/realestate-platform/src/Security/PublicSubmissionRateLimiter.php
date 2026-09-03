<?php
/** Anonymous submission abuse guard without storing raw network identifiers. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Security;

final class PublicSubmissionRateLimiter {
	public function allow( string $action, int $limit = 10, int $window = 600 ): bool {
		$identity = (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) . '|' . (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		$salt     = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'realestate-platform';
		$key      = 'rep_public_' . substr( hash_hmac( 'sha256', $action . '|' . $identity, $salt ), 0, 32 );
		$count    = (int) get_transient( $key );
		if ( $count >= max( 1, $limit ) ) {
			return false;
		}
		set_transient( $key, $count + 1, max( 60, $window ) );
		return true;
	}
}
