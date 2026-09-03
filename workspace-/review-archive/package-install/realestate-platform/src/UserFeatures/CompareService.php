<?php
/** Stateless bounded compare state; persistence belongs to the client. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\UserFeatures;
final class CompareService {
	public const MAX_ITEMS = 4;
	/** @param list<int|string> $ids @return list<array<string,mixed>>|\WP_Error */
	public function compare( array $ids ): array|\WP_Error {
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		if ( count( $ids ) > self::MAX_ITEMS ) {
			return new \WP_Error( 'compare_limit', 'A maximum of four Properties may be compared.', array( 'status' => 400 ) );
		}$out = array();
		foreach ( $ids as$id ) {
			$p = get_post( $id );
			if ( ! $p instanceof \WP_Post || 'property' !== $p->post_type || 'publish' !== $p->post_status ) {
				return new \WP_Error( 'invalid_property', 'A Property is unavailable.', array( 'status' => 400 ) );
			}$meta = fn( $k )=>get_post_meta( $id, 'rep_' . $k, true );
			$out[] = array(
				'id'        => $id,
				'title'     => wp_strip_all_tags( $p->post_title ),
				'url'       => (string) get_permalink( $id ),
				'price'     => is_numeric( $meta( 'price' ) ) ? (float) $meta( 'price' ) : null,
				'currency'  => (string) $meta( 'currency' ),
				'city'      => (string) $meta( 'city' ),
				'area'      => is_numeric( $meta( 'area' ) ) ? (float) $meta( 'area' ) : null,
				'bedrooms'  => is_numeric( $meta( 'bedrooms' ) ) ? (int) $meta( 'bedrooms' ) : null,
				'bathrooms' => is_numeric( $meta( 'bathrooms' ) ) ? (int) $meta( 'bathrooms' ) : null,
			);
		}return $out;}
}
