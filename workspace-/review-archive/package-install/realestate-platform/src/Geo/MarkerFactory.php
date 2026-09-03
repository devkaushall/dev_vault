<?php
/** Public marker construction from canonical/indexed results. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Geo;
final class MarkerFactory {
	public function __construct( private CoordinatePrivacy $privacy ) {}
	public function forProperty( int $post_id ): ?MapMarker {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'property' !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		$lat = get_post_meta( $post_id, 'rep_latitude', true );
		$lng = get_post_meta( $post_id, 'rep_longitude', true );
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return null;
		}
		$latitude  = (float) $lat;
		$longitude = (float) $lng;
		if ( ! is_finite( $latitude ) || ! is_finite( $longitude ) || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 ) {
			return null;
		}
		$mode = (string) get_post_meta( $post_id, 'rep_coordinate_privacy', true );
		if ( '' === $mode ) {
			$mode = (string) ( new \Mayfair\RealEstatePlatform\Settings\SettingsManager() )->get( 'coordinate_privacy', 'exact' );
		}
		$point = $this->privacy->expose( $latitude, $longitude, $mode );
		if ( null === $point ) {
			return null;
		}
		$price    = get_post_meta( $post_id, 'rep_price', true );
		$currency = get_post_meta( $post_id, 'rep_currency', true );
		return new MapMarker( $post_id, $point['latitude'], $point['longitude'], wp_strip_all_tags( $post->post_title ), esc_url_raw( (string) get_permalink( $post_id ) ), is_numeric( $price ) ? (float) $price : null, '' === $currency ? null : (string) $currency );
	}
}
