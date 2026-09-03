<?php
/** Read-only geospatial configuration and coordinate health. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Diagnostics;

use Mayfair\RealEstatePlatform\Contracts\DiagnosticCheckInterface;
use Mayfair\RealEstatePlatform\Settings\SettingsManager;
final class GeoCheck implements DiagnosticCheckInterface {
	public function __construct( private SettingsManager $settings ) {}
	public function name(): string {
		return 'Geospatial'; }
	public function run(): DiagnosticResult {
		$ids     = get_posts(
			array(
				'post_type'      => 'property',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);
		$missing = 0;
		$invalid = 0;
		foreach ( $ids as$id ) {
			$lat = get_post_meta( $id, 'rep_latitude', true );
			$lng = get_post_meta( $id, 'rep_longitude', true );
			if ( '' === $lat || '' === $lng ) {
				++$missing;
				continue;
			}$a = (float) $lat;
			$o  = (float) $lng;
			if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) || ! is_finite( $a ) || ! is_finite( $o ) || $a < -90 || $a > 90 || $o < -180 || $o > 180 ) {
				++$invalid;
			}
		}
		$details = array(
			'published'           => count( $ids ),
			'missing_coordinates' => $missing,
			'invalid_coordinates' => $invalid,
			'map_provider'        => $this->settings->get( 'map_provider' ),
			'geocoder_provider'   => $this->settings->get( 'geocoder_provider' ),
			'coordinate_privacy'  => $this->settings->get( 'coordinate_privacy' ),
			'maximum_radius'      => $this->settings->get( 'maximum_geo_radius' ),
			'maximum_map_results' => $this->settings->get( 'maximum_map_results' ),
			'clustering'          => $this->settings->get( 'marker_clustering' ),
			'cache_ttl'           => $this->settings->get( 'geocode_cache_ttl' ),
		);
		return new DiagnosticResult( $this->name(), 0 === $invalid ? DiagnosticResult::PASS : DiagnosticResult::FAIL, 0 === $invalid ? 'Geospatial configuration is valid.' : 'Invalid canonical coordinates detected.', $details, 0 === $invalid ? '' : 'Correct invalid canonical coordinates.' );
	}
}
