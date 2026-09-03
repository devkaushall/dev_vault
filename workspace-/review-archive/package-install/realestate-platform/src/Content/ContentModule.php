<?php
/**
 * Phase 2 content module composition.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Content;

use Mayfair\RealEstatePlatform\Classification\TaxonomyRegistry;
use Mayfair\RealEstatePlatform\Compatibility\OperatingMode;
use Mayfair\RealEstatePlatform\Settings\SettingsManager;

final class ContentModule {
	public function __construct( private ContentRegistrar $content, private TaxonomyRegistry $taxonomies, private SettingsManager $settings ) {}

	public function register(): void {
		add_action( 'init', array( $this, 'initialize' ), 20 );
		add_filter( 'rest_pre_dispatch', array( $this, 'validateRestDispatch' ), 10, 3 );
	}

	public function validateRestDispatch( mixed $response, mixed $server, \WP_REST_Request $request ): mixed {
		unset( $server );
		if ( is_wp_error( $response ) || $response instanceof \WP_HTTP_Response || ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			return $response;
		}
		$route = $request->get_route();
		foreach ( array(
			'properties' => 'property',
			'projects'   => 'project',
			'insights'   => 'insight',
		) as $base => $entity ) {
			if ( '/wp/v2/' . $base === $route || str_starts_with( $route, '/wp/v2/' . $base . '/' ) ) {
				return $this->content->validateRestRequest( $entity, $response, $request );
			}
		}
		return $response;
	}

	public function initialize(): void {
		$mode = OperatingMode::tryFrom( (string) $this->settings->get( 'operating_mode', 'compatibility' ) ) ?? OperatingMode::Compatibility;
		$this->content->register( $mode );
		if ( OperatingMode::Standalone === $mode ) {
			$this->taxonomies->register();
		}
	}
}
