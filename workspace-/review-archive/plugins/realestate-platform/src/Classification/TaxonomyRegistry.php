<?php
/**
 * Canonical taxonomy registration with conflict preservation.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Classification;

final class TaxonomyRegistry {
	/** @return array<string, array{entities:list<string>,hierarchical:bool,label:string}> */
	public function definitions(): array {
		return array(
			'property_type'     => array(
				'entities'     => array( 'property' ),
				'hierarchical' => true,
				'label'        => 'Property Types',
			),
			'property_status'   => array(
				'entities'     => array( 'property' ),
				'hierarchical' => true,
				'label'        => 'Property Statuses',
			),
			'property_category' => array(
				'entities'     => array( 'property' ),
				'hierarchical' => true,
				'label'        => 'Property Categories',
			),
			'property_label'    => array(
				'entities'     => array( 'property' ),
				'hierarchical' => false,
				'label'        => 'Property Labels',
			),
			'property_feature'  => array(
				'entities'     => array( 'property' ),
				'hierarchical' => false,
				'label'        => 'Property Features',
			),
			'property_amenity'  => array(
				'entities'     => array( 'property' ),
				'hierarchical' => false,
				'label'        => 'Property Amenities',
			),
			'location'          => array(
				'entities'     => array( 'property', 'project' ),
				'hierarchical' => true,
				'label'        => 'Locations',
			),
			'project_type'      => array(
				'entities'     => array( 'project' ),
				'hierarchical' => true,
				'label'        => 'Project Types',
			),
			'insight_topic'     => array(
				'entities'     => array( 'insight' ),
				'hierarchical' => true,
				'label'        => 'Insight Topics',
			),
		);
	}

	public function register(): void {
		foreach ( $this->definitions() as $name => $definition ) {
			if ( taxonomy_exists( $name ) ) {
				continue;
			}
			register_taxonomy(
				$name,
				$definition['entities'],
				array(
					'label'             => $definition['label'],
					'public'            => true,
					'hierarchical'      => $definition['hierarchical'],
					'show_in_rest'      => true,
					'show_admin_column' => true,
				)
			);
		}
	}
}
