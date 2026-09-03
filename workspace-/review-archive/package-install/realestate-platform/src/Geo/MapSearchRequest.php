<?php
/** Bounded public map marker request adapter. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Geo;

use Mayfair\RealEstatePlatform\Search\SearchRequest;
use Mayfair\RealEstatePlatform\Settings\SettingsManager;
final class MapSearchRequest {
	public function __construct( private SearchRequest $search, private MarkerFactory $markers, private SettingsManager $settings ) {}
	/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
	public function execute( array $input ): array|\WP_Error {
		if ( ! isset( $input['radius'] ) && ! isset( $input['north'] ) ) {
			return new \WP_Error( 'realestate_platform_map_viewport_required', 'A radius or complete viewport is required.', array( 'status' => 400 ) );
		}
		$limit             = (int) $this->settings->get( 'maximum_map_results', 100 );
		$input['per_page'] = min( $limit, isset( $input['per_page'] ) ? (int) $input['per_page'] : $limit, 100 );
		$result            = $this->search->execute( $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$ids = array_map(
			static function ( $item ): int {
				$data = $item->jsonSerialize();
				return (int) $data['id'];
			},
			$result['results']
		);
		_prime_post_caches( $ids, false, false );
		update_meta_cache( 'post', $ids );
		$markers = array();
		foreach ( $result['results'] as $item ) {
			$data   = $item->jsonSerialize();
			$marker = $this->markers->forProperty( (int) $data['id'] );
			if ( null !== $marker ) {
				$markers[] = $marker;
			}
		}
		return array(
			'markers'         => $markers,
			'pagination'      => $result['pagination'],
			'applied_filters' => $result['applied_filters'],
			'clusterable'     => (bool) $this->settings->get( 'marker_clustering', true ),
		);
	}
}
