<?php
/** Same-origin AJAX adapter for user features. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\UserFeatures;

use Mayfair\RealEstatePlatform\Security\StrictId;
final class UserFeaturesAjaxController {
	public const ACTION       = 'realestate_platform_user_features';
	public const NONCE_ACTION = self::ACTION;
	public function __construct( private FavoritesService $favorites, private SavedSearchService $saved, private AlertService $alerts, private MutationRateLimiter $rate ) {}
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'dispatch' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'dispatch' ) );
	}
	public function dispatch(): void {
		$result = $this->process( wp_unslash( $_POST ) );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			wp_send_json_error(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				(int) ( is_array( $data ) ? ( $data['status'] ?? 400 ) : 400 )
			);
		}
		wp_send_json_success( $result );
	}
	/** @param array<string,mixed> $input @return mixed */
	public function process( array $input ): mixed {
		$nonce = $input['_ajax_nonce'] ?? '';
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return $this->error( 'invalid_nonce', 'Invalid nonce.', 403 );
		}
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return $this->error( 'authentication_required', 'Authentication is required.', 401 );
		}
		$operation = sanitize_key( is_string( $input['operation'] ?? null ) ? $input['operation'] : '' );
		if ( ! $this->rate->allow( $user_id, '' === $operation ? 'invalid' : $operation ) ) {
			return $this->error( 'rate_limited', 'Too many requests.', 429 );
		}
		if ( 'saved_create' === $operation && ! is_array( $input['criteria'] ?? null ) ) {
			return $this->error( 'invalid_criteria', 'Criteria must be an object.', 400 );
		}
		if ( 'alert_save' === $operation ) {
			$enabled = $this->boolean( $input['enabled'] ?? null );
			if ( null === $enabled ) {
				return $this->error( 'invalid_enabled', 'Enabled must be a boolean.', 400 );
			}
			return $this->alerts->save( $user_id, $this->positiveId( $input['saved_search_id'] ?? null ), is_string( $input['frequency'] ?? null ) ? $input['frequency'] : '', $enabled );
		}
		return match ( $operation ) {
			'favorite_add'    => $this->favorites->add( $user_id, $this->positiveId( $input['property_id'] ?? null ) ),
			'favorite_remove' => $this->favorites->remove( $user_id, $this->positiveId( $input['property_id'] ?? null ) ),
			'favorite_toggle' => $this->favorites->toggle( $user_id, $this->positiveId( $input['property_id'] ?? null ) ),
			'saved_create'    => $this->saved->create( $user_id, is_string( $input['title'] ?? null ) ? $input['title'] : '', $input['criteria'] ),
			'saved_delete'    => $this->saved->delete( $user_id, $this->positiveId( $input['id'] ?? null ) ),
			'alert_delete'    => $this->alerts->delete( $user_id, $this->positiveId( $input['id'] ?? null ) ),
			default           => $this->error( 'invalid_operation', 'Invalid operation.', 400 ),
		};
	}
	private function positiveId( mixed $value ): int {
		return StrictId::parse( $value );
	}
	private function boolean( mixed $value ): ?bool {
		if ( true === $value || false === $value ) {
			return $value; }
		if ( 1 === $value || '1' === $value || 'true' === $value ) {
			return true; }
		if ( 0 === $value || '0' === $value || 'false' === $value ) {
			return false; }
		return null;
	}
	private function error( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
