<?php
/** Stable public Elementor dynamic-tag catalog. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Elementor;

final class TagCatalog {
	/** @return list<array{id:string,title:string,entity:string,field:string,type:string}> */
	public static function definitions(): array {
		$definitions = array();
		$add         = static function ( string $id, string $title, string $entity, string $field, string $type = 'text' ) use ( &$definitions ): void {
			$definitions[] = array(
				'id'     => $id,
				'title'  => $title,
				'entity' => $entity,
				'field'  => $field,
				'type'   => $type,
			);
		};

		foreach ( array(
			array( 'property', 'Property title', 'title', 'text' ),
			array( 'property', 'Property price', 'price', 'text' ),
			array( 'property', 'Property currency', 'currency', 'text' ),
			array( 'property', 'Property price period', 'price_period', 'text' ),
			array( 'property', 'Property area', 'area', 'text' ),
			array( 'property', 'Property area unit', 'area_unit', 'text' ),
			array( 'property', 'Property bedrooms', 'bedrooms', 'text' ),
			array( 'property', 'Property bathrooms', 'bathrooms', 'text' ),
			array( 'property', 'Property address', 'address', 'text' ),
			array( 'property', 'Property city', 'city', 'text' ),
			array( 'property', 'Property state', 'state', 'text' ),
			array( 'property', 'Property country', 'country', 'text' ),
			array( 'property', 'Property postal code', 'postal_code', 'text' ),
			array( 'property', 'Property type', 'property_type', 'text' ),
			array( 'property', 'Property status', 'property_status', 'text' ),
			array( 'property', 'Property location', 'location', 'text' ),
			array( 'property', 'Property agent', 'agent', 'text' ),
			array( 'property', 'Property agency', 'agency', 'text' ),
			array( 'property', 'Property latitude', 'latitude', 'text' ),
			array( 'property', 'Property longitude', 'longitude', 'text' ),
			array( 'property', 'Property featured image', 'featured_image', 'url' ),
			array( 'property', 'Property brochure', 'brochure', 'url' ),
			array( 'property', 'Property virtual tour', 'virtual_tour', 'url' ),
			array( 'property', 'Property video', 'video', 'url' ),
			array( 'project', 'Project title', 'title', 'text' ),
			array( 'project', 'Project type', 'project_type', 'text' ),
			array( 'project', 'Project location', 'location', 'text' ),
			array( 'project', 'Project status', 'status', 'text' ),
			array( 'project', 'Project price', 'price', 'text' ),
			array( 'project', 'Project currency', 'currency', 'text' ),
			array( 'project', 'Project address', 'address', 'text' ),
			array( 'project', 'Project city', 'city', 'text' ),
			array( 'project', 'Project state', 'state', 'text' ),
			array( 'project', 'Project country', 'country', 'text' ),
			array( 'project', 'Project developer', 'developer', 'text' ),
			array( 'project', 'Project RERA', 'rera', 'text' ),
			array( 'project', 'Project featured image', 'featured_image', 'url' ),
			array( 'project', 'Project brochure', 'brochure', 'url' ),
			array( 'agent', 'Agent name', 'title', 'text' ),
			array( 'agent', 'Agent avatar', 'avatar', 'url' ),
			array( 'agent', 'Agent phone', 'public_phone', 'text' ),
			array( 'agent', 'Agent email', 'public_email', 'text' ),
			array( 'agent', 'Agent website', 'website', 'url' ),
			array( 'agent', 'Agent agency', 'agency', 'text' ),
			array( 'agency', 'Agency name', 'title', 'text' ),
			array( 'agency', 'Agency logo', 'avatar', 'url' ),
			array( 'agency', 'Agency phone', 'public_phone', 'text' ),
			array( 'agency', 'Agency email', 'public_email', 'text' ),
			array( 'agency', 'Agency website', 'website', 'url' ),
			array( 'agency', 'Agency office address', 'office_address', 'text' ),
			array( 'agency', 'Agency license number', 'license_number', 'text' ),
			array( 'insight', 'Insight title', 'title', 'text' ),
			array( 'insight', 'Insight topic', 'insight_topic', 'text' ),
			array( 'insight', 'Insight subtitle', 'subtitle', 'text' ),
			array( 'insight', 'Insight reading time', 'reading_time', 'text' ),
			array( 'insight', 'Insight source name', 'source_name', 'text' ),
			array( 'insight', 'Insight external source', 'external_source', 'url' ),
			array( 'insight', 'Insight call to action label', 'cta_label', 'text' ),
			array( 'insight', 'Insight call to action URL', 'cta_url', 'url' ),
			array( 'insight', 'Insight featured image', 'featured_image', 'url' ),
			array( 'insight', 'Insight status', 'status', 'text' ),
		) as $definition ) {
			$id = 'rep_' . $definition[0] . '_' . $definition[2];
			if ( in_array( $definition[2], array( 'property_type', 'property_status', 'project_type', 'insight_topic' ), true ) ) {
				$id = 'rep_' . $definition[2];
			}
			$add( $id, $definition[1], $definition[0], $definition[2], $definition[3] );
		}
		return $definitions;
	}
}
