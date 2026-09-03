<?php
/** Public Elementor context resolver backed by canonical REP data. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Elementor;

use Mayfair\RealEstatePlatform\Fields\FieldRegistry;
use Mayfair\RealEstatePlatform\Geo\CoordinatePrivacy;
use Mayfair\RealEstatePlatform\Profiles\ProfileService;

final class PublicContext {
	private const ENTITIES   = array( 'property', 'project', 'agent', 'agency', 'insight' );
	private const TAXONOMIES = array( 'property_type', 'property_status', 'location', 'project_type', 'insight_topic' );

	public function __construct( private FieldRegistry $fields, private ProfileService $profiles, private CoordinatePrivacy $coordinates ) {}

	public function resolve( string $entity, string $field ): mixed {
		$post = $this->currentPost( $entity );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		if ( in_array( $entity, array( 'agent', 'agency' ), true ) ) {
			return $this->profileValue( $post, $field );
		}
		if ( 'title' === $field ) {
			return wp_strip_all_tags( $post->post_title );
		}
		if ( 'status' === $field ) {
			return 'publish' === $post->post_status ? __( 'Published', 'realestate-platform' ) : null;
		}
		if ( 'featured_image' === $field ) {
			$image_id = (int) get_post_thumbnail_id( $post->ID );
			return $image_id > 0 ? $this->attachmentUrl( $image_id ) : null;
		}
		if ( in_array( $field, self::TAXONOMIES, true ) ) {
			return $this->taxonomyValue( $post->ID, $field );
		}
		if ( in_array( $field, array( 'agent', 'agency' ), true ) && 'property' === $entity ) {
			return $this->relatedProfileTitle( $post->ID, $field );
		}
		$definitions = $this->fields->all();
		if ( ! isset( $definitions[ $field ] ) ) {
			return null;
		}
		$definition = $definitions[ $field ];
		if ( ! in_array( $entity, $definition->entities, true ) || ! $definition->elementor_exposed || ! $definition->frontend_visible ) {
			return null;
		}
		$raw = get_post_meta( $post->ID, 'rep_' . $definition->key, true );
		if ( null === $raw || '' === $raw || array() === $raw ) {
			return null;
		}
		if ( in_array( $field, array( 'latitude', 'longitude' ), true ) ) {
			return $this->coordinateValue( $post->ID, $field );
		}
		$clean = $definition->sanitize( $raw );
		if ( ! $definition->validate( $clean ) ) {
			return null;
		}
		if ( 'attachment' === $definition->type ) {
			return $this->attachmentUrl( (int) $clean );
		}
		if ( 'attachments' === $definition->type ) {
			$urls = array();
			foreach ( is_array( $clean ) ? $clean : array() as $attachment_id ) {
				$url = $this->attachmentUrl( (int) $attachment_id );
				if ( null !== $url ) {
					$urls[] = $url;
				}
			}
			return $urls;
		}
		return $clean;
	}

	public function currentPost( string $entity ): ?\WP_Post {
		if ( ! in_array( $entity, self::ENTITIES, true ) ) {
			return null;
		}
		$id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		if ( $id < 1 && function_exists( 'get_queried_object_id' ) ) {
			$id = (int) get_queried_object_id();
		}
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof \WP_Post || $entity !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		return $post;
	}

	private function profileValue( \WP_Post $post, string $field ): mixed {
		if ( 'featured_image' === $field || 'avatar' === $field ) {
			$image_id = (int) get_post_thumbnail_id( $post->ID );
			return $image_id > 0 ? $this->attachmentUrl( $image_id ) : null;
		}
		$data = $this->profiles->get( $post->post_type, $post->ID, 0, false );
		if ( is_wp_error( $data ) || ! is_array( $data ) ) {
			return null;
		}
		if ( 'agency' === $field && 'agent' === $post->post_type ) {
			return $this->relatedProfileTitle( $post->ID, 'agency' );
		}
		if ( 'title' === $field ) {
			return $data['title'] ?? null;
		}
		$definitions = $this->fields->all();
		if ( ! isset( $definitions[ $field ] ) || ! in_array( $post->post_type, $definitions[ $field ]->entities, true ) || ! $definitions[ $field ]->elementor_exposed || ! $definitions[ $field ]->frontend_visible ) {
			return null;
		}
		return $data[ $field ] ?? null;
	}

	private function relatedProfileTitle( int $post_id, string $relation ): ?string {
		$related_id = (int) get_post_meta( $post_id, 'rep_' . ( 'agent' === $relation ? 'agent_id' : 'agency_id' ), true );
		if ( $related_id < 1 ) {
			return null;
		}
		$related = get_post( $related_id );
		if ( ! $related instanceof \WP_Post || $relation !== $related->post_type || 'publish' !== $related->post_status ) {
			return null;
		}
		return wp_strip_all_tags( $related->post_title );
	}

	private function taxonomyValue( int $post_id, string $taxonomy ): ?string {
		$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return null;
		}
		$names = array_filter( array_map( static fn( mixed $name ): string => wp_strip_all_tags( (string) $name ), $terms ) );
		return empty( $names ) ? null : implode( ', ', $names );
	}

	private function coordinateValue( int $post_id, string $field ): ?float {
		$latitude  = get_post_meta( $post_id, 'rep_latitude', true );
		$longitude = get_post_meta( $post_id, 'rep_longitude', true );
		if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
			return null;
		}
		$mode   = sanitize_key( (string) get_post_meta( $post_id, 'rep_coordinate_privacy', true ) );
		$values = $this->coordinates->expose( (float) $latitude, (float) $longitude, '' === $mode ? 'exact' : $mode );
		return null !== $values ? (float) $values[ $field ] : null;
	}

	private function attachmentUrl( int $attachment_id ): ?string {
		if ( $attachment_id < 1 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return null;
		}
		$url = wp_get_attachment_url( $attachment_id );
		return is_string( $url ) && '' !== $url ? $url : null;
	}
}
