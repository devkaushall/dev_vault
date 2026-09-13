<?php
/**
 * REST API: namespace estat/v1.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Rest;

use EstatOS\Audit\AuditLog;
use EstatOS\Data\Agents;
use EstatOS\Data\Listings;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Projects;
use EstatOS\Forms\Submissions;
use EstatOS\Leads\Leads;
use EstatOS\Leads\Visits;
use EstatOS\Search\Query;
use EstatOS\Search\SearchIndex;
use EstatOS\Settings\Settings;
use EstatOS\Support\Sanitize;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Public routes expose only marketing information and are rate limited.
 * Everything about people or office operations requires a capability.
 */
final class RestApi {

	/**
	 * API namespace. Public contract.
	 */
	public const NS = 'estat/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NS,
			'/properties',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_properties' ),
				'permission_callback' => '__return_true',
				'args'                => self::search_args(),
			)
		);

		register_rest_route(
			self::NS,
			'/properties/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_property' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array( 'required' => true, 'validate_callback' => 'is_numeric', 'sanitize_callback' => 'absint' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/projects/(?P<id>\d+)/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_project_stats' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array( 'required' => true, 'validate_callback' => 'is_numeric', 'sanitize_callback' => 'absint' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/agents',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_agents' ),
				'permission_callback' => '__return_true',
			)
		);

		// A landing spot for form plugins that can only POST somewhere —
		// Elementor Pro's "Webhook" action being the common one. It is public
		// on purpose (the sender has no login) and protected the same way the
		// public form endpoint is: a shared secret, spam checks and dedupe.
		register_rest_route(
			self::NS,
			'/enquiries',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_enquiry' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/forms/(?P<id>\d+)/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'submit_form' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array( 'required' => true, 'validate_callback' => 'is_numeric', 'sanitize_callback' => 'absint' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/leads',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_leads' ),
					'permission_callback' => static fn() => current_user_can( 'estat_manage_leads' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_lead' ),
					'permission_callback' => static fn() => current_user_can( 'estat_manage_leads' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/leads/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_lead' ),
				'permission_callback' => static fn() => current_user_can( 'estat_manage_leads' ),
			)
		);

		register_rest_route(
			self::NS,
			'/visits',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_visits' ),
					'permission_callback' => static fn() => current_user_can( 'estat_manage_visits' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_visit' ),
					'permission_callback' => static fn() => current_user_can( 'estat_manage_visits' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/visits/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_visit' ),
				'permission_callback' => static fn() => current_user_can( 'estat_manage_visits' ),
			)
		);

		register_rest_route(
			self::NS,
			'/audit',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_audit' ),
				'permission_callback' => static fn() => current_user_can( 'estat_view_audit' ),
			)
		);

		register_rest_route(
			self::NS,
			'/maintenance/(?P<task>[a-z_\-]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'run_maintenance' ),
				'permission_callback' => static fn() => current_user_can( 'estat_run_maintenance' ),
			)
		);
	}

	/**
	 * Search argument schema.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function search_args(): array {
		return array(
			'keyword'       => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'offer'         => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'property_type' => array(),
			'locality'      => array(),
			'bedrooms_min'  => array( 'sanitize_callback' => 'absint' ),
			'price_min'     => array(),
			'price_max'     => array(),
			'area_min'      => array(),
			'area_max'      => array(),
			'orderby'       => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'page'          => array( 'sanitize_callback' => 'absint', 'default' => 1 ),
			'per_page'      => array( 'sanitize_callback' => 'absint', 'default' => 12 ),
		);
	}

	/**
	 * Public property search.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_properties( WP_REST_Request $request ) {
		$limited = self::rate_limit( 'search', 60 );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$results = Query::search( (array) $request->get_params() );
		$items   = array();
		foreach ( $results['ids'] as $id ) {
			$items[] = Listings::to_array( (int) $id, false );
		}
		return new WP_REST_Response(
			array(
				'items' => $items,
				'total' => $results['total'],
				'page'  => $results['page'],
				'pages' => $results['pages'],
			),
			200
		);
	}

	/**
	 * Public property detail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_property( WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		if ( ! $post || PostTypes::LISTING !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'estat_not_found', __( 'That property could not be found.', 'estat-os' ), array( 'status' => 404 ) );
		}
		$data            = Listings::to_array( $id, false );
		$data['agent']   = (int) $data['agent_id'] > 0 ? Agents::to_array( (int) $data['agent_id'] ) : null;
		$data['project'] = (int) $data['project_id'] > 0 ? array( 'id' => (int) $data['project_id'], 'name' => get_the_title( (int) $data['project_id'] ) ) : null;
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Project statistics.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_project_stats( WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		// A draft or trashed project must look exactly like one that never
		// existed, otherwise the reply confirms it is there.
		if ( ! $post || PostTypes::PROJECT !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'estat_not_found', __( 'That society or project could not be found.', 'estat-os' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( Projects::stats( $id ), 200 );
	}

	/**
	 * Public team directory (business contact details only).
	 *
	 * @return WP_REST_Response
	 */
	public static function get_agents(): WP_REST_Response {
		$ids   = get_posts(
			array(
				'post_type'      => PostTypes::AGENT,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$items = array();
		foreach ( $ids as $id ) {
			$items[] = Agents::to_array( (int) $id );
		}
		return new WP_REST_Response( array( 'items' => $items, 'total' => count( $items ) ), 200 );
	}

	/**
	 * Public form submission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function submit_form( WP_REST_Request $request ) {
		$form_id = (int) $request['id'];
		$params  = (array) $request->get_params();

		$nonce = Sanitize::text( $params['estat_nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'estat_form_submit_' . $form_id ) ) {
			return new WP_Error( 'estat_bad_nonce', __( 'This page has been open for a while. Please refresh and send the form again.', 'estat-os' ), array( 'status' => 403 ) );
		}

		$result = Submissions::handle( $form_id, $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Office enquiry list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_leads( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( Leads::query( (array) $request->get_params() ), 200 );
	}

	/**
	 * Create an enquiry from an authorised client.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_lead( WP_REST_Request $request ) {
		$result = Leads::create( (array) $request->get_params() );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 422 ) );
			return $result;
		}
		return new WP_REST_Response( array( 'lead_id' => (int) $result ), 201 );
	}

	/**
	 * Update an enquiry.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_lead( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$params = (array) $request->get_params();
		if ( isset( $params['agent_id'] ) && ! current_user_can( 'estat_assign_leads' ) ) {
			return new WP_Error( 'estat_forbidden', __( 'You are not allowed to assign enquiries.', 'estat-os' ), array( 'status' => 403 ) );
		}
		$result = Leads::update( $id, $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( Leads::get( $id ), 200 );
	}

	/**
	 * Visit list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_visits( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( Visits::query( (array) $request->get_params() ), 200 );
	}

	/**
	 * Schedule a visit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_visit( WP_REST_Request $request ) {
		$result = Visits::schedule( (array) $request->get_params() );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 422 ) );
			return $result;
		}
		return new WP_REST_Response( array( 'visit_id' => (int) $result ), 201 );
	}

	/**
	 * Update a visit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_visit( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$result = Visits::update( $id, (array) $request->get_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( Visits::get( $id ), 200 );
	}

	/**
	 * Activity history.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_audit( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( AuditLog::query( (array) $request->get_params() ), 200 );
	}

	/**
	 * Run an allow-listed maintenance task.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function run_maintenance( WP_REST_Request $request ) {
		$task = sanitize_key( (string) $request['task'] );
		switch ( $task ) {
			case 'rebuild_index':
				$offset = (int) $request->get_param( 'offset' );
				$done   = SearchIndex::rebuild_batch( 200, $offset );
				AuditLog::record( 'maintenance.run', 'maintenance', 0, array( 'task' => $task, 'rows' => $done ) );
				return new WP_REST_Response( array( 'processed' => $done, 'next_offset' => $offset + $done, 'complete' => $done < 200 ), 200 );

			case 'expire_listings':
				$count = \EstatOS\Cron\Scheduler::expire_listings();
				return new WP_REST_Response( array( 'expired' => $count ), 200 );

			case 'index_status':
				return new WP_REST_Response( SearchIndex::status(), 200 );

			default:
				return new WP_Error( 'estat_unknown_task', __( 'That maintenance task does not exist.', 'estat-os' ), array( 'status' => 404 ) );
		}
	}

	/**
	 * Rate limit anonymous public calls.
	 *
	 * @param string $bucket Bucket name.
	 * @param int    $limit  Calls allowed per minute.
	 * @return true|WP_Error
	 */
	private static function rate_limit( string $bucket, int $limit ) {
		if ( is_user_logged_in() ) {
			return true;
		}
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ) : ''; // phpcs:ignore
		$key = 'estat_api_' . $bucket . '_' . substr( hash( 'sha256', $ip . wp_salt( 'auth' ) ), 0, 24 );
		$hits = (int) get_transient( $key );
		if ( $hits >= $limit ) {
			return new WP_Error( 'estat_rate_limited', __( 'Too many requests. Please slow down and try again in a minute.', 'estat-os' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Accept an enquiry posted by another form tool.
	 *
	 * Elementor Pro (and most form plugins) can POST the submitted fields to a
	 * URL. This turns that POST into a proper enquiry, going through exactly
	 * the same checks as our own forms — nothing gets a shortcut into the
	 * database just because it arrived from somewhere else.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_enquiry( WP_REST_Request $request ) {
		$settings = Settings::all();
		$secret   = (string) ( $settings['inbound_secret'] ?? '' );

		// Without a secret configured the door stays shut, rather than
		// silently accepting anything the whole internet sends.
		if ( '' === $secret ) {
			return new WP_Error(
				'estat_inbound_disabled',
				__( 'Incoming enquiries are turned off. Set a secret key in Office Settings first.', 'estat-os' ),
				array( 'status' => 403 )
			);
		}

		$given = (string) $request->get_param( 'secret' );

		if ( '' === $given ) {
			$given = (string) $request->get_header( 'x_estat_secret' );
		}

		// hash_equals keeps the comparison constant-time.
		if ( ! hash_equals( $secret, $given ) ) {
			return new WP_Error(
				'estat_inbound_forbidden',
				__( 'That secret key is not correct.', 'estat-os' ),
				array( 'status' => 403 )
			);
		}

		$fields = $request->get_param( 'fields' );

		// Elementor posts the boxes at the top level rather than under
		// "fields", so accept both shapes.
		if ( ! is_array( $fields ) ) {
			$fields = (array) $request->get_params();
			unset( $fields['secret'], $fields['form_id'], $fields['page_url'], $fields['idempotency_key'] );
		}

		$name = Sanitize::text( $fields['name'] ?? '' );

		$phone = Sanitize::text( $fields['phone'] ?? '' );
		$email = Sanitize::email( $fields['email'] ?? '' );

		if ( '' === $phone && '' === $email ) {
			return new WP_Error(
				'estat_inbound_incomplete',
				__( 'An enquiry needs at least a phone number or an email address.', 'estat-os' ),
				array( 'status' => 422 )
			);
		}

		$lead = Leads::create(
			array(
				'name'              => $name,
				'phone'             => $phone,
				'email'             => $email,
				'message'           => Sanitize::textarea( $fields['message'] ?? '' ),
				'listing_id'        => Sanitize::int( $fields['property_id'] ?? 0 ),
				'source'            => Sanitize::text( $fields['source'] ?? 'website' ),
				'submission_source' => Sanitize::url( $request->get_param( 'page_url' ) ?? '' ),
				'idempotency_key'   => Sanitize::text( $request->get_param( 'idempotency_key' ) ?? '' ),
			)
		);

		if ( is_wp_error( $lead ) ) {
			return $lead;
		}

		AuditLog::record( 'lead.inbound', 'lead', (int) $lead, array( 'via' => 'rest' ) );

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'lead_id' => (int) $lead,
			),
			201
		);
	}
}
