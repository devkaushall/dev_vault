<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Security;

final class Security {
	public static function requireCapability( string $cap ): void {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'realestate-platform' ), '', array( 'response' => 403 ) );
		}} public static function verifyNonce( string $nonce, string $action ): bool {
		return wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), $action ) !== false;
		} public static function safeRedirect( string $url, string $fallback ): never {
			wp_safe_redirect( wp_validate_redirect( $url, $fallback ) );
			exit;
		} public static function validateRemoteUrl( string $url ): string|\WP_Error {
			$clean = esc_url_raw( $url, array( 'https' ) );
			if ( ! $clean || ! wp_http_validate_url( $clean ) ) {
				return new \WP_Error( 'invalid_url', __( 'The URL is not permitted.', 'realestate-platform' ) );
			}$host = (string) wp_parse_url( $clean, PHP_URL_HOST );
			$port  = wp_parse_url( $clean, PHP_URL_PORT );
			if ( null !== $port && 443 !== (int) $port ) {
				return new \WP_Error( 'unsafe_port', __( 'The URL port is not permitted.', 'realestate-platform' ) );
			}
			$ips = gethostbynamel( $host );
			if ( false === $ips || array_filter( $ips, static fn( string $ip ): bool => ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) ) {
				return new \WP_Error( 'unsafe_url', __( 'The URL resolves to a private or reserved network.', 'realestate-platform' ) );
			}return $clean;
		} public static function token( int $bytes = 32 ): string {
			return bin2hex( random_bytes( max( 16, $bytes ) ) );
		} public static function safePath( string $base, string $relative ): string|\WP_Error {
			$relative = str_replace( '\\', '/', $relative );
			if ( str_contains( $relative, "\0" ) || str_contains( $relative, '../' ) || str_starts_with( $relative, '/' ) ) {
				return new \WP_Error( 'invalid_path', __( 'Invalid file path.', 'realestate-platform' ) );
			}$path = wp_normalize_path( trailingslashit( $base ) . ltrim( $relative, '/' ) );
			$root  = wp_normalize_path( trailingslashit( $base ) );
			return str_starts_with( $path, $root ) ? $path : new \WP_Error( 'invalid_path', __( 'Invalid file path.', 'realestate-platform' ) );}
}
