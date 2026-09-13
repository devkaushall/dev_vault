<?php
/**
 * Photos, brochures and documents belonging to the office.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Data;

use EstatOS\Support\Format;

defined( 'ABSPATH' ) || exit;

/**
 * A thin, safe view over the WordPress media library, limited to the files an
 * estate office actually deals with, and aware of which property or project a
 * file belongs to. Nothing here uploads or deletes by itself; it reads, and
 * the admin screen performs writes through capability-checked actions.
 */
final class Media {

	/**
	 * Meta key linking an attachment to a property or project.
	 */
	public const LINK_KEY = '_estat_attached_to';

	/**
	 * Meta key marking what an office file is for.
	 */
	public const KIND_KEY = '_estat_file_kind';

	/**
	 * Kinds of file an office keeps, in plain language.
	 *
	 * @return array<string,string>
	 */
	public static function kinds(): array {
		return array(
			'photo'     => __( 'Photos', 'estat-os' ),
			'floorplan' => __( 'Floor plans', 'estat-os' ),
			'brochure'  => __( 'Brochures', 'estat-os' ),
			'document'  => __( 'Documents', 'estat-os' ),
		);
	}

	/**
	 * Which MIME types belong to each kind.
	 *
	 * @param string $kind Kind slug.
	 * @return string[]
	 */
	public static function mimes_for( string $kind ): array {
		$images = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif' );
		$docs   = array(
			'application/pdf',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		);

		switch ( $kind ) {
			case 'photo':
			case 'floorplan':
				return $images;
			case 'brochure':
			case 'document':
				return $docs;
			default:
				return array_merge( $images, $docs );
		}
	}

	/**
	 * Find files.
	 *
	 * @param array<string,mixed> $args kind, search, attached_to, unattached, paged, per_page.
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int,page:int}
	 */
	public static function query( array $args = array() ): array {
		$kind        = isset( $args['kind'] ) ? sanitize_key( (string) $args['kind'] ) : '';
		$search      = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';
		$attached_to = isset( $args['attached_to'] ) ? absint( $args['attached_to'] ) : 0;
		$unattached  = ! empty( $args['unattached'] );
		$paged       = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$per_page    = isset( $args['per_page'] ) ? (int) $args['per_page'] : 40;
		$per_page    = max( 1, min( 100, $per_page ) );

		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$mimes = self::mimes_for( $kind );
		if ( $mimes ) {
			$query_args['post_mime_type'] = $mimes;
		}

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		$meta_query = array();

		if ( $attached_to > 0 ) {
			$meta_query[] = array(
				'key'   => self::LINK_KEY,
				'value' => (string) $attached_to,
			);
		}

		if ( $unattached ) {
			$meta_query[] = array(
				'key'     => self::LINK_KEY,
				'compare' => 'NOT EXISTS',
			);
		}

		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
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
	 * Describe one file for display.
	 *
	 * @param \WP_Post|int $post Attachment or ID.
	 * @return array<string,mixed>
	 */
	public static function to_array( $post ): array {
		$post = get_post( $post );

		if ( ! $post ) {
			return array();
		}

		$id   = (int) $post->ID;
		$mime = (string) $post->post_mime_type;

		$linked_to = (int) get_post_meta( $id, self::LINK_KEY, true );
		$linked    = $linked_to > 0 ? get_post( $linked_to ) : null;

		return array(
			'id'          => $id,
			'title'       => get_the_title( $post ),
			'mime'        => $mime,
			'is_image'    => 0 === strpos( $mime, 'image/' ),
			'kind'        => self::kind_of( $id, $mime ),
			'thumb'       => (string) ( wp_get_attachment_image_url( $id, 'thumbnail' ) ?: '' ),
			'medium'      => (string) ( wp_get_attachment_image_url( $id, 'medium' ) ?: '' ),
			'url'         => (string) ( wp_get_attachment_url( $id ) ?: '' ),
			'alt'         => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'filesize'    => self::readable_size( $id ),
			'date'        => Format::date( $post->post_date ),
			'linked_id'   => $linked_to,
			'linked_name' => $linked ? get_the_title( $linked ) : '',
		);
	}

	/**
	 * What kind of office file this is.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $mime MIME type.
	 * @return string
	 */
	public static function kind_of( int $id, string $mime = '' ): string {
		$stored = (string) get_post_meta( $id, self::KIND_KEY, true );
		$kinds  = self::kinds();

		if ( '' !== $stored && isset( $kinds[ $stored ] ) ) {
			return $stored;
		}

		if ( '' === $mime ) {
			$mime = (string) get_post_mime_type( $id );
		}

		return 0 === strpos( $mime, 'image/' ) ? 'photo' : 'document';
	}

	/**
	 * Record what a file is for.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $kind Kind slug.
	 * @return bool
	 */
	public static function set_kind( int $id, string $kind ): bool {
		$kind = sanitize_key( $kind );

		if ( ! isset( self::kinds()[ $kind ] ) ) {
			return false;
		}

		if ( 'attachment' !== get_post_type( $id ) ) {
			return false;
		}

		update_post_meta( $id, self::KIND_KEY, $kind );

		return true;
	}

	/**
	 * Attach a file to a property or project, or detach it with 0.
	 *
	 * @param int $id        Attachment ID.
	 * @param int $record_id Property or project ID, or 0 to detach.
	 * @return bool
	 */
	public static function link( int $id, int $record_id ): bool {
		if ( 'attachment' !== get_post_type( $id ) ) {
			return false;
		}

		if ( $record_id <= 0 ) {
			delete_post_meta( $id, self::LINK_KEY );
			return true;
		}

		$record = get_post( $record_id );
		$owned  = array( PostTypes::LISTING, PostTypes::PROJECT );

		if ( ! $record || ! in_array( $record->post_type, $owned, true ) ) {
			return false;
		}

		update_post_meta( $id, self::LINK_KEY, $record_id );

		return true;
	}

	/**
	 * How many files of each kind exist.
	 *
	 * @return array<string,int>
	 */
	public static function counts(): array {
		$out = array();

		foreach ( array_keys( self::kinds() ) as $kind ) {
			$result       = self::query(
				array(
					'kind'     => $kind,
					'per_page' => 1,
				)
			);
			$out[ $kind ] = (int) $result['total'];
		}

		return $out;
	}

	/**
	 * File size in words a person can read.
	 *
	 * @param int $id Attachment ID.
	 * @return string
	 */
	private static function readable_size( int $id ): string {
		$path = get_attached_file( $id );

		if ( ! $path || ! file_exists( $path ) ) {
			return '';
		}

		return size_format( (int) filesize( $path ) );
	}
}
