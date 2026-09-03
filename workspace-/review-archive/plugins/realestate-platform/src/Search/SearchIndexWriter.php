<?php
/** Search projection writer. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Search;

use Mayfair\RealEstatePlatform\Fields\FieldRegistry;

final class SearchIndexWriter {
	public const TAXONOMIES = SearchCriteria::TAXONOMIES;
	public function __construct( private FieldRegistry $fields ) {}

	public function synchronize( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'property' !== $post->post_type || 'publish' !== $post->post_status ) {
			$this->remove( $post_id );
			return false;
		}
		global $wpdb;
		$meta = static fn( string $key ) => get_post_meta( $post_id, 'rep_' . $key, true );
		$text = array( $post->post_title, $post->post_excerpt, $post->post_content );
		foreach ( $this->fields->forEntity( 'property' ) as $field ) {
			if ( $field->searchable ) {
				$text[] = (string) $meta( $field->key );
			}
		}
		foreach ( wp_get_object_terms( $post_id, self::TAXONOMIES ) as $term ) {
			if ( $term instanceof \WP_Term ) {
				$text[] = $term->name;
			}
		}
		$data = array(
			'post_id'           => $post_id,
			'post_modified_gmt' => $post->post_modified_gmt,
			'title'             => $post->post_title,
			'slug'              => $post->post_name,
			'keyword_text'      => implode( ' ', array_filter( $text ) ),
			'indexed_at'        => current_time( 'mysql', true ),
		);
		foreach ( array( 'reference', 'country', 'state', 'city', 'locality', 'neighborhood', 'postal_code', 'currency', 'developer', 'rera', 'furnishing', 'possession', 'availability', 'construction_status', 'price', 'area', 'plot_area', 'bedrooms', 'bathrooms', 'floors', 'floor', 'parking', 'latitude', 'longitude', 'project_id', 'featured', 'verified' ) as $key ) {
			$value        = $meta( $key );
			$data[ $key ] = '' === $value ? null : $value;
		}
		$data['featured'] = (int) (bool) $data['featured'];
		$data['verified'] = (int) (bool) $data['verified'];
		$result           = $wpdb->replace( $wpdb->prefix . 'rep_search_properties', $data );
		if ( false === $result ) {
			return false; }
		$wpdb->delete( $wpdb->prefix . 'rep_search_terms', array( 'post_id' => $post_id ), array( '%d' ) );
		foreach ( self::TAXONOMIES as $taxonomy ) {
			foreach ( wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) ) as $term_id ) {
				$wpdb->insert(
					$wpdb->prefix . 'rep_search_terms',
					array(
						'post_id'  => $post_id,
						'taxonomy' => $taxonomy,
						'term_id'  => (int) $term_id,
					),
					array( '%d', '%s', '%d' )
				);
			}
		}
		do_action( 'realestate_platform_search_index_changed', $post_id );
		return true;
	}
	public function remove( int $post_id ): void {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'rep_search_terms', array( 'post_id' => $post_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'rep_search_properties', array( 'post_id' => $post_id ), array( '%d' ) );
	}
}
