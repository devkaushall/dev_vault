<?php
/**
 * Blogs, articles and market insights: storage and retrieval.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Data;

use EstatOS\Support\Format;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * One store for the three kinds of writing an office publishes. They share a
 * post type and are separated by the "kind" taxonomy, so a piece can be
 * re-filed without losing its address on the website.
 */
final class Insights {

	/**
	 * Save a piece of writing.
	 *
	 * @param array<string,mixed> $input Raw input from the editor screen.
	 * @return int|WP_Error Post ID on success.
	 */
	public static function save( array $input ) {
		$id    = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		$title = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';

		if ( '' === trim( $title ) ) {
			return new WP_Error( 'estat_insight_title', __( 'Please give this a title before saving.', 'estat-os' ) );
		}

		$status = isset( $input['status'] ) && 'publish' === $input['status'] ? 'publish' : 'draft';

		$postarr = array(
			'post_type'    => PostTypes::INSIGHT,
			'post_title'   => $title,
			'post_status'  => $status,
			'post_excerpt' => isset( $input['excerpt'] ) ? sanitize_textarea_field( (string) $input['excerpt'] ) : '',
			'post_content' => isset( $input['content'] ) ? wp_kses_post( (string) $input['content'] ) : '',
		);

		if ( $id > 0 ) {
			$postarr['ID'] = $id;
			$result        = wp_update_post( $postarr, true );
		} else {
			$result = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$id = (int) $result;

		// Kind: exactly one, always present.
		$kind  = isset( $input['kind'] ) ? sanitize_key( (string) $input['kind'] ) : 'blog';
		$kinds = Taxonomies::insight_kinds();
		if ( ! isset( $kinds[ $kind ] ) ) {
			$kind = 'blog';
		}
		wp_set_object_terms( $id, $kind, Taxonomies::INSIGHT_KIND, false );

		// Topics: free text, comma separated, created as needed.
		if ( isset( $input['topics'] ) ) {
			$topics = is_array( $input['topics'] ) ? $input['topics'] : explode( ',', (string) $input['topics'] );
			$topics = array_values(
				array_filter(
					array_map(
						static function ( $topic ) {
							return sanitize_text_field( trim( (string) $topic ) );
						},
						$topics
					),
					static function ( string $topic ): bool {
						return '' !== $topic;
					}
				)
			);
			wp_set_object_terms( $id, $topics, Taxonomies::INSIGHT_TOPIC, false );
		}

		if ( isset( $input['cover_id'] ) ) {
			$cover = absint( $input['cover_id'] );
			if ( $cover > 0 ) {
				set_post_thumbnail( $id, $cover );
			} else {
				delete_post_thumbnail( $id );
			}
		}

		return $id;
	}

	/**
	 * Look up pieces of writing.
	 *
	 * @param array<string,mixed> $args kind, search, paged, per_page, status.
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int,page:int}
	 */
	public static function query( array $args = array() ): array {
		$kind     = isset( $args['kind'] ) ? sanitize_key( (string) $args['kind'] ) : '';
		$search   = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';
		$paged    = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 20;
		$per_page = max( 1, min( 100, $per_page ) );
		$status   = isset( $args['status'] ) ? $args['status'] : array( 'publish', 'draft', 'pending' );

		$query_args = array(
			'post_type'      => PostTypes::INSIGHT,
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		if ( '' !== $kind ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Taxonomies::INSIGHT_KIND,
					'field'    => 'slug',
					'terms'    => $kind,
				),
			);
		}

		$query = new \WP_Query( $query_args );
		$items = array();

		foreach ( (array) $query->posts as $post ) {
			$items[] = self::to_array( $post );
		}

		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
			'page'  => $paged,
		);
	}

	/**
	 * How many pieces exist of one kind.
	 *
	 * @param string $kind Kind slug.
	 * @return int
	 */
	public static function count( string $kind ): int {
		$term = get_term_by( 'slug', sanitize_key( $kind ), Taxonomies::INSIGHT_KIND );

		if ( $term && isset( $term->count ) ) {
			return (int) $term->count;
		}

		$result = self::query(
			array(
				'kind'     => $kind,
				'per_page' => 1,
			)
		);

		return (int) $result['total'];
	}

	/**
	 * Shape one piece for display or for the API.
	 *
	 * @param \WP_Post|int $post Post or ID.
	 * @return array<string,mixed>
	 */
	public static function to_array( $post ): array {
		$post = get_post( $post );

		if ( ! $post ) {
			return array();
		}

		$id = (int) $post->ID;

		$kinds = wp_get_object_terms( $id, Taxonomies::INSIGHT_KIND, array( 'fields' => 'slugs' ) );
		$kinds = is_array( $kinds ) ? $kinds : array();

		$topics = wp_get_object_terms( $id, Taxonomies::INSIGHT_TOPIC, array( 'fields' => 'names' ) );
		$topics = is_array( $topics ) ? $topics : array();

		return array(
			'id'      => $id,
			'title'   => get_the_title( $post ),
			'excerpt' => (string) $post->post_excerpt,
			'status'  => (string) $post->post_status,
			'kind'    => $kinds ? (string) $kinds[0] : 'blog',
			'topics'  => array_values( $topics ),
			'date'    => Format::date( $post->post_date ),
			'link'    => get_permalink( $post ),
			'cover'   => get_the_post_thumbnail_url( $id, 'medium' ) ?: '',
		);
	}
}
