<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Compatibility;

final class CompatibilityDetector {
	/** @return array<string, mixed> */
	public function snapshot(): array {
		global $wp_version, $wp_rest_server;
		$plugins = (array) get_option( 'active_plugins', array() );
		$classes = get_declared_classes();
		$routes  = function_exists( 'rest_get_server' ) ? array_keys( rest_get_server()->get_routes() ) : array();
		return array(
			'php'                 => PHP_VERSION,
			'wordpress'           => (string) $wp_version,
			'mayfair_core'        => $this->pluginMatch( $plugins, array( 'mayfair-core', 'mayfair_core' ) ) || defined( 'MAYFAIR_CORE_VERSION' ),
			'mayfair_forms_leads' => $this->pluginMatch( $plugins, array( 'mayfair-forms', 'mayfair_forms', 'mayfair-leads' ) ),
			'acf'                 => class_exists( 'ACF' ) || defined( 'ACF_VERSION' ),
			'elementor'           => did_action( 'elementor/loaded' ) > 0 || class_exists( 'Elementor\\Plugin' ),
			'elementor_pro'       => defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( 'ElementorPro\\Plugin' ),
			'woocommerce'         => class_exists( 'WooCommerce' ),
			'post_types'          => array_values( array_intersect( array( 'property', 'project', 'insight' ), get_post_types( array(), 'names' ) ) ),
			'taxonomies'          => array_values( array_filter( get_taxonomies( array(), 'names' ), static fn( $t )=>str_starts_with( (string) $t, 'mpd_' ) || str_starts_with( (string) $t, 'mayfair_' ) ) ),
			'rest_namespaces'     => $this->namespaces( $routes ),
		);
	} /** @param list<mixed> $plugins
	 * @param list<string> $needles */
	private function pluginMatch( array $plugins, array $needles ): bool {
		foreach ( $plugins as $p ) {
			foreach ( $needles as $n ) {
				if ( str_contains( strtolower( (string) $p ), $n ) ) {
					return true;
				}
			}
		}return false;
	} /** @param list<string> $routes
	 * @return list<string> */
	private function namespaces( array $routes ): array {
		$out = array();
		foreach ( $routes as $r ) {
			if ( preg_match( '#^/([^/]+/v[0-9]+)(?:/|$)#', (string) $r, $m ) && preg_match( '/mayfair|mpd/i', $m[1] ) ) {
				$out[] = $m[1];
			}
		}return array_values( array_unique( $out ) );
	} public function recommendedMode(): OperatingMode {
		$s = $this->snapshot();
		return ( $s['mayfair_core'] || $s['post_types'] ) ? OperatingMode::Compatibility : OperatingMode::Standalone;}
}
