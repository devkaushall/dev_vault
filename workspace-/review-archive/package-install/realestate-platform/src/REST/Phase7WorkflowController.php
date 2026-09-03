<?php
/** Thin Phase 7 REST adapters for leads, requests, and site visits. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\REST;

use Mayfair\RealEstatePlatform\Leads\LeadService;
use Mayfair\RealEstatePlatform\Requests\RequestService;
use Mayfair\RealEstatePlatform\Security\PublicSubmissionRateLimiter;
use Mayfair\RealEstatePlatform\Security\StrictId;
use Mayfair\RealEstatePlatform\SiteVisits\SiteVisitService;

final class Phase7WorkflowController extends \WP_REST_Controller {
	public function __construct( private LeadService $leads, private RequestService $requests, private SiteVisitService $visits, private PublicSubmissionRateLimiter $rate ) {
		$this->namespace = 'realestate-platform/v1';
	}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/leads',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'leadList' ),
					'permission_callback' => array( $this, 'authenticated' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'leadSubmit' ),
					'permission_callback' => array( $this, 'publicSubmission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/leads/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'leadGet' ),
				'permission_callback' => array( $this, 'authenticated' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/leads/(?P<id>\d+)/status',
			array(
				'methods'             => array( 'POST', 'PUT' ),
				'callback'            => array( $this, 'leadStatus' ),
				'permission_callback' => array( $this, 'authenticatedMutation' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/leads/(?P<id>\d+)/assignment',
			array(
				'methods'             => array( 'POST', 'PUT' ),
				'callback'            => array( $this, 'leadAssignment' ),
				'permission_callback' => array( $this, 'authenticatedMutation' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/requests',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'requestSubmit' ),
				'permission_callback' => array( $this, 'publicSubmission' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/requests/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'requestGet' ),
				'permission_callback' => array( $this, 'authenticated' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/site-visits',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'visitList' ),
					'permission_callback' => array( $this, 'authenticated' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'visitSubmit' ),
					'permission_callback' => array( $this, 'publicSubmission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/site-visits/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'visitGet' ),
				'permission_callback' => array( $this, 'authenticated' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/site-visits/(?P<id>\d+)/status',
			array(
				'methods'             => array( 'POST', 'PUT' ),
				'callback'            => array( $this, 'visitStatus' ),
				'permission_callback' => array( $this, 'authenticatedMutation' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/site-visits/(?P<id>\d+)/reschedule',
			array(
				'methods'             => array( 'POST', 'PUT' ),
				'callback'            => array( $this, 'visitReschedule' ),
				'permission_callback' => array( $this, 'authenticatedMutation' ),
			)
		);
	}

	public function authenticated(): bool|\WP_Error {
		return is_user_logged_in() ? true : new \WP_Error( 'authentication_required', 'Authentication is required.', array( 'status' => 401 ) );
	}

	public function publicSubmission( ?\WP_REST_Request $request = null ): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return true;
		}
		$nonce = $request instanceof \WP_REST_Request ? $request->get_header( 'X-WP-Nonce' ) : '';
		return is_string( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' ) ? true : new \WP_Error( 'csrf_required', 'A valid REST nonce is required for authenticated submissions.', array( 'status' => 403 ) );
	}

	public function authenticatedMutation( ?\WP_REST_Request $request = null ): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'authentication_required', 'Authentication is required.', array( 'status' => 401 ) );
		}
		$nonce = $request instanceof \WP_REST_Request ? $request->get_header( 'X-WP-Nonce' ) : '';
		return is_string( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' ) ? true : new \WP_Error( 'csrf_required', 'A valid REST nonce is required for authenticated mutations.', array( 'status' => 403 ) );
	}

	public function leadSubmit( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		return $this->submitRequest( $request, 'leads' );
	}

	public function requestSubmit( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		return $this->submitRequest( $request, 'requests' );
	}

	public function leadList( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$page     = $this->positive( $request->get_param( 'page' ), 1 );
		$per_page = $this->positive( $request->get_param( 'per_page' ), 20 );
		$status   = $request->get_param( 'status' );
		if ( $page < 1 || $per_page < 1 || $per_page > 100 || ( null !== $status && ! is_string( $status ) ) ) {
			return $this->error( 'invalid_lead_query', 400 );
		}
		return $this->respond( $this->leads->list( get_current_user_id(), $page, $per_page, is_string( $status ) ? $status : '' ) );
	}

	public function leadGet( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = $this->positive( $request->get_param( 'id' ) );
		return 0 === $id ? $this->error( 'invalid_id', 400 ) : $this->respond( $this->leads->get( $id, get_current_user_id() ) );
	}

	public function leadStatus( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id     = $this->positive( $request->get_param( 'id' ) );
		$status = $request->get_param( 'status' );
		$note   = $request->get_param( 'note' );
		if ( 0 === $id || ! is_string( $status ) || ( null !== $note && ! is_string( $note ) ) ) {
			return $this->error( 'invalid_lead_transition', 400 );
		}
		return $this->respond( $this->leads->transition( $id, $status, get_current_user_id(), is_string( $note ) ? $note : '' ) );
	}

	public function leadAssignment( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id        = $this->positive( $request->get_param( 'id' ) );
		$agent_id  = $this->optionalPositive( $request, 'agent_id' );
		$agency_id = $this->optionalPositive( $request, 'agency_id' );
		if ( 0 === $id || is_wp_error( $agent_id ) || is_wp_error( $agency_id ) ) {
			return $this->error( 'invalid_relationship', 400 );
		}
		return $this->respond( $this->leads->assign( $id, $agent_id, $agency_id, get_current_user_id() ) );
	}

	public function requestGet( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = $this->positive( $request->get_param( 'id' ) );
		return 0 === $id ? $this->error( 'invalid_id', 400 ) : $this->respond( $this->requests->get( $id, get_current_user_id() ) );
	}

	public function visitSubmit( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->rate->allow( 'site-visit' ) ) {
			return $this->error( 'rate_limited', 429 );
		}
		$input = $this->visitInput( $request );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		$result = $this->visits->createFromArray( $input, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response(
			array(
				'accepted' => true,
				'status'   => 'requested',
			),
			202
		);
	}

	public function visitList( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$page     = $this->positive( $request->get_param( 'page' ), 1 );
		$per_page = $this->positive( $request->get_param( 'per_page' ), 20 );
		$status   = $request->get_param( 'status' );
		if ( $page < 1 || $per_page < 1 || $per_page > 100 || ( null !== $status && ! is_string( $status ) ) ) {
			return $this->error( 'invalid_visit_query', 400 );
		}
		return $this->respond( $this->visits->list( get_current_user_id(), $page, $per_page, is_string( $status ) ? $status : '' ) );
	}

	public function visitGet( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = $this->positive( $request->get_param( 'id' ) );
		return 0 === $id ? $this->error( 'invalid_id', 400 ) : $this->respond( $this->visits->get( $id, get_current_user_id() ) );
	}

	public function visitStatus( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id     = $this->positive( $request->get_param( 'id' ) );
		$status = $request->get_param( 'status' );
		$note   = $request->get_param( 'note' );
		if ( 0 === $id || ! is_string( $status ) || ( null !== $note && ! is_string( $note ) ) ) {
			return $this->error( 'invalid_visit_transition', 400 );
		}
		return $this->respond( $this->visits->transition( $id, $status, get_current_user_id(), is_string( $note ) ? $note : '' ) );
	}

	public function visitReschedule( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id    = $this->positive( $request->get_param( 'id' ) );
		$start = $request->get_param( 'requested_start_at' );
		$end   = $request->get_param( 'requested_end_at' );
		if ( 0 === $id || ! is_string( $start ) || ! is_string( $end ) ) {
			return $this->error( 'invalid_visit_window', 400 );
		}
		return $this->respond( $this->visits->reschedule( $id, $start, $end, get_current_user_id() ) );
	}

	/** @return \WP_REST_Response|\WP_Error */
	private function submitRequest( \WP_REST_Request $request, string $action ): \WP_REST_Response|\WP_Error {
		if ( ! $this->rate->allow( $action ) ) {
			return $this->error( 'rate_limited', 429 );
		}
		$input = $this->submissionInput( $request );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		$result = $this->requests->submit( $input, get_current_user_id(), 'rest' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response(
			array(
				'accepted' => true,
				'status'   => 'received',
			),
			202
		);
	}

	/** @return array<string,mixed>|\WP_Error */
	private function submissionInput( \WP_REST_Request $request ): array|\WP_Error {
		$input           = array( 'source' => 'rest' );
		$idempotency_key = $request->get_header( 'Idempotency-Key' );
		if ( is_string( $idempotency_key ) && '' !== $idempotency_key ) {
			$input['idempotency_key'] = $idempotency_key;
		}
		foreach ( array( 'name', 'email', 'phone', 'message', 'property_id', 'project_id', 'consent', 'website_url' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) {
				$input[ $key ] = $value;
			}
		}
		return $input;
	}

	/** @return array<string,mixed>|\WP_Error */
	private function visitInput( \WP_REST_Request $request ): array|\WP_Error {
		$input = $this->submissionInput( $request );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		foreach ( array( 'lead_id', 'requested_start_at', 'requested_end_at' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) {
				$input[ $key ] = $value;
			}
		}
		return $input;
	}

	private function positive( mixed $value, int $fallback = 0 ): int {
		return null === $value ? $fallback : StrictId::parse( $value );
	}

	private function optionalPositive( \WP_REST_Request $request, string $key ): int|\WP_Error {
		$value = $request->get_param( $key );
		if ( null === $value || '' === $value ) {
			return 0;
		}
		$id = StrictId::parse( $value );
		return $id > 0 ? $id : $this->error( 'invalid_id', 400 );
	}

	private function respond( mixed $result ): \WP_REST_Response|\WP_Error {
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	private function error( string $code, int $status ): \WP_Error {
		return new \WP_Error( $code, 'The workflow request could not be completed.', array( 'status' => $status ) );
	}
}
