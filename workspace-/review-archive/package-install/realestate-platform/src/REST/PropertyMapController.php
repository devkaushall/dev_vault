<?php
/** Public bounded map marker REST adapter. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\REST;

use Mayfair\RealEstatePlatform\Geo\MapSearchRequest;
final class PropertyMapController extends \WP_REST_Controller {
	/** @param array<string,array<string,mixed>> $params */
	public function __construct( private MapSearchRequest $map, private array $params ) {
		$this->namespace = 'realestate-platform/v1';
		$this->rest_base = 'properties/map'; }
	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getItems' ),
				'permission_callback' => '__return_true',
				'args'                => $this->params,
			)
		); }
	public function getItems( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$result = $this->map->execute( $request->get_params() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result ); }
}
