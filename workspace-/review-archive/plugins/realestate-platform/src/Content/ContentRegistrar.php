<?php
/**
 * Property, project, and insight registration/adoption boundary.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Content;

use Mayfair\RealEstatePlatform\Compatibility\OperatingMode;
use Mayfair\RealEstatePlatform\Fields\FieldDefinition;
use Mayfair\RealEstatePlatform\Fields\FieldRegistry;

final class ContentRegistrar {
	public function __construct( private FieldRegistry $fields ) {}

	public function register( OperatingMode $mode ): void {
		foreach ( $this->entities() as $name => $args ) {
			/** @var lowercase-string&non-empty-string $name */
			if ( ! post_type_exists( $name ) && OperatingMode::Standalone === $mode ) {
				register_post_type( $name, $args );
			}
			if ( post_type_exists( $name ) ) {
				$this->registerMeta( $name );
			}
		}
	}

	/** @return array<string, array<string, mixed>> */
	public function entities(): array {
		return array(
			'property' => $this->args( 'Properties', 'Property', array( 'property', 'properties' ) ),
			'project'  => $this->args( 'Projects', 'Project', array( 'project', 'projects' ) ),
			'insight'  => $this->args( 'Insights', 'Insight', array( 'insight', 'insights' ) ),
			'agent'    => $this->args( 'Agents', 'Agent', array( 'agent', 'agents' ) ),
			'agency'   => $this->args( 'Agencies', 'Agency', array( 'agency', 'agencies' ) ),
		);
	}

	/** @param array{0:string,1:string} $capability_type
	 * @return array<string, mixed> */
	private function args( string $plural, string $singular, array $capability_type ): array {
		return array(
			'labels'          => array(
				'name'          => $plural,
				'singular_name' => $singular,
			),
			'public'          => true,
			'show_in_rest'    => true,
			'rest_base'       => strtolower( $plural ),
			'show_in_menu'    => 'realestate-platform',
			'has_archive'     => true,
			'rewrite'         => array( 'slug' => strtolower( $plural ) ),
			'supports'        => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'revisions', 'custom-fields' ),
			'capability_type' => $capability_type,
			'map_meta_cap'    => true,
		);
	}

	private function registerMeta( string $entity ): void {
		foreach ( $this->fields->forEntity( $entity ) as $field ) {
			$args = array(
				'single'            => 'attachments' !== $field->type,
				'type'              => $this->metaType( $field ),
				'show_in_rest'      => $field->rest_exposed ? array( 'schema' => $field->restSchema() ) : false,
				'sanitize_callback' => static fn( $value ) => self::sanitizeMeta( $field, $value ),
				'auth_callback'     => static fn( bool $allowed, string $key, int $post_id ) => $allowed && current_user_can( 'edit_post', $post_id ),
			);
			if ( null !== $field->default_value ) {
				$args['default'] = $field->default_value;
			}
			register_post_meta( $entity, 'rep_' . $field->key, $args );
		}
	}

	public function validateRestRequest( string $entity, mixed $prepared, \WP_REST_Request $request ): mixed {
		$taxonomy = match ( $entity ) {
			'project' => 'project_type',
			'insight' => 'insight_topic',
			default   => '',
		};
		if ( '' !== $taxonomy && null !== $request->get_param( $taxonomy ) ) {
			$term_ids = $request->get_param( $taxonomy );
			if ( ! is_array( $term_ids ) || array_filter( $term_ids, static fn( $term_id ) => ! is_int( $term_id ) || ! term_exists( $term_id, $taxonomy ) ) ) {
				return new \WP_Error(
					'realestate_invalid_taxonomy',
					__( 'One or more taxonomy references are invalid.', 'realestate-platform' ),
					array(
						'status' => 400,
						'field'  => $taxonomy,
					)
				);
			}
		}

		$meta = $request->get_param( 'meta' );
		if ( null === $meta ) {
			return $prepared;
		}
		if ( ! is_array( $meta ) ) {
			return new \WP_Error( 'realestate_invalid_meta', __( 'Metadata must be an object.', 'realestate-platform' ), array( 'status' => 400 ) );
		}
		$definitions = $this->fields->forEntity( $entity );
		foreach ( $meta as $key => $value ) {
			$canonical = str_starts_with( (string) $key, 'rep_' ) ? substr( (string) $key, 4 ) : '';
			if ( ! isset( $definitions[ $canonical ] ) || ! self::validateMeta( $definitions[ $canonical ], $value ) ) {
				return new \WP_Error(
					'realestate_invalid_meta',
					__( 'One or more metadata values are invalid.', 'realestate-platform' ),
					array(
						'status' => 400,
						'field'  => sanitize_key( (string) $key ),
					)
				);
			}
		}
		return $prepared;
	}

	public static function sanitizeMeta( FieldDefinition $field, mixed $value ): mixed {
		$clean = $field->sanitize( $value );
		return $field->validate( $clean ) ? $clean : null;
	}

	public static function validateMeta( FieldDefinition $field, mixed $value ): bool {
		$clean = $field->sanitize( $value );
		if ( null !== $value && '' !== $value && ( null === $clean || '' === $clean ) ) {
			return false;
		}
		return $field->validate( $clean );
	}

	private function metaType( FieldDefinition $field ): string {
		return match ( $field->type ) {
			'integer', 'attachment' => 'integer',
			'number', 'latitude', 'longitude' => 'number',
			'boolean' => 'boolean',
			'attachments' => 'array',
			default => 'string',
		};
	}
}
