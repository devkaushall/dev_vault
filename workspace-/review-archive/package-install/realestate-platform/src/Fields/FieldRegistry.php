<?php
/**
 * Single canonical content field registry.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Fields;

final class FieldRegistry {
	/** @return array<string, FieldDefinition> */
	public function all(): array {
		static $fields;
		if ( is_array( $fields ) ) {
			return $fields;
		}
		$fields   = array();
		$add      = static function ( FieldDefinition $field ) use ( &$fields ): void {
			$fields[ $field->key ] = $field;
		};
		$property = array( 'property' );
		$project  = array( 'project' );
		$insight  = array( 'insight' );
		$shared   = array( 'property', 'project' );
		foreach ( array(
			new FieldDefinition( 'reference', 'Reference', 'string', $property, 'Stable listing reference.', null, false, true, true, true ),
			new FieldDefinition( 'price', 'Price', 'number', $shared, 'Canonical numeric price; never formatted.', null, false, false, true, true ),
			new FieldDefinition( 'currency', 'Currency', 'string', $shared, 'ISO 4217 currency code.', 'INR', false, false, false, true ),
			new FieldDefinition( 'price_period', 'Price period', 'string', $property, 'Optional rental price period.', null, false, false, false, true ),
			new FieldDefinition( 'address', 'Address', 'text', $shared ),
			new FieldDefinition( 'city', 'City', 'string', $shared, '', null, false, true, false, true ),
			new FieldDefinition( 'state', 'State', 'string', $shared, '', null, false, true, false, true ),
			new FieldDefinition( 'country', 'Country', 'string', $shared, '', 'IN', false, true, false, true ),
			new FieldDefinition( 'postal_code', 'Postal code', 'string', $shared, '', null, false, true, false, true ),
			new FieldDefinition( 'latitude', 'Latitude', 'latitude', $shared, 'WGS84 latitude.', null, false, false, true, true ),
			new FieldDefinition( 'longitude', 'Longitude', 'longitude', $shared, 'WGS84 longitude.', null, false, false, true, true ),
			new FieldDefinition( 'coordinate_privacy', 'Coordinate privacy', 'string', $property, 'Public marker precision: exact, rounded, approximate, or hidden.', 'exact' ),
			new FieldDefinition( 'area', 'Area', 'number', $property, '', null, false, false, true, true ),
			new FieldDefinition( 'area_unit', 'Area unit', 'string', $property, '', null, false, false, false, true ),
			new FieldDefinition( 'plot_area', 'Plot area', 'number', $property, '', null, false, false, true, true ),
			new FieldDefinition( 'bedrooms', 'Bedrooms', 'integer', $property, '', null, false, false, true, true ),
			new FieldDefinition( 'bathrooms', 'Bathrooms', 'integer', $property, '', null, false, false, true, true ),
			new FieldDefinition( 'floors', 'Floors', 'integer', $property ),
			new FieldDefinition( 'floor', 'Floor', 'integer', $property ),
			new FieldDefinition( 'parking', 'Parking spaces', 'integer', $property ),
			new FieldDefinition( 'year_built', 'Year built', 'integer', $property ),
			new FieldDefinition( 'furnishing', 'Furnishing', 'string', $property, '', null, false, false, false, true ),
			new FieldDefinition( 'possession', 'Possession', 'string', $shared, '', null, false, false, true, true ),
			new FieldDefinition( 'availability', 'Availability', 'string', $property, '', null, false, false, false, true ),
			new FieldDefinition( 'construction_status', 'Construction status', 'string', $shared, '', null, false, false, false, true ),
			new FieldDefinition( 'developer', 'Developer', 'string', $shared, '', null, false, true, false, true ),
			new FieldDefinition( 'rera', 'RERA', 'string', $shared, '', null, false, true, false, true ),
			new FieldDefinition( 'featured', 'Featured', 'boolean', array( 'property', 'project', 'insight' ), '', false, false, true, true ),
			new FieldDefinition( 'verified', 'Verified', 'boolean', $property, '', false, false, true, true ),
			new FieldDefinition( 'virtual_tour', 'Virtual tour', 'url', $property ),
			new FieldDefinition( 'video', 'Video', 'url', $shared ),
			new FieldDefinition( 'brochure', 'Brochure', 'attachment', $shared ),
			new FieldDefinition( 'floor_plan', 'Floor plan', 'attachment', $property ),
			new FieldDefinition( 'gallery', 'Gallery', 'attachments', $shared ),
			new FieldDefinition( 'public_phone', 'Public phone', 'string', array( 'agent', 'agency' ), '', null, false, true ),
			new FieldDefinition( 'public_email', 'Public email', 'string', array( 'agent', 'agency' ), '', null, false, true ),
			new FieldDefinition( 'website', 'Website', 'url', array( 'agent', 'agency' ) ),
			new FieldDefinition( 'license_number', 'License number', 'string', array( 'agent', 'agency' ), '', null, false, true ),
			new FieldDefinition( 'office_address', 'Office address', 'text', array( 'agency' ) ),
			new FieldDefinition( 'agency_id', 'Agency', 'integer', array( 'agent', 'property' ), '', null, false, false, false, true, false, false, false ),
			new FieldDefinition( 'agent_id', 'Agent', 'integer', array( 'property' ), '', null, false, false, false, true, false, false, false ),
			new FieldDefinition( 'private_notes', 'Private notes', 'text', array( 'agent', 'agency' ), '', null, false, false, false, false, false, false, false ),
			new FieldDefinition( 'subtitle', 'Subtitle', 'string', $insight ),
			new FieldDefinition( 'reading_time', 'Reading time', 'integer', $insight ),
			new FieldDefinition( 'author_image', 'Author image', 'attachment', $insight ),
			new FieldDefinition( 'external_source', 'External source', 'url', $insight ),
			new FieldDefinition( 'source_name', 'Source name', 'string', $insight ),
			new FieldDefinition( 'cta_label', 'CTA label', 'string', $insight ),
			new FieldDefinition( 'cta_url', 'CTA URL', 'url', $insight ),
		) as $field ) {
			$add( $field ); }
		return $fields;
	}

	/** @return array<string, FieldDefinition> */
	public function forEntity( string $entity ): array {
		return array_filter( $this->all(), static fn( FieldDefinition $field ) => in_array( $entity, $field->entities, true ) );
	}
}
