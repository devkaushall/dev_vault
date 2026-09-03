<?php
/** Bounded Agent and Agency REST adapter. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\REST;
use Mayfair\RealEstatePlatform\Profiles\ProfileService;
use Mayfair\RealEstatePlatform\Security\StrictId;
final class ProfilesController extends \WP_REST_Controller {
	public function __construct( private ProfileService $profiles ) {
		$this->namespace = 'realestate-platform/v1'; }
	public function registerRoutes(): void {
		foreach ( array(
			'agents'   => 'agent',
			'agencies' => 'agency',
		) as $route => $type ) {
			register_rest_route(
				$this->namespace,
				'/' . $route,
				array(
					array(
						'methods'             => 'GET',
						'callback'            => fn( \WP_REST_Request $r ) => $this->listing( $type, $r ),
						'permission_callback' => '__return_true',
					),
					array(
						'methods'             => 'POST',
						'callback'            => fn( \WP_REST_Request $r ) => $this->create( $type, $r ),
						'permission_callback' => array( $this, 'authenticated' ),
					),
				)
			);
			register_rest_route(
				$this->namespace,
				'/' . $route . '/(?P<id>\d+)',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => fn( \WP_REST_Request $r ) => $this->single( $type, $r ),
						'permission_callback' => '__return_true',
					),
					array(
						'methods'             => 'PUT',
						'callback'            => fn( \WP_REST_Request $r ) => $this->update( $type, $r ),
						'permission_callback' => array( $this, 'authenticated' ),
					),
					array(
						'methods'             => 'DELETE',
						'callback'            => fn( \WP_REST_Request $r ) => $this->delete( $type, $r ),
						'permission_callback' => array( $this, 'authenticated' ),
					),
				)
			);
		}
		register_rest_route(
			$this->namespace,
			'/agents/(?P<id>\d+)/agency',
			array(
				'methods'             => array( 'PUT', 'DELETE' ),
				'callback'            => array( $this, 'assignAgency' ),
				'permission_callback' => array( $this, 'authenticated' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/properties/(?P<id>\d+)/profile',
			array(
				'methods'             => array( 'PUT', 'DELETE' ),
				'callback'            => array( $this, 'assignProperty' ),
				'permission_callback' => array( $this, 'authenticated' ),
			)
		);
	}
	public function authenticated(): bool|\WP_Error {
		return is_user_logged_in() ? true : $this->error( 'authentication_required', 401 ); }
	private function listing( string $type, \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$page     = $this->positive( $request->get_param( 'page' ), 1 );
		$per_page = $this->positive( $request->get_param( 'per_page' ), 20 );
		$search   = $request->get_param( 'search' );
		if ( 0 === $page || $per_page < 1 || $per_page > 100 || ( null !== $search && ! is_string( $search ) ) ) {
			return $this->error( 'invalid_request', 400 ); }
		return rest_ensure_response( $this->profiles->listPublic( $type, $page, $per_page, (string) $search ) );
	}
	private function single( string $type, \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = $this->positive( $request->get_param( 'id' ) );
		return 0 === $id ? $this->error( 'invalid_id', 400 ) : $this->respond( $this->profiles->get( $type, $id ) ); }
	private function create( string $type, \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$input = $this->input( $request );
		return is_wp_error( $input ) ? $input : $this->respond( $this->profiles->create( $type, $input, get_current_user_id() ) ); }
	private function update( string $type, \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id    = $this->positive( $request->get_param( 'id' ) );
		$input = $this->input( $request );
		return 0 === $id || is_wp_error( $input ) ? $this->error( 'invalid_request', 400 ) : $this->respond( $this->profiles->update( $type, $id, $input, get_current_user_id() ) ); }
	private function delete( string $type, \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = $this->positive( $request->get_param( 'id' ) );
		return 0 === $id ? $this->error( 'invalid_id', 400 ) : $this->respond( $this->profiles->delete( $type, $id, get_current_user_id() ) ); }
	public function assignAgency( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$agent = $this->positive( $request->get_param( 'id' ) );
		if ( 0 === $agent ) {
			return $this->error( 'invalid_relationship', 400 ); }
		if ( 'DELETE' === $request->get_method() ) {
			return $this->respond( $this->profiles->removeAgency( $agent, get_current_user_id() ) ); }
		$agency = $this->positive( $request->get_param( 'agency_id' ) );
		return 0 === $agency ? $this->error( 'invalid_relationship', 400 ) : $this->respond( $this->profiles->assignAgency( $agent, $agency, get_current_user_id() ) ); }
	public function assignProperty( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$property = $this->positive( $request->get_param( 'id' ) );
		if ( 0 === $property ) {
			return $this->error( 'invalid_relationship', 400 );
		}
		if ( 'DELETE' === $request->get_method() ) {
			return $this->respond( $this->profiles->removeProperty( $property, get_current_user_id() ) );
		}
		$agent  = $this->positive( $request->get_param( 'agent_id' ) );
		$agency = $this->positive( $request->get_param( 'agency_id' ) );
		return 0 === $agent || 0 === $agency ? $this->error( 'invalid_relationship', 400 ) : $this->respond( $this->profiles->assignProperty( $property, $agent, $agency, get_current_user_id() ) ); }
	/** @return array<string,mixed>|\WP_Error */
	private function input( \WP_REST_Request $request ): array|\WP_Error {
		$allowed = array( 'title', 'content', 'status', 'public_phone', 'public_email', 'website', 'license_number', 'office_address', 'private_notes' );
		$input   = array();
		foreach ( $allowed as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) {
				if ( ! is_string( $value ) ) {
					return $this->error( 'invalid_' . $key, 400 );
				} $input[ $key ] = $value; }
		}
		return $input;
	}
	private function positive( mixed $value, int $fallback = 0 ): int {
		return null === $value ? $fallback : StrictId::parse( $value );
	}
	private function respond( mixed $result ): \WP_REST_Response|\WP_Error {
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result ); }
	private function error( string $code, int $status ): \WP_Error {
		return new \WP_Error( $code, 'Profile request could not be completed.', array( 'status' => $status ) ); }
}
