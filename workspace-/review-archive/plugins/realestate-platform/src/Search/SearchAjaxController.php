<?php
/** Same-origin AJAX adapter for public search. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Search;

final class SearchAjaxController {
	public const ACTION       = 'realestate_platform_search';
	public const NONCE_ACTION = 'realestate_platform_search';
	public function __construct( private SearchRequest $search ) {}
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'dispatch' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'dispatch' ) );
	}
	public function dispatch(): void {
		$result = $this->process( wp_unslash( $_REQUEST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by process before use.
		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 400 );
			wp_send_json_error(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				$status
			);
		}
		wp_send_json_success( $result, 200 );
	}
	/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
	public function process( array $input ): array|\WP_Error {
		$nonce = $input['_ajax_nonce'] ?? '';
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new \WP_Error( 'realestate_platform_invalid_nonce', 'Invalid search nonce.', array( 'status' => 403 ) );
		}
		unset( $input['_ajax_nonce'], $input['action'] );
		return $this->search->execute( $input );
	}
}
