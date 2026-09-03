<?php
/**
 * Canonical, deterministic import planner and executor.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\ImportExport;

use Mayfair\RealEstatePlatform\Content\ContentRegistrar;
use Mayfair\RealEstatePlatform\Fields\FieldDefinition;
use Mayfair\RealEstatePlatform\Media\MediaService;
use Mayfair\RealEstatePlatform\Profiles\ProfileService;
use Mayfair\RealEstatePlatform\Security\Security;
use Mayfair\RealEstatePlatform\Security\StrictId;

final class ImportService {
	private const IMPORT_CAPABILITY = 'manage_realestate_imports';
	private const ENTITY_PLURALS    = array(
		'property' => 'properties',
		'project'  => 'projects',
		'insight'  => 'insights',
		'agent'    => 'agents',
		'agency'   => 'agencies',
	);

	public function __construct(
		private SourceParser $parser,
		private SchemaCatalog $schema,
		private ProfileService $profiles,
		private MediaService $media,
		private RemoteMediaImporter $remote_media
	) {}

	/**
	 * Validate, plan, or import bounded source content.
	 *
	 * @param array<string,mixed> $options
	 * @return array<string,mixed>|\WP_Error
	 */
	public function runContent( string $mode, string $entity, string $format, string $contents, array $options, int $actor_id ): array|\WP_Error {
		$settings = $this->options( $mode, $entity, $format, $options );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}
		$auth = $this->authorize( $entity, $actor_id );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$parsed = $this->parser->parseString( $contents, $format );
		return $this->processParsed( $parsed, $settings, $actor_id );
	}

	/**
	 * Read a relative file below the plugin's upload staging directory.
	 *
	 * @param array<string,mixed> $options
	 * @return array<string,mixed>|\WP_Error
	 */
	public function runFile( string $mode, string $entity, string $format, string $relative_path, array $options, int $actor_id ): array|\WP_Error {
		$settings = $this->options( $mode, $entity, $format, $options );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}
		$auth = $this->authorize( $entity, $actor_id );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$path = Security::safePath( $this->storageDirectory(), $relative_path );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! $this->safeExistingPath( $this->storageDirectory(), $path ) ) {
			return $this->error( 'invalid_path', 'The import path is not a safe staging file.', 400 );
		}
		$parsed = $this->parser->parseFile( $path, $format );
		return $this->processParsed( $parsed, $settings, $actor_id );
	}

	/** @return array<string,mixed>|\WP_Error */
	private function processParsed( array|\WP_Error $parsed, array $settings, int $actor_id ): array|\WP_Error {
		$report = new ImportReport( $settings['entity'], $settings['mode'], $settings['strategy'], $settings['format'] );
		if ( is_wp_error( $parsed ) ) {
			$report->fatal( $parsed->get_error_code() . ': ' . $parsed->get_error_message() );
			return $report->toArray();
		}
		$report->warning( $parsed['warnings'] );
		$preflight_error = false;
		if ( array() !== $parsed['declared_columns'] ) {
			foreach ( $parsed['declared_columns'] as $column ) {
				if ( ! $this->schema->allowsColumn( $settings['entity'], $column ) ) {
					$report->fatal( 'field: column is not allowlisted for ' . $settings['entity'] . ': ' . $column );
					$preflight_error = true;
				}
			}
		}
		if ( array() === $parsed['rows'] ) {
			$report->fatal( 'import_empty_source: the source contains no data rows.' );
			return $report->toArray();
		}
		$plan = array();
		foreach ( $parsed['rows'] as $source_row ) {
			$row = $this->normalizeRow( $settings['entity'], $source_row['data'], $source_row['line'], $settings );
			if ( ! $row->valid() ) {
				$report->row( $row->line, 'invalid', array(), $row->errors, $row->warnings );
				$preflight_error = true;
				continue;
			}
			$planned = $this->planRow( $row, $settings, $actor_id );
			if ( is_wp_error( $planned ) ) {
				$code   = $planned->get_error_code();
				$status = str_starts_with( (string) $code, 'conflict' ) ? 'conflict' : 'invalid';
				$report->row( $row->line, $status, array( 'identity' => $this->identityLabel( $row->normalized ) ), array( $this->issue( $code, $planned->get_error_message() ) ), $row->warnings );
				$preflight_error = true;
				continue;
			}
			$plan[] = $planned;
			if ( 'import' !== $settings['mode'] ) {
				$report->row(
					$row->line,
					$planned['decision'],
					array(
						'decision' => $planned['decision'],
						'id'       => $planned['id'],
						'identity' => $planned['identity'],
					),
					array(),
					$planned['warnings']
				);
			}
		}
		if ( 'import' === $settings['mode'] ) {
			if ( $preflight_error ) {
				foreach ( $plan as $planned ) {
					$report->row(
						$planned['row']->line,
						'skipped',
						array(
							'decision' => $planned['decision'],
							'id'       => $planned['id'],
							'identity' => $planned['identity'],
						),
						array( 'preflight: no mutations were applied because the complete plan contains errors.' ),
						$planned['warnings']
					);
				}
			} else {
				foreach ( $plan as $planned ) {
					$result = $this->apply( $planned, $settings, $actor_id );
					if ( is_wp_error( $result ) ) {
						$report->row(
							$planned['row']->line,
							'failed',
							array(
								'decision' => $planned['decision'],
								'id'       => $planned['id'],
								'identity' => $planned['identity'],
							),
							array( 'execution: ' . $result->get_error_message() ),
							$planned['warnings']
						);
						continue;
					}
					$report->row(
						$planned['row']->line,
						'imported',
						array(
							'decision' => $planned['decision'],
							'id'       => (int) $result,
							'identity' => $planned['identity'],
						),
						array(),
						$planned['warnings']
					);
				}
			}
		}
		$report->finalize();
		return $report->toArray();
	}

	/** @param array<string,mixed> $options
	 * @return array<string,mixed>|\WP_Error */
	private function options( string $mode, string $entity, string $format, array $options ): array|\WP_Error {
		$mode     = strtolower( trim( $mode ) );
		$entity   = strtolower( trim( $entity ) );
		$format   = strtolower( trim( $format ) );
		$strategy = strtolower( trim( (string) ( $options['strategy'] ?? 'upsert' ) ) );
		if ( ! in_array( $mode, array( 'validate', 'dry_run', 'import' ), true ) || ! in_array( $entity, $this->schema->entities(), true ) || ! in_array( $format, array( 'csv', 'json' ), true ) ) {
			return $this->error( 'invalid_import_options', 'The import mode, entity, or format is not supported.', 400 );
		}
		if ( ! in_array( $strategy, array( 'upsert', 'create_only', 'update_only' ), true ) ) {
			return $this->error( 'invalid_import_strategy', 'The import strategy is not supported.', 400 );
		}
		$create_terms = $this->booleanOption( $options['create_missing_terms'] ?? false );
		$remote_media = $this->booleanOption( $options['allow_remote_media'] ?? false );
		if ( null === $create_terms || null === $remote_media ) {
			return $this->error( 'invalid_import_options', 'Boolean import options must be true or false.', 400 );
		}
		return array(
			'mode'                 => $mode,
			'entity'               => $entity,
			'format'               => $format,
			'strategy'             => $strategy,
			'create_missing_terms' => $create_terms,
			'allow_remote_media'   => $remote_media,
		);
	}

	private function authorize( string $entity, int $actor_id ): bool|\WP_Error {
		if ( $actor_id < 1 || get_current_user_id() !== $actor_id || ! current_user_can( self::IMPORT_CAPABILITY ) || ! current_user_can( 'edit_' . self::ENTITY_PLURALS[ $entity ] ) ) {
			return $this->error( 'import_forbidden', 'The current user is not allowed to import this entity.', 403 );
		}
		return true;
	}

	/** @param array<string,mixed> $settings */
	private function normalizeRow( string $entity, array $raw, int $line, array $settings ): ImportRow {
		$errors   = array();
		$warnings = array();
		foreach ( $raw as $column => $value ) {
			if ( ! is_string( $column ) || ! $this->schema->allowsColumn( $entity, $column ) ) {
				$errors[] = 'field: column is not allowlisted: ' . (string) $column;
			}
		}
		$post       = array();
		$fields     = array();
		$term_input = array();
		$provided   = array(
			'post'          => array(),
			'terms'         => array(),
			'media'         => array(),
			'relationships' => array(),
		);
		$id         = null;
		if ( array_key_exists( 'id', $raw ) && ! $this->emptyInput( $raw['id'], $settings['format'] ) ) {
			$value = $this->sourceValue( $raw['id'], $settings['format'] );
			if ( ! is_int( $value ) && ! ( is_string( $value ) && ctype_digit( $value ) ) ) {
				$errors[] = 'identity: id must be a positive integer.';
			} else {
				$id = StrictId::parse( $value );
				if ( 0 === $id ) {
					$errors[] = 'identity: id must be a positive integer.';
				}
			}
			$provided['post'][] = 'id';
		}
		$slug = null;
		if ( array_key_exists( 'slug', $raw ) && ! $this->emptyInput( $raw['slug'], $settings['format'] ) ) {
			$value = $this->sourceValue( $raw['slug'], $settings['format'] );
			if ( ! is_string( $value ) ) {
				$errors[] = 'identity: slug must be a string.';
			} else {
				$slug = sanitize_title( $value );
				if ( '' === $slug ) {
					$errors[] = 'identity: slug is empty after sanitization.';
				}
				if ( strlen( $slug ) > 200 ) {
					$errors[] = 'identity: slug is too long.';
				}
			}
			$provided['post'][] = 'slug';
		}
		foreach ( array( 'title', 'content', 'excerpt' ) as $key ) {
			if ( ! array_key_exists( $key, $raw ) ) {
				continue;
			}
			$value = $this->sourceValue( $raw[ $key ], $settings['format'] );
			if ( ! is_string( $value ) ) {
				$errors[] = 'field: ' . $key . ' must be a string.';
				continue;
			}
			$limit = 'title' === $key ? 200 : 65535;
			if ( strlen( $value ) > $limit ) {
				$errors[] = 'field: ' . $key . ' exceeds its length limit.';
			}
			if ( 'title' === $key ) {
				$value = sanitize_text_field( $value );
				if ( '' === trim( $value ) ) {
					$errors[] = 'field: title is required.';
				}
			} else {
				$value = wp_kses_post( $value );
			}
			$post[ $key ]       = $value;
			$provided['post'][] = $key;
		}
		if ( ! array_key_exists( 'title', $post ) || '' === trim( (string) ( $post['title'] ?? '' ) ) ) {
			$errors[] = 'field: title is required.';
		}
		if ( array_key_exists( 'status', $raw ) && ! $this->emptyInput( $raw['status'], $settings['format'] ) ) {
			$value = $this->sourceValue( $raw['status'], $settings['format'] );
			if ( ! is_string( $value ) ) {
				$errors[] = 'field: status must be draft or publish.';
			} else {
				$status = strtolower( trim( $value ) );
				if ( ! in_array( $status, array( 'draft', 'publish' ), true ) ) {
					$errors[] = 'field: status must be draft or publish.';
				} else {
					$post['status'] = $status;
				}
			}
			$provided['post'][] = 'status';
		}
		if ( ! isset( $post['status'] ) ) {
			$post['status'] = 'draft';
		}
		foreach ( $this->schema->publicFields( $entity ) as $definition ) {
			if ( in_array( $definition->type, array( 'attachment', 'attachments' ), true ) || ! array_key_exists( $definition->key, $raw ) ) {
				continue;
			}
			$value = $this->normalizeField( $definition, $raw[ $definition->key ], $settings['format'] );
			if ( is_wp_error( $value ) ) {
				$errors[] = 'field: ' . $definition->key . ' ' . $value->get_error_message();
				continue;
			}
			$fields[ $definition->key ] = $value;
		}
		foreach ( $this->schema->taxonomyNames( $entity ) as $taxonomy ) {
			$column = 'tax_' . $taxonomy;
			if ( ! array_key_exists( $column, $raw ) ) {
				continue;
			}
			$terms = $this->normalizeTerms( $raw[ $column ], $settings['format'] );
			if ( is_wp_error( $terms ) ) {
				$errors[] = 'taxonomy: ' . $taxonomy . ' ' . $terms->get_error_message();
				continue;
			}
			$provided['terms'][ $taxonomy ] = true;
			$term_input[ $taxonomy ]        = $terms;
		}
		$relationships = array();
		foreach ( $this->schema->relationshipColumns( $entity ) as $column ) {
			if ( ! array_key_exists( $column, $raw ) ) {
				continue;
			}
			$value = $this->sourceValue( $raw[ $column ], $settings['format'] );
			if ( $this->emptyInput( $value, $settings['format'] ) ) {
				continue;
			}
			if ( ! is_int( $value ) && ! ( is_string( $value ) && ctype_digit( $value ) ) ) {
				$errors[] = 'relationship: ' . $column . ' must be a positive integer.';
				continue;
			}
			$id_value = StrictId::parse( $value );
			if ( 0 === $id_value ) {
				$errors[] = 'relationship: ' . $column . ' must be a positive integer.';
				continue;
			}
			$relationships[ $column ]             = $id_value;
			$provided['relationships'][ $column ] = true;
		}
		$media      = $this->normalizeMedia( $entity, $raw, $settings, $errors );
		$identities = array();
		if ( null !== $id && $id > 0 ) {
			$identities[] = 'id';
		}
		if ( null !== $slug && '' !== $slug ) {
			$identities[] = 'slug';
		}
		if ( isset( $fields['reference'] ) && is_string( $fields['reference'] ) && '' !== $fields['reference'] ) {
			$identities[] = 'reference';
		}
		if ( array() === $identities ) {
			$errors[] = 'identity: one of id, slug, or the canonical reference is required for deterministic reruns.';
		}
		if ( array_key_exists( 'status', $provided['post'] ) && 'publish' === $post['status'] && ! current_user_can( 'publish_' . self::ENTITY_PLURALS[ $entity ] ) ) {
			$errors[] = 'field: the current user cannot publish this entity.';
		}
		$normalized = array(
			'id'            => $id,
			'slug'          => $slug,
			'post'          => $post,
			'fields'        => $fields,
			'provided'      => $provided,
			'term_input'    => $term_input,
			'relationships' => $relationships,
			'media'         => $media,
			'identity'      => $identities,
		);
		return new ImportRow( $line, $raw, $normalized, array_values( array_unique( $errors ) ), array_values( array_unique( $warnings ) ) );
	}
	/** @param array<string,mixed> $settings */
	private function planRow( ImportRow $row, array $settings, int $actor_id ): array|\WP_Error {
		$normalized = $row->normalized;
		$identity   = $this->resolveIdentity( $settings['entity'], $normalized );
		if ( is_wp_error( $identity ) ) {
			return $identity;
		}
		$existing = (int) $identity['id'];
		$decision = $existing > 0 ? 'update' : 'create';
		if ( $existing > 0 && ! current_user_can( 'edit_post', $existing ) ) {
			return $this->error( 'import_record_forbidden', 'The current user cannot update the resolved record.', 403 );
		}
		if ( 'create_only' === $settings['strategy'] && 'update' === $decision ) {
			return $this->error( 'conflict_existing_record', 'A deterministic identity already belongs to an existing record.', 409 );
		}
		if ( 'update_only' === $settings['strategy'] && 'create' === $decision ) {
			return $this->error( 'conflict_missing_record', 'The update-only strategy could not find the deterministic identity.', 409 );
		}
		$terms = $this->resolveTerms( $settings['entity'], $normalized['term_input'] ?? array(), $settings['create_missing_terms'] );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		$normalized['terms']    = $terms['terms'];
		$normalized['warnings'] = $terms['warnings'];
		$relationship_error     = $this->validateRelationships( $settings['entity'], $normalized['relationships'], $actor_id );
		if ( is_wp_error( $relationship_error ) ) {
			return $relationship_error;
		}
		return array(
			'row'      => new ImportRow( $row->line, $row->raw, $normalized, $row->errors, $row->warnings ),
			'decision' => $decision,
			'id'       => $existing,
			'identity' => $this->identityLabel( $normalized ),
			'warnings' => array_values( array_unique( array_merge( $row->warnings, $terms['warnings'] ) ) ),
		);
	}

	/** @param array<string,mixed> $normalized
	 * @return array{ id:int }|\WP_Error */
	private function resolveIdentity( string $entity, array $normalized ): array|\WP_Error {
		$matches = array();
		if ( isset( $normalized['id'] ) && (int) $normalized['id'] > 0 ) {
			$post = get_post( (int) $normalized['id'] );
			if ( ! $post instanceof \WP_Post ) {
				return $this->error( 'conflict_requested_id_missing', 'The explicitly requested ID does not exist.', 409 );
			}
			if ( $entity !== $post->post_type ) {
				return $this->error( 'conflict_requested_id_type', 'The explicitly requested ID belongs to a different entity.', 409 );
			}
			$matches[] = (int) $post->ID;
		}
		if ( isset( $normalized['slug'] ) && is_string( $normalized['slug'] ) && '' !== $normalized['slug'] ) {
			$post = get_page_by_path( $normalized['slug'], OBJECT, $entity );
			if ( $post instanceof \WP_Post ) {
				$matches[] = (int) $post->ID;
			}
		}
		if ( 'property' === $entity && isset( $normalized['fields']['reference'] ) && is_string( $normalized['fields']['reference'] ) && '' !== $normalized['fields']['reference'] ) {
			$posts = get_posts(
				array(
					'post_type'      => 'property',
					'post_status'    => 'any',
					'meta_key'       => 'rep_reference',
					'meta_value'     => $normalized['fields']['reference'],
					'meta_compare'   => '=',
					'posts_per_page' => 2,
					'fields'         => 'ids',
				)
			);
			if ( count( $posts ) > 1 ) {
				return $this->error( 'conflict_duplicate_reference', 'The canonical reference matches more than one record.', 409 );
			}
			if ( isset( $posts[0] ) ) {
				$matches[] = (int) $posts[0];
			}
		}
		$matches = array_values( array_unique( array_filter( array_map( 'absint', $matches ) ) ) );
		if ( count( $matches ) > 1 ) {
			return $this->error( 'conflict_identity_mismatch', 'The supplied identities resolve to different records.', 409 );
		}
		return array( 'id' => (int) ( $matches[0] ?? 0 ) );
	}

	/** @param array<string,mixed> $term_input
	 * @return array{terms:array<string,list<array<string,mixed>>>,warnings:list<string>}|\WP_Error */
	private function resolveTerms( string $entity, array $term_input, bool $create_missing ): array|\WP_Error {
		$resolved = array();
		$warnings = array();
		foreach ( $term_input as $taxonomy => $references ) {
			if ( ! in_array( $taxonomy, $this->schema->taxonomyNames( $entity ), true ) || ! is_array( $references ) ) {
				return $this->error( 'taxonomy_invalid', 'The taxonomy is not allowlisted for this entity.', 400 );
			}
			$resolved[ $taxonomy ] = array();
			$seen                  = array();
			foreach ( $references as $reference ) {
				if ( ! is_array( $reference ) || ! isset( $reference['kind'], $reference['value'] ) ) {
					return $this->error( 'taxonomy_invalid_reference', 'The taxonomy reference is malformed.', 400 );
				}
				$term_id = $this->findTerm( $taxonomy, (string) $reference['kind'], $reference['value'] );
				if ( null !== $term_id ) {
					if ( isset( $seen[ $term_id ] ) ) {
						continue;
					}
					$seen[ $term_id ]        = true;
					$reference['term_id']    = $term_id;
					$resolved[ $taxonomy ][] = $reference;
					continue;
				}
				if ( ! $create_missing || 'id' === $reference['kind'] ) {
					return $this->error( 'taxonomy_missing_term', 'taxonomy: the referenced term does not exist and missing-term creation is disabled.', 400 );
				}
				$name = sanitize_text_field( (string) $reference['value'] );
				if ( '' === $name || strlen( $name ) > 200 ) {
					return $this->error( 'taxonomy_invalid_term', 'taxonomy: the missing term name is invalid.', 400 );
				}
				$key = strtolower( $taxonomy . ':' . $name );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ]            = true;
				$reference['create']     = true;
				$reference['name']       = $name;
				$reference['term_id']    = null;
				$resolved[ $taxonomy ][] = $reference;
				$warnings[]              = 'taxonomy: term ' . $name . ' will be created only during an import execution.';
			}
		}
		return array(
			'terms'    => $resolved,
			'warnings' => $warnings,
		);
	}

	private function findTerm( string $taxonomy, string $kind, mixed $value ): ?int {
		if ( 'id' === $kind ) {
			$id = StrictId::parse( $value );
			if ( 0 === $id ) {
				return null;
			}
			$exists = term_exists( $id, $taxonomy );
			if ( is_array( $exists ) && isset( $exists['term_id'] ) ) {
				return (int) $exists['term_id'];
			}
			return is_int( $exists ) && $exists > 0 ? $exists : null;
		}
		$name = trim( (string) $value );
		if ( '' === $name ) {
			return null;
		}
		$term = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
		if ( $term instanceof \WP_Term ) {
			return (int) $term->term_id;
		}
		$term = get_term_by( 'name', $name, $taxonomy );
		return $term instanceof \WP_Term ? (int) $term->term_id : null;
	}

	/** @param array<string,int> $relationships */
	private function validateRelationships( string $entity, array $relationships, int $actor_id ): bool|\WP_Error {
		if ( 'agent' === $entity && isset( $relationships['relationship_agency_id'] ) ) {
			if ( ! $this->usableTarget( 'agency', $relationships['relationship_agency_id'], $actor_id ) ) {
				return $this->error( 'relationship_invalid_agency', 'relationship: the agency target is not an accessible agency.', 400 );
			}
		}
		if ( 'property' === $entity ) {
			$has_agent  = isset( $relationships['relationship_agent_id'] );
			$has_agency = isset( $relationships['relationship_agency_id'] );
			if ( $has_agent xor $has_agency ) {
				return $this->error( 'relationship_partial_property', 'relationship: property agent and agency IDs must be supplied together.', 400 );
			}
			if ( $has_agent && ( ! $this->usableTarget( 'agent', $relationships['relationship_agent_id'], $actor_id ) || ! $this->usableTarget( 'agency', $relationships['relationship_agency_id'], $actor_id ) ) ) {
				return $this->error( 'relationship_invalid_property_target', 'relationship: the property targets are not accessible profiles.', 400 );
			}
			if ( $has_agent && (int) get_post_meta( $relationships['relationship_agent_id'], 'rep_agency_id', true ) !== $relationships['relationship_agency_id'] ) {
				return $this->error( 'relationship_inconsistent_property_target', 'relationship: the agent is not assigned to the supplied agency.', 400 );
			}
		}
		return true;
	}

	private function usableTarget( string $entity, int $id, int $actor_id ): bool {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || $entity !== $post->post_type ) {
			return false;
		}
		return 'publish' === $post->post_status || (int) $post->post_author === $actor_id || current_user_can( 'edit_others_' . self::ENTITY_PLURALS[ $entity ] );
	}

	/** @param array<string,mixed> $planned
	 * @param array<string,mixed> $settings */
	private function apply( array $planned, array $settings, int $actor_id ): int|\WP_Error {
		$row       = $planned['row'];
		$existing  = (int) $planned['id'];
		$created   = false;
		$post_id   = $existing;
		$snapshot  = $existing > 0 ? $this->snapshot( $settings['entity'], $existing ) : array();
		$new_media = array();
		try {
			if ( 'create' === $planned['decision'] ) {
				$post_id = $this->writeCreate( $settings['entity'], $row->normalized, $actor_id );
				if ( is_wp_error( $post_id ) ) {
					return $post_id;
				}
				$created = true;
			} else {
				$post_id = $this->writeUpdate( $settings['entity'], $existing, $row->normalized, $actor_id );
				if ( is_wp_error( $post_id ) ) {
					return $this->rollbackError( $settings['entity'], $existing, false, $snapshot, $new_media, $post_id );
				}
			}
			$meta_result = $this->applyFields( $settings['entity'], $post_id, $row->normalized );
			if ( is_wp_error( $meta_result ) ) {
				return $this->rollbackError( $settings['entity'], $post_id, $created, $snapshot, $new_media, $meta_result );
			}
			$terms_result = $this->applyTerms( $post_id, $row->normalized['terms'] ?? array() );
			if ( is_wp_error( $terms_result ) ) {
				return $this->rollbackError( $settings['entity'], $post_id, $created, $snapshot, $new_media, $terms_result );
			}
			$media_result = $this->applyMedia( $post_id, $row->normalized['media'], $settings['allow_remote_media'], $new_media );
			if ( is_wp_error( $media_result ) ) {
				return $this->rollbackError( $settings['entity'], $post_id, $created, $snapshot, $new_media, $media_result );
			}
			$relationship_result = $this->applyRelationships( $settings['entity'], $post_id, $row->normalized['relationships'], $actor_id );
			if ( is_wp_error( $relationship_result ) ) {
				return $this->rollbackError( $settings['entity'], $post_id, $created, $snapshot, $new_media, $relationship_result );
			}
			$verification = $this->verifyApplied( $settings['entity'], $post_id, $row->normalized );
			if ( is_wp_error( $verification ) ) {
				return $this->rollbackError( $settings['entity'], $post_id, $created, $snapshot, $new_media, $verification );
			}
			return $post_id;
		} catch ( \Throwable $exception ) {
			return $this->rollbackError( $settings['entity'], $post_id, $created, $snapshot, $new_media, $this->error( 'import_execution_failed', 'The row could not be applied safely.', 500 ) );
		}
	}

	/** @param array<string,mixed> $normalized */
	private function writeCreate( string $entity, array $normalized, int $actor_id ): int|\WP_Error {
		if ( in_array( $entity, array( 'agent', 'agency' ), true ) ) {
			$result = $this->profiles->create( $entity, $this->profileInput( $normalized ), $actor_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$id = (int) ( $result['id'] ?? 0 );
			if ( $id < 1 ) {
				return $this->error( 'import_create_failed', 'The profile was not created.', 500 );
			}
			if ( in_array( 'status', $normalized['provided']['post'] ?? array(), true ) ) {
				$status_result = $this->profiles->update( $entity, $id, array( 'status' => $normalized['post']['status'] ), $actor_id );
				if ( is_wp_error( $status_result ) ) {
					wp_delete_post( $id, true );
					return $status_result;
				}
			}
			$extra = $this->writePostExtras( $id, $normalized );
			if ( is_wp_error( $extra ) ) {
				wp_delete_post( $id, true );
				return $extra;
			}
			return $id;
		}
		$post = array(
			'post_type'    => $entity,
			'post_status'  => $normalized['post']['status'],
			'post_title'   => sanitize_text_field( (string) $normalized['post']['title'] ),
			'post_content' => wp_kses_post( (string) ( $normalized['post']['content'] ?? '' ) ),
			'post_excerpt' => wp_kses_post( (string) ( $normalized['post']['excerpt'] ?? '' ) ),
			'post_author'  => $actor_id,
		);
		if ( isset( $normalized['slug'] ) && is_string( $normalized['slug'] ) && '' !== $normalized['slug'] ) {
			$post['post_name'] = $normalized['slug'];
		}
		$id = wp_insert_post( $post, true );
		return is_wp_error( $id ) ? $id : (int) $id;
	}

	/** @param array<string,mixed> $normalized */
	private function writeUpdate( string $entity, int $id, array $normalized, int $actor_id ): int|\WP_Error {
		if ( in_array( $entity, array( 'agent', 'agency' ), true ) ) {
			$result = $this->profiles->update( $entity, $id, $this->profileInput( $normalized ), $actor_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$extra = $this->writePostExtras( $id, $normalized );
			return is_wp_error( $extra ) ? $extra : $id;
		}
		$change   = array(
			'ID'         => $id,
			'post_title' => sanitize_text_field( (string) $normalized['post']['title'] ),
		);
		$provided = $normalized['provided']['post'] ?? array();
		if ( in_array( 'content', $provided, true ) ) {
			$change['post_content'] = wp_kses_post( (string) $normalized['post']['content'] );
		}
		if ( in_array( 'excerpt', $provided, true ) ) {
			$change['post_excerpt'] = wp_kses_post( (string) $normalized['post']['excerpt'] );
		}
		if ( in_array( 'status', $provided, true ) ) {
			$change['post_status'] = $normalized['post']['status'];
		}
		if ( in_array( 'slug', $provided, true ) && isset( $normalized['slug'] ) && is_string( $normalized['slug'] ) ) {
			$change['post_name'] = $normalized['slug'];
		}
		$result = wp_update_post( $change, true );
		return is_wp_error( $result ) ? $result : $id;
	}

	/** @param array<string,mixed> $normalized */
	private function profileInput( array $normalized ): array {
		$input    = array( 'title' => (string) $normalized['post']['title'] );
		$provided = $normalized['provided']['post'] ?? array();
		if ( in_array( 'content', $provided, true ) ) {
			$input['content'] = (string) $normalized['post']['content'];
		}
		if ( in_array( 'status', $provided, true ) ) {
			$input['status'] = (string) $normalized['post']['status'];
		}
		foreach ( $normalized['fields'] as $key => $value ) {
			$input[ $key ] = $value;
		}
		return $input;
	}

	/** @param array<string,mixed> $normalized */
	private function writePostExtras( int $id, array $normalized ): bool|\WP_Error {
		$change   = array( 'ID' => $id );
		$provided = $normalized['provided']['post'] ?? array();
		if ( in_array( 'slug', $provided, true ) && isset( $normalized['slug'] ) && is_string( $normalized['slug'] ) ) {
			$change['post_name'] = $normalized['slug'];
		}
		if ( in_array( 'excerpt', $provided, true ) ) {
			$change['post_excerpt'] = wp_kses_post( (string) $normalized['post']['excerpt'] );
		}
		if ( count( $change ) < 2 ) {
			return true;
		}
		$result = wp_update_post( $change, true );
		return is_wp_error( $result ) ? $result : true;
	}

	/** @param array<string,mixed> $normalized */
	private function applyFields( string $entity, int $post_id, array $normalized ): bool|\WP_Error {
		foreach ( $normalized['fields'] as $key => $value ) {
			$definition = $this->schema->field( $entity, $key );
			if ( ! $definition instanceof FieldDefinition ) {
				continue;
			}
			if ( null === $value ) {
				delete_post_meta( $post_id, 'rep_' . $key );
				continue;
			}
			if ( ! $definition->validate( $value ) || ! ContentRegistrar::validateMeta( $definition, $value ) ) {
				return $this->error( 'invalid_meta', 'The normalized value for ' . $key . ' failed canonical validation.', 400 );
			}
			update_post_meta( $post_id, 'rep_' . $key, $value );
		}
		return true;
	}

	/** @param array<string,list<array<string,mixed>>> $terms */
	private function applyTerms( int $post_id, array $terms ): bool|\WP_Error {
		foreach ( $terms as $taxonomy => $references ) {
			$term_ids = array();
			foreach ( $references as $reference ) {
				$term_id = isset( $reference['term_id'] ) ? (int) $reference['term_id'] : 0;
				if ( $term_id < 1 && isset( $reference['name'] ) ) {
					$existing = $this->findTerm( $taxonomy, 'name', $reference['name'] );
					if ( null !== $existing ) {
						$term_id = $existing;
					} else {
						$created = wp_insert_term( (string) $reference['name'], $taxonomy );
						if ( is_wp_error( $created ) ) {
							$existing = $this->findTerm( $taxonomy, 'name', $reference['name'] );
							if ( null === $existing ) {
								return $created;
							}
							$term_id = $existing;
						} else {
							$term_id = (int) ( $created['term_id'] ?? 0 );
						}
					}
				}
				if ( $term_id < 1 || ! term_exists( $term_id, $taxonomy ) ) {
					return $this->error( 'taxonomy_apply_failed', 'A taxonomy term could not be resolved during execution.', 400 );
				}
				$term_ids[] = $term_id;
			}
			$result = wp_set_post_terms( $post_id, array_values( array_unique( $term_ids ) ), $taxonomy, false );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	/** @param array<string,array<string,mixed>> $media
	 * @param list<int> $new_media */
	private function applyMedia( int $post_id, array $media, bool $allow_remote, array &$new_media ): bool|\WP_Error {
		foreach ( $media as $key => $item ) {
			if ( ! is_array( $item ) || empty( $item['provided'] ) ) {
				continue;
			}
			$ids = array_values( array_map( 'absint', $item['ids'] ?? array() ) );
			foreach ( $item['remote'] ?? array() as $url ) {
				if ( ! $allow_remote ) {
					return $this->error( 'remote_media_not_allowed', 'media: remote media requires explicit opt-in.', 400 );
				}
				$attachment = $this->remote_media->import( (string) $url, $post_id );
				if ( is_wp_error( $attachment ) ) {
					return $attachment;
				}
				$ids[]       = (int) $attachment;
				$new_media[] = (int) $attachment;
			}
			$ids = array_values( array_unique( array_filter( $ids ) ) );
			if ( 'featured' === $key ) {
				if ( array() === $ids ) {
					delete_post_thumbnail( $post_id );
				} elseif ( count( $ids ) !== 1 || ! $this->media->validAttachment( $ids[0], 'image/' ) || ! set_post_thumbnail( $post_id, $ids[0] ) ) {
					return $this->error( 'media_apply_failed', 'media: the featured image could not be applied.', 400 );
				}
				continue;
			}
			$field = (string) ( $item['field'] ?? '' );
			$type  = (string) ( $item['type'] ?? '' );
			if ( 'attachments' === $type ) {
				$valid_ids = $this->media->normalizeGallery( $ids );
				if ( count( $valid_ids ) !== count( $ids ) ) {
					return $this->error( 'media_apply_failed', 'media: one or more gallery attachments are invalid.', 400 );
				}
				$ids = $valid_ids;
			}
			if ( array() === $ids ) {
				delete_post_meta( $post_id, 'rep_' . $field );
			} else {
				update_post_meta( $post_id, 'rep_' . $field, 'attachments' === $type ? $ids : $ids[0] );
			}
		}
		return true;
	}

	/** @param array<string,int> $relationships */
	private function applyRelationships( string $entity, int $post_id, array $relationships, int $actor_id ): bool|\WP_Error {
		if ( 'agent' === $entity && isset( $relationships['relationship_agency_id'] ) ) {
			$result = $this->profiles->assignAgency( $post_id, $relationships['relationship_agency_id'], $actor_id );
			return is_wp_error( $result ) ? $result : true;
		}
		if ( 'property' === $entity && isset( $relationships['relationship_agent_id'], $relationships['relationship_agency_id'] ) ) {
			$result = $this->profiles->assignProperty( $post_id, $relationships['relationship_agent_id'], $relationships['relationship_agency_id'], $actor_id );
			return is_wp_error( $result ) ? $result : true;
		}
		return true;
	}

	/** @param array<string,mixed> $normalized */
	private function verifyApplied( string $entity, int $post_id, array $normalized ): bool|\WP_Error {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $entity !== $post->post_type ) {
			return $this->error( 'import_verification_failed', 'The saved record could not be verified.', 500 );
		}
		if ( isset( $normalized['slug'] ) && is_string( $normalized['slug'] ) && '' !== $normalized['slug'] && $normalized['slug'] !== $post->post_name ) {
			return $this->error( 'import_verification_failed', 'The saved slug did not match the deterministic plan.', 500 );
		}
		if ( isset( $normalized['fields']['reference'] ) && (string) get_post_meta( $post_id, 'rep_reference', true ) !== (string) $normalized['fields']['reference'] ) {
			return $this->error( 'import_verification_failed', 'The saved canonical reference did not match the deterministic plan.', 500 );
		}
		return true;
	}

	/** @return array<string,mixed> */
	private function snapshot( string $entity, int $post_id ): array {
		$post     = get_post( $post_id );
		$snapshot = array(
			'post'      => $post instanceof \WP_Post ? array(
				'title'   => $post->post_title,
				'content' => $post->post_content,
				'excerpt' => $post->post_excerpt,
				'status'  => $post->post_status,
				'slug'    => $post->post_name,
			) : array(),
			'meta'      => array(),
			'terms'     => array(),
			'thumbnail' => (int) get_post_thumbnail_id( $post_id ),
		);
		foreach ( $this->schema->publicFields( $entity ) as $field ) {
			if ( in_array( $field->type, array( 'attachment', 'attachments' ), true ) ) {
				$snapshot['meta'][ $field->key ] = get_post_meta( $post_id, 'rep_' . $field->key, false );
			} else {
				$snapshot['meta'][ $field->key ] = get_post_meta( $post_id, 'rep_' . $field->key, false );
			}
		}
		foreach ( $this->schema->relationshipColumns( $entity ) as $relationship ) {
			$key                      = 'relationship_agent_id' === $relationship ? 'agent_id' : 'agency_id';
			$snapshot['meta'][ $key ] = get_post_meta( $post_id, 'rep_' . $key, false );
		}
		foreach ( $this->schema->taxonomyNames( $entity ) as $taxonomy ) {
			$ids                            = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			$snapshot['terms'][ $taxonomy ] = is_wp_error( $ids ) ? array() : array_values( array_map( 'absint', $ids ) );
		}
		return $snapshot;
	}

	/** @param array<string,mixed> $snapshot
	 * @param list<int> $new_media */
	private function rollbackError( string $entity, int $post_id, bool $created, array $snapshot, array $new_media, \WP_Error $cause ): \WP_Error {
		foreach ( $new_media as $attachment ) {
			if ( $attachment > 0 ) {
				wp_delete_attachment( $attachment, true );
			}
		}
		if ( $created ) {
			if ( $post_id > 0 ) {
				wp_delete_post( $post_id, true );
			}
			return $this->error( 'import_rolled_back', 'The row failed and its newly created content was rolled back. Reason: ' . $cause->get_error_message(), 400 );
		}
		if ( $post_id > 0 && array() !== $snapshot ) {
			$post = $snapshot['post'] ?? array();
			if ( array() !== $post ) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_title'   => $post['title'],
						'post_content' => $post['content'],
						'post_excerpt' => $post['excerpt'],
						'post_status'  => $post['status'],
						'post_name'    => $post['slug'],
					),
					true
				);
			}
			foreach ( $snapshot['meta'] ?? array() as $key => $values ) {
				delete_post_meta( $post_id, 'rep_' . $key );
				foreach ( is_array( $values ) ? $values : array() as $value ) {
					update_post_meta( $post_id, 'rep_' . $key, $value );
				}
			}
			foreach ( $snapshot['terms'] ?? array() as $taxonomy => $ids ) {
				wp_set_post_terms( $post_id, $ids, $taxonomy, false );
			}
			if ( (int) ( $snapshot['thumbnail'] ?? 0 ) > 0 ) {
				set_post_thumbnail( $post_id, (int) $snapshot['thumbnail'] );
			} else {
				delete_post_thumbnail( $post_id );
			}
		}
		return $this->error( 'import_rolled_back', 'The row failed and the prior record was restored. Reason: ' . $cause->get_error_message(), 400 );
	}

	/** @param mixed $value */
	private function normalizeField( FieldDefinition $definition, mixed $value, string $format ): mixed {
		$value = $this->sourceValue( $value, $format );
		if ( null === $value || '' === $value ) {
			return null;
		}
		switch ( $definition->type ) {
			case 'integer':
				if ( ! ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) ) {
					return $this->error( 'invalid_type', 'must be a non-negative integer.', 400 );
				}
				$clean = (int) $value;
				break;
			case 'number':
			case 'latitude':
			case 'longitude':
				if ( ! $this->numeric( $value ) ) {
					return $this->error( 'invalid_type', 'must be a finite number.', 400 );
				}
				$clean = (float) $value;
				break;
			case 'boolean':
				$clean = $this->booleanValue( $value );
				if ( null === $clean ) {
					return $this->error( 'invalid_type', 'must be true, false, 1, or 0.', 400 );
				}
				break;
			case 'url':
				if ( ! is_string( $value ) || strlen( $value ) > 2048 ) {
					return $this->error( 'invalid_type', 'must be a bounded URL string.', 400 );
				}
				$clean = esc_url_raw( $value, array( 'https', 'http' ) );
				if ( '' === $clean || false === wp_http_validate_url( $clean ) ) {
					return $this->error( 'invalid_type', 'must be an HTTP or HTTPS URL.', 400 );
				}
				break;
			default:
				if ( ! is_string( $value ) ) {
					return $this->error( 'invalid_type', 'must be a string.', 400 );
				}
				$limit = 'text' === $definition->type ? 65535 : 2048;
				if ( strlen( $value ) > $limit ) {
					return $this->error( 'invalid_length', 'exceeds its length limit.', 400 );
				}
				$clean = $definition->sanitize( $value );
				break;
		}
		if ( ! $definition->validate( $clean ) ) {
			return $this->error( 'invalid_value', 'failed canonical field validation.', 400 );
		}
		return $clean;
	}

	/** @return list<array{kind:string,value:mixed}>|\WP_Error */
	private function normalizeTerms( mixed $value, string $format ): array|\WP_Error {
		$value = $this->sourceValue( $value, $format );
		if ( null === $value || '' === $value ) {
			return array();
		}
		if ( is_string( $value ) ) {
			$value = array_map( 'trim', explode( '|', $value ) );
		} elseif ( is_int( $value ) ) {
			$value = array( $value );
		}
		if ( ! is_array( $value ) || count( $value ) > 50 ) {
			return $this->error( 'invalid_terms', 'must be a bounded string or array.', 400 );
		}
		$references = array();
		foreach ( $value as $item ) {
			if ( is_int( $item ) && $item > 0 ) {
				$references[] = array(
					'kind'  => 'id',
					'value' => $item,
				);
				continue;
			}
			if ( is_string( $item ) ) {
				$item = trim( $item );
				if ( '' === $item ) {
					continue;
				}
				if ( ctype_digit( $item ) && StrictId::parse( $item ) > 0 ) {
					$references[] = array(
						'kind'  => 'id',
						'value' => StrictId::parse( $item ),
					);
				} elseif ( strlen( $item ) <= 200 ) {
					$references[] = array(
						'kind'  => 'name',
						'value' => $item,
					);
				} else {
					return $this->error( 'invalid_terms', 'a term name is too long.', 400 );
				}
				continue;
			}
			return $this->error( 'invalid_terms', 'each term must be an ID or string.', 400 );
		}
		return $references;
	}

	/** @param array<string,mixed> $raw
	 * @param array<string,mixed> $settings
	 * @param list<string> $errors
	 * @return array<string,array<string,mixed>> */
	private function normalizeMedia( string $entity, array $raw, array $settings, array &$errors ): array {
		$media    = array();
		$featured = $this->mediaInput( 'featured', 'attachment', array_key_exists( 'featured_image_id', $raw ), array_key_exists( 'featured_image_url', $raw ), $raw['featured_image_id'] ?? null, $raw['featured_image_url'] ?? null, $settings['format'], $settings['allow_remote_media'], $errors );
		if ( null !== $featured ) {
			$media['featured'] = $featured;
		}
		foreach ( $this->schema->mediaFields( $entity ) as $field ) {
			$id_column  = 'attachments' === $field->type ? 'media_' . $field->key . '_ids' : 'media_' . $field->key . '_id';
			$url_column = 'media_' . $field->key . '_urls';
			$item       = $this->mediaInput( $field->key, $field->type, array_key_exists( $id_column, $raw ), array_key_exists( $url_column, $raw ), $raw[ $id_column ] ?? null, $raw[ $url_column ] ?? null, $settings['format'], $settings['allow_remote_media'], $errors );
			if ( null !== $item ) {
				$media[ $field->key ] = $item;
			}
		}
		return $media;
	}

	/** @param list<string> $errors
	 * @return array<string,mixed>|null */
	private function mediaInput( string $field, string $type, bool $has_ids, bool $has_urls, mixed $ids_value, mixed $urls_value, string $format, bool $allow_remote, array &$errors ): ?array {
		if ( ! $has_ids && ! $has_urls ) {
			return null;
		}
		$ids          = array();
		$remote       = array();
		$ids_provided = $has_ids && ! $this->emptyInput( $ids_value, $format );
		if ( $ids_provided ) {
			$id_list = $this->mediaList( $ids_value, $format, 'attachments' === $type );
			if ( is_wp_error( $id_list ) ) {
				$errors[] = 'media: ' . $field . ' ' . $id_list->get_error_message();
			} else {
				foreach ( $id_list as $id ) {
					if ( is_string( $id ) && ctype_digit( $id ) ) {
						$id = StrictId::parse( $id );
					}
					$prefix = 'attachments' === $type || 'featured' === $field ? 'image/' : null;
					if ( ! is_int( $id ) || $id < 1 || ! $this->media->validAttachment( $id, $prefix ) ) {
						$errors[] = 'media: ' . $field . ' contains an invalid attachment ID.';
					} else {
						$ids[] = $id;
					}
				}
			}
		}
		if ( $has_urls && ! $this->emptyInput( $urls_value, $format ) ) {
			$url_list = $this->mediaList( $urls_value, $format, 'attachments' === $type || 'featured' === $field );
			if ( is_wp_error( $url_list ) ) {
				$errors[] = 'media: ' . $field . ' ' . $url_list->get_error_message();
			} else {
				foreach ( $url_list as $url ) {
					if ( ! is_string( $url ) ) {
						$errors[] = 'media: ' . $field . ' URLs must be strings.';
						continue;
					}
					$url           = esc_url_raw( $url, array( 'https', 'http' ) );
					$attachment_id = '' !== $url ? (int) attachment_url_to_postid( $url ) : 0;
					$prefix        = 'featured' === $field || 'attachments' === $type ? 'image/' : null;
					if ( $ids_provided ) {
						if ( $attachment_id < 1 || ! in_array( $attachment_id, $ids, true ) || ! $this->media->validAttachment( $attachment_id, $prefix ) ) {
							$errors[] = 'media: supplied ID and URL must resolve to the same existing attachment.';
						}
						continue;
					}
					if ( $attachment_id > 0 && $this->media->validAttachment( $attachment_id, $prefix ) ) {
						$ids[] = $attachment_id;
						continue;
					}
					if ( ! $allow_remote ) {
						$errors[] = 'media: URL is not an existing attachment; remote media is opt-in.';
						continue;
					}
					$verified = $this->remote_media->validate( $url );
					if ( is_wp_error( $verified ) ) {
						$errors[] = 'media: remote URL is NOT VERIFIED and was rejected.';
						continue;
					}
					$remote[] = $verified;
				}
			}
		}
		if ( 'attachment' === $type || 'featured' === $field ) {
			if ( count( $ids ) + count( $remote ) > 1 ) {
				$errors[] = 'media: ' . $field . ' accepts only one attachment.';
			}
		}
		return array(
			'field'    => $field,
			'type'     => $type,
			'provided' => true,
			'ids'      => array_values( array_unique( $ids ) ),
			'remote'   => array_values( array_unique( $remote ) ),
		);
	}

	/** @return list<int|string>|\WP_Error */
	private function mediaList( mixed $value, string $format, bool $multiple ): array|\WP_Error {
		$value = $this->sourceValue( $value, $format );
		if ( is_string( $value ) ) {
			$value = $multiple ? array_map( 'trim', explode( '|', $value ) ) : array( trim( $value ) );
		} elseif ( is_int( $value ) ) {
			$value = array( $value );
		}
		if ( ! is_array( $value ) || ( ! $multiple && count( $value ) > 1 ) || count( $value ) > 50 ) {
			return $this->error( 'invalid_media_list', 'must be a bounded scalar or array.', 400 );
		}
		$result = array();
		foreach ( $value as $item ) {
			if ( is_int( $item ) && $item > 0 ) {
				$result[] = $item;
			} elseif ( is_string( $item ) && '' !== trim( $item ) ) {
				$result[] = trim( $item );
			} else {
				return $this->error( 'invalid_media_list', 'contains an invalid ID or URL.', 400 );
			}
		}
		return $result;
	}

	private function emptyInput( mixed $value, string $format ): bool {
		$value = $this->sourceValue( $value, $format );
		return null === $value || ( is_string( $value ) && '' === trim( $value ) ) || ( is_array( $value ) && array() === $value );
	}

	private function sourceValue( mixed $value, string $format ): mixed {
		if ( 'csv' !== $format || ! is_string( $value ) ) {
			return $value;
		}
		return $this->cleanCsvCell( $value );
	}

	private function cleanCsvCell( string $value ): string {
		if ( strlen( $value ) > 1 && "'" === $value[0] && in_array( $value[1], array( '=', '+', '-', '@' ), true ) ) {
			return substr( $value, 1 );
		}
		return $value;
	}

	private function numeric( mixed $value ): bool {
		if ( ! is_int( $value ) && ! is_float( $value ) && ! is_string( $value ) ) {
			return false;
		}
		if ( is_string( $value ) && ! preg_match( '/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $value ) ) {
			return false;
		}
		return is_finite( (float) $value );
	}

	private function booleanValue( mixed $value ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) && in_array( $value, array( 0, 1 ), true ) ) {
			return 1 === $value;
		}
		if ( is_string( $value ) ) {
			return match ( strtolower( trim( $value ) ) ) {
				'1', 'true'  => true,
				'0', 'false' => false,
				default      => null,
			};
		}
		return null;
	}

	private function booleanOption( mixed $value ): ?bool {
		return $this->booleanValue( $value );
	}

	private function identityLabel( array $normalized ): string {
		if ( isset( $normalized['id'] ) && (int) $normalized['id'] > 0 ) {
			return 'id:' . (int) $normalized['id'];
		}
		if ( isset( $normalized['slug'] ) && is_string( $normalized['slug'] ) && '' !== $normalized['slug'] ) {
			return 'slug:' . $normalized['slug'];
		}
		if ( isset( $normalized['fields']['reference'] ) && is_string( $normalized['fields']['reference'] ) ) {
			return 'reference:' . $normalized['fields']['reference'];
		}
		return '';
	}

	private function issue( string $code, string $message ): string {
		if ( str_starts_with( $code, 'taxonomy' ) ) {
			return 'taxonomy: ' . $message;
		}
		if ( str_starts_with( $code, 'relationship' ) ) {
			return 'relationship: ' . $message;
		}
		if ( str_starts_with( $code, 'media' ) || str_starts_with( $code, 'remote_media' ) ) {
			return 'media: ' . $message;
		}
		return $code . ': ' . $message;
	}

	private function storageDirectory(): string {
		$uploads = wp_upload_dir();
		return trailingslashit( (string) ( $uploads['basedir'] ?? WP_CONTENT_DIR . '/uploads' ) ) . 'realestate-platform-imports';
	}

	private function safeExistingPath( string $base, string $path ): bool {
		$base_real = realpath( $base );
		$file_real = realpath( $path );
		if ( false === $base_real || false === $file_real || is_link( $path ) ) {
			return false;
		}
		$root = rtrim( wp_normalize_path( $base_real ), '/' ) . '/';
		return str_starts_with( wp_normalize_path( $file_real ), $root ) && is_file( $file_real );
	}

	private function error( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
