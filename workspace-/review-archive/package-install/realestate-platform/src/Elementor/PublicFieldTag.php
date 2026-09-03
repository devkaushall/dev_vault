<?php
/** Elementor dynamic tag for one allowlisted public REP value. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Elementor;

final class PublicFieldTag extends \Elementor\Core\DynamicTags\Tag {
	/** @param array{id:string,title:string,entity:string,field:string,type:string} $definition */
	public function __construct( private PublicContext $context, private array $definition ) {}

	public function get_name(): string {
		return $this->definition['id'];
	}

	public function get_title(): string {
		return esc_html( $this->definition['title'] );
	}

	public function get_group(): array {
		return array( 'realestate-platform' );
	}

	public function get_categories(): array {
		$category = 'url' === $this->definition['type'] ? 'url' : 'text';
		$constant = 'url' === $category ? 'URL_CATEGORY' : 'TEXT_CATEGORY';
		$class    = '\\Elementor\\Modules\\DynamicTags\\Module';
		return defined( $class . '::' . $constant ) ? array( constant( $class . '::' . $constant ) ) : array( $category );
	}

	public function render(): void {
		$value = $this->context->resolve( $this->definition['entity'], $this->definition['field'] );
		if ( null === $value || false === $value || '' === $value ) {
			return;
		}
		if ( 'url' === $this->definition['type'] ) {
			echo esc_url( (string) $value );
			return;
		}
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( static fn( mixed $item ): string => (string) $item, $value ) );
		}
		if ( is_float( $value ) || is_int( $value ) ) {
			if ( in_array( $this->definition['field'], array( 'latitude', 'longitude' ), true ) ) {
				$value = rtrim( rtrim( number_format( (float) $value, 6, '.', '' ), '0' ), '.' );
			} else {
				$value = function_exists( 'number_format_i18n' ) ? number_format_i18n( $value, 2 ) : (string) $value;
			}
		}
		echo esc_html( (string) $value );
	}
}
