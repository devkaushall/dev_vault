<?php
/** Authenticated user-feature REST endpoints. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\REST;
use Mayfair\RealEstatePlatform\UserFeatures\AlertService;
use Mayfair\RealEstatePlatform\UserFeatures\CompareService;
use Mayfair\RealEstatePlatform\UserFeatures\FavoritesService;
use Mayfair\RealEstatePlatform\UserFeatures\MutationRateLimiter;
use Mayfair\RealEstatePlatform\UserFeatures\SavedSearchService;
use Mayfair\RealEstatePlatform\Security\StrictId;
final class UserFeaturesController extends \WP_REST_Controller {
	public function __construct( private FavoritesService $favorites, private CompareService $compare, private SavedSearchService $saved, private AlertService $alerts, private MutationRateLimiter $rate ) {
		$this->namespace = 'realestate-platform/v1'; }
	public function registerRoutes(): void {
		$auth = fn() => is_user_logged_in() ? true : new \WP_Error( 'authentication_required', 'Authentication is required.', array( 'status' => 401 ) );
		register_rest_route(
			$this->namespace,
			'/me/favorites',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'favoritesList' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			$this->namespace,
			'/me/favorites/(?P<id>\d+)',
			array(
				'methods'             => array( 'GET', 'POST', 'DELETE' ),
				'callback'            => array( $this, 'favoriteItem' ),
				'permission_callback' => $auth,
				'args'                => array(
					'id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/compare',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'compare' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ids' => array(
						'required' => true,
						'type'     => 'array',
						'maxItems' => 4,
						'items'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/me/saved-searches',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'savedCollection' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			$this->namespace,
			'/me/saved-searches/(?P<id>\d+)',
			array(
				'methods'             => array( 'GET', 'PUT', 'DELETE' ),
				'callback'            => array( $this, 'savedItem' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			$this->namespace,
			'/me/alerts/(?P<id>\d+)',
			array(
				'methods'             => array( 'GET', 'DELETE' ),
				'callback'            => array( $this, 'alertItem' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			$this->namespace,
			'/me/saved-searches/(?P<id>\d+)/alert',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'alertSave' ),
				'permission_callback' => $auth,
			)
		);
	}
	public function favoritesList( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$page     = $this->pagination( $r, 'page', 1, 1, PHP_INT_MAX );
		$per_page = $this->pagination( $r, 'per_page', 20, 1, 100 );
		return is_wp_error( $page ) || is_wp_error( $per_page ) ? $this->invalid( 'invalid_pagination', 'Pagination values are invalid.' ) : $this->respond( $this->favorites->list( get_current_user_id(), $page, $per_page ) );
	}
	public function favoriteItem( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$u  = get_current_user_id();
		$id = $this->positiveId( $r->get_param( 'id' ) );
		if ( 0 === $id ) {
			return $this->invalid( 'invalid_id', 'ID must be a positive integer.' ); }
		if ( 'GET' === $r->get_method() ) {
			return $this->respond( array( 'favorite' => $this->favorites->contains( $u, $id ) ) );
		}if ( ! $this->rate->allow( $u, 'favorite' ) ) {
			return $this->respond( new \WP_Error( 'rate_limited', 'Too many requests.', array( 'status' => 429 ) ) );
		}$x = 'DELETE' === $r->get_method() ? $this->favorites->remove( $u, $id ) : $this->favorites->add( $u, $id );
		return $this->respond( is_wp_error( $x ) ? $x : array( 'favorite' => 'DELETE' !== $r->get_method() ) );
	}
	public function compare( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$ids = $r->get_param( 'ids' );
		if ( ! is_array( $ids ) || count( $ids ) > CompareService::MAX_ITEMS ) {
			return $this->invalid( 'invalid_compare', 'IDs must be an array containing no more than four items.' ); }
		foreach ( $ids as $id ) {
			if ( 0 === $this->positiveId( $id ) ) {
				return $this->invalid( 'invalid_id', 'Each ID must be a positive integer.' ); }
		}
		return $this->respond( $this->compare->compare( $ids ) );
	}
	public function savedCollection( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$user_id = get_current_user_id();
		if ( 'GET' === $r->get_method() ) {
			$page     = $this->pagination( $r, 'page', 1, 1, PHP_INT_MAX );
			$per_page = $this->pagination( $r, 'per_page', 20, 1, 100 );
			return is_wp_error( $page ) || is_wp_error( $per_page ) ? $this->invalid( 'invalid_pagination', 'Pagination values are invalid.' ) : $this->respond( $this->saved->list( $user_id, $page, $per_page ) );
		}
		$title    = $r->get_param( 'title' );
		$criteria = $r->get_param( 'criteria' );
		if ( ! is_string( $title ) || ! is_array( $criteria ) ) {
			return $this->invalid( 'invalid_saved_search', 'Title must be a string and criteria must be an object.' );
		}
		if ( ! $this->rate->allow( $user_id, 'saved' ) ) {
			return $this->invalid( 'rate_limited', 'Too many requests.', 429 );
		}
		return $this->respond( $this->saved->create( $user_id, $title, $criteria ) );
	}
	public function savedItem( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$user_id = get_current_user_id();
		$id      = $this->positiveId( $r->get_param( 'id' ) );
		if ( 0 === $id ) {
			return $this->invalid( 'invalid_id', 'ID must be a positive integer.' ); }
		if ( 'GET' === $r->get_method() ) {
			return $this->respond( $this->saved->get( $user_id, $id ) ); }
		if ( 'DELETE' === $r->get_method() ) {
			return $this->respond( $this->saved->delete( $user_id, $id ) ); }
		$title    = $r->get_param( 'title' );
		$criteria = $r->get_param( 'criteria' );
		$enabled  = $this->boolean( $r->get_param( 'enabled' ) );
		if ( ! is_string( $title ) || ! is_array( $criteria ) || null === $enabled ) {
			return $this->invalid( 'invalid_saved_search', 'Title, criteria, or enabled is invalid.' ); }
		return $this->respond( $this->saved->update( $user_id, $id, $title, $criteria, $enabled ) );
	}
	public function alertSave( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$user_id   = get_current_user_id();
		$id        = $this->positiveId( $r->get_param( 'id' ) );
		$frequency = $r->get_param( 'frequency' );
		$enabled   = $this->boolean( $r->get_param( 'enabled' ) );
		if ( 0 === $id || ! is_string( $frequency ) || null === $enabled ) {
			return $this->invalid( 'invalid_alert', 'Saved-search ID, frequency, or enabled is invalid.' ); }
		if ( ! $this->rate->allow( $user_id, 'alert' ) ) {
			return $this->invalid( 'rate_limited', 'Too many requests.', 429 ); }
		return $this->respond( $this->alerts->save( $user_id, $id, $frequency, $enabled ) );
	}
	public function alertItem( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$id = $this->positiveId( $r->get_param( 'id' ) );
		if ( 0 === $id ) {
			return $this->invalid( 'invalid_id', 'ID must be a positive integer.' ); }
		return $this->respond( 'DELETE' === $r->get_method() ? $this->alerts->delete( get_current_user_id(), $id ) : $this->alerts->get( get_current_user_id(), $id ) );
	}
	private function pagination( \WP_REST_Request $r, string $key, int $fallback, int $min, int $max ): int|\WP_Error {
		$value = $r->get_param( $key );
		if ( null === $value ) {
			return $fallback; }
		$id = $this->positiveId( $value );
		return $id >= $min && $id <= $max ? $id : new \WP_Error( 'invalid_pagination' );
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
	private function invalid( string $code, string $message = 'Invalid request.', int $status = 400 ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) ); }
	private function respond( mixed $x ): \WP_REST_Response|\WP_Error {
		return is_wp_error( $x ) ? $x : rest_ensure_response( $x ); }
}
