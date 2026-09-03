<?php
/**
 * Provider-neutral import/export schema derived from canonical registries.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\ImportExport;

use InvalidArgumentException;
use Mayfair\RealEstatePlatform\Classification\TaxonomyRegistry;
use Mayfair\RealEstatePlatform\Fields\FieldDefinition;
use Mayfair\RealEstatePlatform\Fields\FieldRegistry;

final class SchemaCatalog {
	private const ENTITIES       = array( 'property', 'project', 'insight', 'agent', 'agency' );
	private const COMMON_COLUMNS = array( 'id', 'slug', 'title', 'content', 'excerpt', 'status' );
	private const RELATIONSHIPS  = array(
		'agent'    => array( 'relationship_agency_id' ),
		'agency'   => array(),
		'property' => array( 'relationship_agent_id', 'relationship_agency_id' ),
		'project'  => array(),
		'insight'  => array(),
	);
	/** @var array<string,list<string>> */
	private array $column_cache = array();

	public function __construct( private FieldRegistry $fields, private TaxonomyRegistry $taxonomies ) {}

	/** @return list<string> */
	public function entities(): array {
		return self::ENTITIES;
	}

	/** @return list<string> */
	public function columns( string $entity ): array {
		$this->assertEntity( $entity );
		if ( isset( $this->column_cache[ $entity ] ) ) {
			return $this->column_cache[ $entity ];
		}
		$columns = self::COMMON_COLUMNS;
		foreach ( $this->publicFields( $entity ) as $field ) {
			if ( in_array( $field->type, array( 'attachment', 'attachments' ), true ) ) {
				continue;
			}
			$columns[] = $field->key;
		}
		foreach ( $this->mediaFields( $entity ) as $field ) {
			$suffix    = 'attachments' === $field->type ? 'ids' : 'id';
			$columns[] = 'media_' . $field->key . '_' . $suffix;
			$columns[] = 'media_' . $field->key . '_urls';
		}
		$columns[] = 'featured_image_id';
		$columns[] = 'featured_image_url';
		foreach ( $this->taxonomyNames( $entity ) as $taxonomy ) {
			$columns[] = 'tax_' . $taxonomy;
		}
		foreach ( self::RELATIONSHIPS[ $entity ] as $relationship ) {
			$columns[] = $relationship;
		}
		$this->column_cache[ $entity ] = array_values( array_unique( $columns ) );
		return $this->column_cache[ $entity ];
	}

	/** @return list<string> */
	public function importColumns( string $entity ): array {
		return $this->columns( $entity );
	}

	/** @return list<string> */
	public function exportColumns( string $entity ): array {
		return $this->columns( $entity );
	}

	public function allowsColumn( string $entity, string $column ): bool {
		return in_array( $column, $this->columns( $entity ), true );
	}

	public function field( string $entity, string $column ): ?FieldDefinition {
		$this->assertEntity( $entity );
		$field = $this->fields->forEntity( $entity )[ $column ] ?? null;
		if ( ! $field instanceof FieldDefinition || ! $this->isPublicField( $field ) || in_array( $field->type, array( 'attachment', 'attachments' ), true ) ) {
			return null;
		}
		return $field;
	}

	/** @return list<FieldDefinition> */
	public function mediaFields( string $entity ): array {
		$this->assertEntity( $entity );
		return array_values(
			array_filter(
				$this->fields->forEntity( $entity ),
				fn( FieldDefinition $field ): bool => $this->isPublicField( $field ) && in_array( $field->type, array( 'attachment', 'attachments' ), true )
			)
		);
	}

	/** @return list<FieldDefinition> */
	public function publicFields( string $entity ): array {
		$this->assertEntity( $entity );
		return array_values( array_filter( $this->fields->forEntity( $entity ), fn( FieldDefinition $field ): bool => $this->isPublicField( $field ) ) );
	}

	/** @return list<string> */
	public function taxonomyNames( string $entity ): array {
		$this->assertEntity( $entity );
		$names = array();
		foreach ( $this->taxonomies->definitions() as $taxonomy => $definition ) {
			if ( in_array( $entity, $definition['entities'], true ) ) {
				$names[] = $taxonomy;
			}
		}
		return $names;
	}

	public function taxonomyForColumn( string $entity, string $column ): ?string {
		if ( ! str_starts_with( $column, 'tax_' ) ) {
			return null;
		}
		$taxonomy = substr( $column, 4 );
		return in_array( $taxonomy, $this->taxonomyNames( $entity ), true ) ? $taxonomy : null;
	}

	/** @return array{field:string,type:string,kind:string}|null */
	public function mediaForColumn( string $entity, string $column ): ?array {
		foreach ( $this->mediaFields( $entity ) as $field ) {
			$prefix = 'media_' . $field->key . '_';
			if ( ! str_starts_with( $column, $prefix ) ) {
				continue;
			}
			$suffix = substr( $column, strlen( $prefix ) );
			if ( 'urls' === $suffix ) {
				return array(
					'field' => $field->key,
					'type'  => $field->type,
					'kind'  => 'url',
				);
			}
			if ( 'id' === $suffix && 'attachment' === $field->type ) {
				return array(
					'field' => $field->key,
					'type'  => $field->type,
					'kind'  => 'id',
				);
			}
			if ( 'ids' === $suffix && 'attachments' === $field->type ) {
				return array(
					'field' => $field->key,
					'type'  => $field->type,
					'kind'  => 'id',
				);
			}
		}
		return null;
	}

	public function isRelationshipColumn( string $entity, string $column ): bool {
		return in_array( $column, self::RELATIONSHIPS[ $entity ] ?? array(), true );
	}

	/** @return list<string> */
	public function relationshipColumns( string $entity ): array {
		$this->assertEntity( $entity );
		return self::RELATIONSHIPS[ $entity ];
	}

	private function isPublicField( FieldDefinition $field ): bool {
		if ( ! $field->frontend_visible || ! $field->rest_exposed ) {
			return false;
		}
		return ! in_array( $field->key, array( 'private_notes', 'agency_id', 'agent_id' ), true );
	}

	private function assertEntity( string $entity ): void {
		if ( ! in_array( $entity, self::ENTITIES, true ) ) {
			throw new InvalidArgumentException( 'Unsupported import/export entity.' );
		}
	}
}
