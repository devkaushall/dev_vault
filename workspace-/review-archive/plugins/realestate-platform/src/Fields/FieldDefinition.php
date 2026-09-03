<?php
/**
 * Canonical content field definition.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Fields;

use InvalidArgumentException;

final class FieldDefinition {
	/** @param list<string> $entities */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly string $type,
		public readonly array $entities,
		public readonly string $description = '',
		public readonly mixed $default_value = null,
		public readonly bool $required = false,
		public readonly bool $searchable = false,
		public readonly bool $sortable = false,
		public readonly bool $filterable = false,
		public readonly bool $rest_exposed = true,
		public readonly bool $elementor_exposed = true,
		public readonly bool $frontend_visible = true
	) {
		if ( ! preg_match( '/^[a-z][a-z0-9_]*$/', $key ) || ! in_array( $type, self::types(), true ) || array() === $entities ) {
			throw new InvalidArgumentException( 'Invalid field definition.' );
		}
	}

	/** @return list<string> */
	public static function types(): array {
		return array( 'string', 'text', 'integer', 'number', 'boolean', 'url', 'latitude', 'longitude', 'attachment', 'attachments' );
	}

	public function sanitize( mixed $value ): mixed {
		if ( null === $value || '' === $value ) {
			return $this->required ? $this->default_value : null;
		}
		return match ( $this->type ) {
			'integer', 'attachment' => absint( $value ),
			'number', 'latitude', 'longitude' => (float) $value,
			'boolean' => (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN ),
			'url' => esc_url_raw( (string) $value, array( 'https', 'http' ) ),
			'attachments' => array_values( array_unique( array_filter( array_map( 'absint', is_array( $value ) ? $value : array() ) ) ) ),
			'text' => sanitize_textarea_field( (string) $value ),
			default => sanitize_text_field( (string) $value ),
		};
	}

	public function validate( mixed $value ): bool {
		if ( null === $value || '' === $value || array() === $value ) {
			return ! $this->required;
		}
		return match ( $this->type ) {
			'integer' => is_int( $value ) && $value >= 0,
			'number' => is_float( $value ) || is_int( $value ),
			'boolean' => is_bool( $value ),
			'url' => is_string( $value ) && '' !== $value && false !== wp_http_validate_url( $value ),
			'latitude' => is_float( $value ) && $value >= -90 && $value <= 90,
			'longitude' => is_float( $value ) && $value >= -180 && $value <= 180,
			'attachment' => is_int( $value ) && $value > 0 && 'attachment' === get_post_type( $value ),
			'attachments' => is_array( $value ) && ! array_filter( $value, static fn( $id ) => ! is_int( $id ) || 'attachment' !== get_post_type( $id ) ),
			default => is_string( $value ),
		};
	}

	/** @return array<string, mixed> */
	public function restSchema(): array {
		$type = match ( $this->type ) {
			'integer', 'attachment' => 'integer',
			'number', 'latitude', 'longitude' => 'number',
			'boolean' => 'boolean',
			'attachments' => 'array',
			default => 'string',
		};
		$schema = array(
			'type'        => $type,
			'required'    => $this->required,
			'description' => $this->description,
		);
		if ( 'array' === $type ) {
			$schema['items'] = array(
				'type'    => 'integer',
				'minimum' => 1,
			);
		}
		if ( in_array( $this->type, array( 'integer', 'attachment' ), true ) ) {
			$schema['minimum'] = 'attachment' === $this->type ? 1 : 0;
		}
		if ( 'latitude' === $this->type ) {
			$schema['minimum'] = -90;
			$schema['maximum'] = 90;
		}
		if ( 'longitude' === $this->type ) {
			$schema['minimum'] = -180;
			$schema['maximum'] = 180;
		}
		if ( 'url' === $this->type ) {
			$schema['format']    = 'uri';
			$schema['maxLength'] = 2048;
		}
		if ( in_array( $this->type, array( 'string', 'text' ), true ) ) {
			$schema['maxLength'] = 'text' === $this->type ? 65535 : 2048;
		}
		return $schema;
	}
}
