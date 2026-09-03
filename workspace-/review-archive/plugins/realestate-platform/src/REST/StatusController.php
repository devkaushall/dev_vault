<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\REST;

use Mayfair\RealEstatePlatform\Compatibility\CompatibilityDetector;
use Mayfair\RealEstatePlatform\Diagnostics\DiagnosticsRunner;

final class StatusController extends \WP_REST_Controller {
	public function __construct(
		private DiagnosticsRunner $diagnostics,
		private CompatibilityDetector $detector
	) {
		$this->namespace = 'realestate-platform/v1';
		$this->rest_base = 'status';
	}

	public function registerRoutes(): void {
		register_rest_route(
			'realestate-platform/v1',
			'/' . $this->rest_base,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getItem' ),
				'permission_callback' => array( $this, 'permissionsCheck' ),
				'args'                => array(),
				'schema'              => array( $this, 'getPublicItemSchema' ),
			)
		);
	}

	public function permissionsCheck( \WP_REST_Request $request ): bool|\WP_Error {
		unset( $request );
		return current_user_can( 'view_realestate_diagnostics' )
			? true
			: new \WP_Error(
				'realestate_platform_forbidden',
				__( 'You are not allowed to view platform status.', 'realestate-platform' ),
				array( 'status' => 403 )
			);
	}

	public function getItem( \WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );
		return rest_ensure_response(
			array(
				'version'        => REALESTATE_PLATFORM_VERSION,
				'schema_version' => (string) get_option( 'realestate_platform_db_version', '0' ),
				'mode'           => $this->detector->recommendedMode()->value,
				'diagnostics'    => $this->diagnostics->summary(),
			)
		);
	}

	/** @return array<string, mixed> */
	public function getPublicItemSchema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'realestate-platform-status',
			'type'       => 'object',
			'properties' => array(
				'version'        => array(
					'type'     => 'string',
					'readonly' => true,
				),
				'schema_version' => array(
					'type'     => 'string',
					'readonly' => true,
				),
				'mode'           => array(
					'type' => 'string',
					'enum' => array( 'standalone', 'compatibility', 'migration' ),
				),
				'diagnostics'    => array(
					'type'     => 'object',
					'readonly' => true,
				),
			),
		);
	}
}
