<?php
/**
 * Deterministic public/editorial export service.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\ImportExport;

use Mayfair\RealEstatePlatform\Fields\FieldDefinition;
use Mayfair\RealEstatePlatform\Security\Security;

final class ExportService {
	private const EXPORT_CAPABILITY = 'manage_realestate_exports';
	private const ENTITY_PLURALS    = array(
		'property' => 'properties',
		'project'  => 'projects',
		'insight'  => 'insights',
		'agent'    => 'agents',
		'agency'   => 'agencies',
	);
	private const MAX_OUTPUT_BYTES  = 32 * 1024 * 1024;

	private ExportSerializer $serializer;

	public function __construct( private SchemaCatalog $schema, ?ExportSerializer $serializer = null ) {
		$this->serializer = $serializer ?? new ExportSerializer();
	}

	/** @param array<string,mixed> $options
	 * @return string|\WP_Error */
	public function content( string $entity, string $format, int $actor_id, array $options = array() ): string|\WP_Error {
		$settings = $this->options( $entity, $format, $options );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}
		$auth = $this->authorize( $entity, $actor_id, $settings['include_nonpublic'] );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$posts = $this->posts( $entity, $settings['include_nonpublic'], $settings['limit'] );
		$rows  = array();
		foreach ( $posts as $post ) {
			$rows[] = $this->row( $entity, $post );
		}
		return $this->serialize( $entity, $format, $rows );
	}

	/** @param array<string,mixed> $options
	 * @return array<string,mixed>|\WP_Error */
	public function writeFile( string $entity, string $format, string $relative_path, int $actor_id, array $options = array() ): array|\WP_Error {
		$settings = $this->options( $entity, $format, $options );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}
		$auth = $this->authorize( $entity, $actor_id, $settings['include_nonpublic'] );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$base = $this->storageDirectory();
		if ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) {
			return $this->error( 'export_storage_unavailable', 'The export staging directory is unavailable.', 500 );
		}
		$path = Security::safePath( $base, $relative_path );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! $this->extensionMatches( $path, $format ) ) {
			return $this->error( 'export_invalid_path', 'The output file extension must match the export format.', 400 );
		}
		if ( file_exists( $path ) && ! $settings['overwrite'] ) {
			return $this->error( 'export_file_exists', 'The output file exists; pass an explicit overwrite option to replace it.', 409 );
		}
		$parent = dirname( $path );
		if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) {
			return $this->error( 'export_storage_unavailable', 'The export output directory is unavailable.', 500 );
		}
		if ( ! $this->safeDirectory( $base, $parent ) || is_link( $path ) ) {
			return $this->error( 'export_invalid_path', 'The output path is not a safe staging path.', 400 );
		}
		$handle = fopen( $path, 'wb' );
		if ( false === $handle ) {
			return $this->error( 'export_storage_unavailable', 'The export output file could not be opened.', 500 );
		}
		$bytes   = 0;
		$count   = 0;
		$write   = function ( string $chunk ) use ( $handle, &$bytes ): bool {
			if ( $bytes + strlen( $chunk ) > self::MAX_OUTPUT_BYTES ) {
				return false;
			}
			$written = fwrite( $handle, $chunk );
			if ( false === $written ) {
				return false;
			}
			$bytes += $written;
			return strlen( $chunk ) === $written;
		};
		$columns = $this->schema->exportColumns( $entity );
		$ok      = true;
		if ( 'csv' === $format ) {
			$ok = $this->writeCsvRow( $handle, $columns, true, $write );
		} else {
			$ok = $write( '{"entity":' . $this->json( $entity ) . ',"columns":' . $this->json( $columns ) . ',"rows":[' );
		}
		if ( $ok ) {
			$posts = $this->posts( $entity, $settings['include_nonpublic'], $settings['limit'] );
			foreach ( $posts as $post ) {
				$row = $this->row( $entity, $post );
				if ( 'csv' === $format ) {
					$ok = $this->writeCsvRow( $handle, $columns, false, $write, $row );
				} else {
					$ok = $write( ( 0 === $count ? '' : ',' ) . $this->json( $row ) );
				}
				if ( ! $ok ) {
					break;
				}
				++$count;
			}
		}
		if ( $ok && 'json' === $format ) {
			$ok = $write( ']}' );
		}
		fclose( $handle );
		if ( ! $ok ) {
			wp_delete_file( $path );
			return $this->error( 'export_write_failed', 'The export could not be written within the bounded output limit.', 500 );
		}
		$hash = hash_file( 'sha256', $path );
		if ( false === $hash ) {
			return $this->error( 'export_write_failed', 'The export checksum could not be calculated.', 500 );
		}
		return array(
			'path'   => $relative_path,
			'format' => $format,
			'rows'   => $count,
			'bytes'  => $bytes,
			'sha256' => $hash,
		);
	}

	/** @param array<string,mixed> $options
	 * @return array<string,mixed>|\WP_Error */
	private function options( string $entity, string $format, array $options ): array|\WP_Error {
		$entity = strtolower( trim( $entity ) );
		$format = strtolower( trim( $format ) );
		if ( ! in_array( $entity, $this->schema->entities(), true ) || ! in_array( $format, array( 'csv', 'json' ), true ) ) {
			return $this->error( 'invalid_export_options', 'The export entity or format is not supported.', 400 );
		}
		$limit = $options['limit'] ?? 1000;
		if ( is_string( $limit ) && ctype_digit( $limit ) ) {
			$limit = (int) $limit;
		}
		if ( ! is_int( $limit ) || $limit < 1 || $limit > SourceParser::MAX_ROWS ) {
			return $this->error( 'invalid_export_limit', 'The export row limit is outside the bounded range.', 400 );
		}
		$include   = $this->boolean( $options['include_nonpublic'] ?? false );
		$overwrite = $this->boolean( $options['overwrite'] ?? false );
		if ( null === $include || null === $overwrite ) {
			return $this->error( 'invalid_export_options', 'Export boolean options must be true or false.', 400 );
		}
		return array(
			'limit'             => $limit,
			'include_nonpublic' => $include,
			'overwrite'         => $overwrite,
		);
	}

	private function authorize( string $entity, int $actor_id, bool $include_nonpublic = false ): bool|\WP_Error {
		if ( $actor_id < 1 || get_current_user_id() !== $actor_id || ! current_user_can( self::EXPORT_CAPABILITY ) || ! current_user_can( 'edit_' . self::ENTITY_PLURALS[ $entity ] ) || ( $include_nonpublic && ! current_user_can( 'manage_realestate' ) ) ) {
			return $this->error( 'export_forbidden', 'The current user is not allowed to export this entity.', 403 );
		}
		return true;
	}

	/** @return list<\WP_Post> */
	private function posts( string $entity, bool $include_nonpublic, int $limit ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => $entity,
				'post_status'    => $include_nonpublic ? 'any' : 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		return array_values( array_filter( $query->posts, static fn( mixed $post ): bool => $post instanceof \WP_Post ) );
	}

	/** @return array<string,mixed> */
	private function row( string $entity, \WP_Post $post ): array {
		$row = array();
		foreach ( $this->schema->exportColumns( $entity ) as $column ) {
			$row[ $column ] = $this->value( $entity, $post, $column );
		}
		return $row;
	}

	private function value( string $entity, \WP_Post $post, string $column ): mixed {
		return match ( $column ) {
			'id'      => (int) $post->ID,
			'slug'    => (string) $post->post_name,
			'title'   => $this->utf8( (string) $post->post_title ),
			'content' => $this->utf8( (string) $post->post_content ),
			'excerpt' => $this->utf8( (string) $post->post_excerpt ),
			'status'  => (string) $post->post_status,
			default   => $this->fieldValue( $entity, $post, $column ),
		};
	}

	private function fieldValue( string $entity, \WP_Post $post, string $column ): mixed {
		$definition = $this->schema->field( $entity, $column );
		if ( $definition instanceof FieldDefinition ) {
			$value = get_post_meta( $post->ID, 'rep_' . $column, true );
			if ( '' === $value || null === $value ) {
				return null;
			}
			return match ( $definition->type ) {
				'boolean' => (bool) $value,
				'integer' => (int) $value,
				'number', 'latitude', 'longitude' => (float) $value,
				default => $this->utf8( (string) $value ),
			};
		}
		$taxonomy = $this->schema->taxonomyForColumn( $entity, $column );
		if ( null !== $taxonomy ) {
			$ids = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $ids ) ) {
				return array();
			}
			$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
			sort( $ids, SORT_NUMERIC );
			return $ids;
		}
		$media = $this->schema->mediaForColumn( $entity, $column );
		if ( null !== $media ) {
			$value = get_post_meta( $post->ID, 'rep_' . $media['field'], true );
			$ids   = 'attachments' === $media['type'] ? ( is_array( $value ) ? array_values( array_unique( array_map( 'absint', $value ) ) ) : array() ) : ( (int) $value > 0 ? array( (int) $value ) : array() );
			if ( 'url' === $media['kind'] ) {
				$urls = array();
				foreach ( $ids as $id ) {
					$url = wp_get_attachment_url( $id );
					if ( is_string( $url ) && '' !== $url ) {
						$urls[] = $this->utf8( $url );
					}
				}
				return 'attachments' === $media['type'] ? $urls : ( $urls[0] ?? null );
			}
			return 'attachments' === $media['type'] ? $ids : ( $ids[0] ?? null );
		}
		if ( 'featured_image_id' === $column ) {
			$id = (int) get_post_thumbnail_id( $post->ID );
			return $id > 0 ? $id : null;
		}
		if ( 'featured_image_url' === $column ) {
			$id  = (int) get_post_thumbnail_id( $post->ID );
			$url = $id > 0 ? wp_get_attachment_url( $id ) : false;
			return is_string( $url ) && '' !== $url ? $this->utf8( $url ) : null;
		}
		if ( $this->schema->isRelationshipColumn( $entity, $column ) ) {
			$key = 'relationship_agent_id' === $column ? 'agent_id' : 'agency_id';
			$id  = (int) get_post_meta( $post->ID, 'rep_' . $key, true );
			return $id > 0 ? $id : null;
		}
		return null;
	}

	/** @param list<array<string,mixed>> $rows */
	private function serialize( string $entity, string $format, array $rows ): string|\WP_Error {
		$columns = $this->schema->exportColumns( $entity );
		$output  = 'json' === $format ? $this->serializer->json( $entity, $columns, $rows ) : $this->serializer->csv( $columns, $rows );
		if ( is_wp_error( $output ) ) {
			return $output;
		}
		return strlen( $output ) > self::MAX_OUTPUT_BYTES ? $this->error( 'export_write_failed', 'The export exceeds the bounded output limit.', 500 ) : $output;
	}

	/** @param list<string> $columns
	 * @param callable(string):bool $write
	 * @param array<string,mixed> $row */
	private function writeCsvRow( $handle, array $columns, bool $header, callable $write, array $row = array() ): bool {
		unset( $handle );
		$values = $header ? $columns : array_map( fn( string $column ): mixed => $row[ $column ] ?? null, $columns );
		$line   = $this->serializer->line( $values );
		if ( is_wp_error( $line ) ) {
			return false;
		}
		return $write( $line );
	}

	private function json( mixed $value ): string {
		return json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
	}

	private function utf8( string $value ): string {
		return 1 === preg_match( '//u', $value ) ? $value : wp_check_invalid_utf8( $value );
	}

	private function boolean( mixed $value ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return match ( strtolower( trim( $value ) ) ) {
				'true', '1'  => true,
				'false', '0' => false,
				default      => null,
			};
		}
		return null;
	}

	private function extensionMatches( string $path, string $format ): bool {
		return strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) ) === $format;
	}

	private function safeDirectory( string $base, string $directory ): bool {
		$base_real = realpath( $base );
		$dir_real  = realpath( $directory );
		if ( false === $base_real || false === $dir_real ) {
			return false;
		}
		$base_real = rtrim( wp_normalize_path( $base_real ), '/' ) . '/';
		return str_starts_with( wp_normalize_path( $dir_real ) . '/', $base_real );
	}

	private function storageDirectory(): string {
		$uploads = wp_upload_dir();
		return trailingslashit( (string) ( $uploads['basedir'] ?? WP_CONTENT_DIR . '/uploads' ) ) . 'realestate-platform-imports';
	}

	private function error( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
