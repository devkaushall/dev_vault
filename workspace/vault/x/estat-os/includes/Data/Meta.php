<?php
/**
 * Property information schema: one declarative table of every stored field.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Data;

use EstatOS\Settings\Settings;
use EstatOS\Support\Sanitize;
use EstatOS\Support\Vocabulary;

defined( 'ABSPATH' ) || exit;

/**
 * The schema drives storage, validation, the admin editor, CSV columns and the
 * REST representation, so the whole plugin stays consistent by construction.
 *
 * Meta keys are part of the public data contract and must not be renamed.
 */
final class Meta {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 7 );
	}

	/**
	 * Register meta with WordPress so it is protected and typed.
	 *
	 * @return void
	 */
	public static function register_meta(): void {
		foreach ( array( PostTypes::LISTING => self::listing_schema(), PostTypes::PROJECT => self::project_schema(), PostTypes::AGENT => self::agent_schema() ) as $type => $schema ) {
			foreach ( $schema as $key => $field ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'              => in_array( $field['type'], array( 'int', 'bool' ), true ) ? ( 'bool' === $field['type'] ? 'boolean' : 'integer' ) : ( 'float' === $field['type'] ? 'number' : 'string' ),
						'single'            => true,
						'show_in_rest'      => false,
						'sanitize_callback' => static function ( $value ) use ( $field ) {
							return self::sanitize_value( $value, $field );
						},
						'auth_callback'     => static function () {
							return current_user_can( 'estat_manage_listings' );
						},
					)
				);
			}
		}
	}

	/**
	 * Listing field schema.
	 *
	 * Each entry: type, label, help, example, required, chapter, choices.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function listing_schema(): array {
		$s = Settings::all();

		return array(
			// Chapter 1 — the home.
			'_estat_offer'              => array(
				'type'     => 'choice',
				'choices'  => 'offers',
				'default'  => 'sale',
				'label'    => __( 'Is this for sale, rent or lease?', 'estat-os' ),
				'help'     => __( 'Choose what the owner wants to do with this property.', 'estat-os' ),
				'required' => true,
				'chapter'  => 'home',
			),
			'_estat_property_type'      => array(
				'type'     => 'choice',
				'choices'  => 'property_types',
				'default'  => 'apartment',
				'label'    => __( 'What kind of property is it?', 'estat-os' ),
				'help'     => __( 'For example an apartment, a villa or a shop.', 'estat-os' ),
				'required' => true,
				'chapter'  => 'home',
			),
			'_estat_price'              => array(
				'type'    => 'float',
				'label'   => __( 'Price', 'estat-os' ),
				'help'    => __( 'Type only numbers. We will show it nicely on the website.', 'estat-os' ),
				'example' => '8500000',
				'chapter' => 'home',
			),
			'_estat_price_type'         => array(
				'type'    => 'choice',
				'choices' => 'price_types',
				'default' => 'fixed',
				'label'   => __( 'How should the price be shown?', 'estat-os' ),
				'help'    => __( 'Choose "Price on request" to hide the number from visitors.', 'estat-os' ),
				'chapter' => 'home',
			),
			'_estat_rent'               => array(
				'type'    => 'float',
				'label'   => __( 'Monthly rent', 'estat-os' ),
				'help'    => __( 'Only for rent or lease listings.', 'estat-os' ),
				'example' => '35000',
				'chapter' => 'home',
			),
			'_estat_deposit'            => array(
				'type'    => 'float',
				'label'   => __( 'Security deposit', 'estat-os' ),
				'example' => '100000',
				'chapter' => 'home',
			),
			'_estat_maintenance'        => array(
				'type'    => 'float',
				'label'   => __( 'Monthly maintenance', 'estat-os' ),
				'example' => '3000',
				'chapter' => 'home',
			),
			'_estat_area'               => array(
				'type'    => 'float',
				'label'   => __( 'Size of the property', 'estat-os' ),
				'help'    => __( 'Type only the number, then pick the unit.', 'estat-os' ),
				'example' => '1250',
				'chapter' => 'home',
			),
			'_estat_area_unit'          => array(
				'type'    => 'choice',
				'choices' => 'area_units',
				'default' => (string) $s['default_area_unit'],
				'label'   => __( 'Size measured in', 'estat-os' ),
				'chapter' => 'home',
			),
			'_estat_area_type'          => array(
				'type'    => 'choice',
				'choices' => 'area_types',
				'default' => 'builtup',
				'label'   => __( 'Which area is this?', 'estat-os' ),
				'chapter' => 'home',
			),
			'_estat_area_sqft'          => array(
				'type'     => 'float',
				'label'    => __( 'Size in square feet', 'estat-os' ),
				'computed' => true,
				'chapter'  => 'home',
			),
			'_estat_bedrooms'           => array(
				'type'    => 'int',
				'label'   => __( 'Bedrooms', 'estat-os' ),
				'example' => '3',
				'chapter' => 'home',
			),
			'_estat_bathrooms'          => array(
				'type'    => 'int',
				'label'   => __( 'Bathrooms', 'estat-os' ),
				'example' => '2',
				'chapter' => 'home',
			),
			'_estat_balconies'          => array( 'type' => 'int', 'label' => __( 'Balconies', 'estat-os' ), 'chapter' => 'home' ),
			'_estat_parking'            => array( 'type' => 'int', 'label' => __( 'Parking spaces', 'estat-os' ), 'chapter' => 'home' ),
			'_estat_floor'              => array( 'type' => 'int', 'label' => __( 'Which floor', 'estat-os' ), 'chapter' => 'home' ),
			'_estat_total_floors'       => array( 'type' => 'int', 'label' => __( 'Floors in the building', 'estat-os' ), 'chapter' => 'home' ),
			'_estat_age'                => array( 'type' => 'int', 'label' => __( 'Age of the property in years', 'estat-os' ), 'chapter' => 'home' ),

			// Chapter 2 — story and extras.
			'_estat_facing'             => array( 'type' => 'choice', 'choices' => 'facing', 'label' => __( 'Which way does it face?', 'estat-os' ), 'chapter' => 'story' ),
			'_estat_furnishing'         => array( 'type' => 'choice', 'choices' => 'furnishing', 'label' => __( 'Furnishing', 'estat-os' ), 'chapter' => 'story' ),
			'_estat_address'            => array( 'type' => 'textarea', 'label' => __( 'Full address', 'estat-os' ), 'help' => __( 'Only the locality is shown publicly unless you say otherwise.', 'estat-os' ), 'chapter' => 'story' ),
			'_estat_latitude'           => array( 'type' => 'lat', 'label' => __( 'Map latitude', 'estat-os' ), 'example' => '28.6139', 'chapter' => 'story' ),
			'_estat_longitude'          => array( 'type' => 'lng', 'label' => __( 'Map longitude', 'estat-os' ), 'example' => '77.2090', 'chapter' => 'story' ),
			'_estat_gallery'            => array( 'type' => 'ids', 'label' => __( 'Photo gallery', 'estat-os' ), 'chapter' => 'story' ),
			'_estat_floor_plans'        => array( 'type' => 'ids', 'label' => __( 'Floor plans', 'estat-os' ), 'chapter' => 'story' ),
			'_estat_brochures'          => array( 'type' => 'ids', 'label' => __( 'Brochures and documents', 'estat-os' ), 'chapter' => 'story' ),
			'_estat_video_url'          => array( 'type' => 'url', 'label' => __( 'Video link', 'estat-os' ), 'example' => 'https://youtu.be/...', 'chapter' => 'story' ),
			'_estat_tour_url'           => array( 'type' => 'url', 'label' => __( '360° tour link', 'estat-os' ), 'chapter' => 'story' ),

			// Chapter 3 — office information.
			'_estat_availability'       => array( 'type' => 'choice', 'choices' => 'availability', 'default' => 'available', 'label' => __( 'Is it still available?', 'estat-os' ), 'chapter' => 'office' ),
			'_estat_construction'       => array( 'type' => 'choice', 'choices' => 'construction', 'default' => 'ready', 'label' => __( 'Construction stage', 'estat-os' ), 'chapter' => 'office' ),
			'_estat_verification'       => array( 'type' => 'choice', 'choices' => 'verification', 'default' => 'unverified', 'label' => __( 'Have we checked this property?', 'estat-os' ), 'chapter' => 'office' ),
			'_estat_possession_date'    => array( 'type' => 'date', 'label' => __( 'Possession date', 'estat-os' ), 'example' => '2027-06-01', 'chapter' => 'office' ),
			'_estat_project_id'         => array( 'type' => 'int', 'label' => __( 'Society or project', 'estat-os' ), 'chapter' => 'office' ),
			'_estat_agent_id'           => array( 'type' => 'int', 'label' => __( 'Team member in charge', 'estat-os' ), 'chapter' => 'office' ),
			'_estat_agency_id'          => array( 'type' => 'int', 'label' => __( 'Company', 'estat-os' ), 'chapter' => 'office' ),
			'_estat_developer'          => array( 'type' => 'text', 'label' => __( 'Builder / developer name', 'estat-os' ), 'chapter' => 'office' ),
			'_estat_featured'           => array( 'type' => 'bool', 'label' => __( 'Show this on the home page', 'estat-os' ), 'chapter' => 'office' ),
			'_estat_investment'         => array( 'type' => 'bool', 'label' => __( 'Good for investment', 'estat-os' ), 'chapter' => 'office' ),
			'_estat_expiry_date'        => array( 'type' => 'date', 'label' => __( 'Take off the website on', 'estat-os' ), 'help' => __( 'Leave empty to keep it live.', 'estat-os' ), 'chapter' => 'office' ),
			'_estat_regulatory_id'      => array( 'type' => 'text', 'label' => __( 'Registration number', 'estat-os' ), 'help' => __( 'The official registration or approval number, if any.', 'estat-os' ), 'chapter' => 'office' ),
			'_estat_internal_notes'     => array( 'type' => 'textarea', 'label' => __( 'Private office notes', 'estat-os' ), 'help' => __( 'Only your office can see this. Never shown on the website.', 'estat-os' ), 'private' => true, 'chapter' => 'office' ),
			'_estat_external_id'        => array( 'type' => 'text', 'label' => __( 'Your own reference number', 'estat-os' ), 'help' => __( 'Used to avoid duplicates when importing spreadsheets.', 'estat-os' ), 'chapter' => 'office' ),
			'_estat_completeness'       => array( 'type' => 'int', 'label' => __( 'Readiness score', 'estat-os' ), 'computed' => true, 'chapter' => 'office' ),
			'_estat_practice'           => array( 'type' => 'bool', 'label' => __( 'Practice record', 'estat-os' ), 'computed' => true, 'chapter' => 'office' ),
			'_estat_assigned_user'      => array( 'type' => 'int', 'label' => __( 'WordPress user in charge', 'estat-os' ), 'chapter' => 'office' ),
		);
	}

	/**
	 * Project field schema.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function project_schema(): array {
		return array(
			'_estat_developer'       => array( 'type' => 'text', 'label' => __( 'Builder / developer', 'estat-os' ) ),
			'_estat_project_status'  => array( 'type' => 'choice', 'choices' => 'project_statuses', 'default' => 'construction', 'label' => __( 'Stage', 'estat-os' ) ),
			'_estat_possession_date' => array( 'type' => 'date', 'label' => __( 'Possession date', 'estat-os' ) ),
			'_estat_price_min'       => array( 'type' => 'float', 'label' => __( 'Lowest price', 'estat-os' ) ),
			'_estat_price_max'       => array( 'type' => 'float', 'label' => __( 'Highest price', 'estat-os' ) ),
			'_estat_total_units'     => array( 'type' => 'int', 'label' => __( 'Total units', 'estat-os' ) ),
			'_estat_available_units' => array( 'type' => 'int', 'label' => __( 'Units still available', 'estat-os' ) ),
			'_estat_unit_types'      => array( 'type' => 'text', 'label' => __( 'Unit types', 'estat-os' ), 'example' => '2 BHK, 3 BHK' ),
			'_estat_gallery'         => array( 'type' => 'ids', 'label' => __( 'Photo gallery', 'estat-os' ) ),
			'_estat_floor_plans'     => array( 'type' => 'ids', 'label' => __( 'Floor plans', 'estat-os' ) ),
			'_estat_brochures'       => array( 'type' => 'ids', 'label' => __( 'Brochures', 'estat-os' ) ),
			'_estat_highlights'      => array( 'type' => 'textarea', 'label' => __( 'Highlights', 'estat-os' ), 'help' => __( 'One point per line.', 'estat-os' ) ),
			'_estat_regulatory_id'   => array( 'type' => 'text', 'label' => __( 'Registration number', 'estat-os' ) ),
			'_estat_latitude'        => array( 'type' => 'lat', 'label' => __( 'Map latitude', 'estat-os' ) ),
			'_estat_longitude'       => array( 'type' => 'lng', 'label' => __( 'Map longitude', 'estat-os' ) ),
			'_estat_agency_id'       => array( 'type' => 'int', 'label' => __( 'Company', 'estat-os' ) ),
			'_estat_external_id'     => array( 'type' => 'text', 'label' => __( 'Your own reference number', 'estat-os' ) ),
			'_estat_practice'        => array( 'type' => 'bool', 'label' => __( 'Practice record', 'estat-os' ), 'computed' => true ),
		);
	}

	/**
	 * Team member field schema.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function agent_schema(): array {
		return array(
			'_estat_role'        => array( 'type' => 'text', 'label' => __( 'Role in the office', 'estat-os' ), 'example' => 'Sales Manager' ),
			'_estat_phone'       => array( 'type' => 'phone', 'label' => __( 'Phone number', 'estat-os' ) ),
			'_estat_whatsapp'    => array( 'type' => 'phone', 'label' => __( 'WhatsApp number', 'estat-os' ) ),
			'_estat_email'       => array( 'type' => 'email', 'label' => __( 'Email address', 'estat-os' ) ),
			'_estat_agency_id'   => array( 'type' => 'int', 'label' => __( 'Company', 'estat-os' ) ),
			'_estat_user_id'     => array( 'type' => 'int', 'label' => __( 'Login account', 'estat-os' ), 'help' => __( 'Link this person to a website login so they can sign in.', 'estat-os' ) ),
			'_estat_external_id' => array( 'type' => 'text', 'label' => __( 'Your own reference number', 'estat-os' ) ),
			'_estat_practice'    => array( 'type' => 'bool', 'label' => __( 'Practice record', 'estat-os' ), 'computed' => true ),
		);
	}

	/**
	 * Schema for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return array<string,array<string,mixed>>
	 */
	public static function schema_for( string $post_type ): array {
		switch ( $post_type ) {
			case PostTypes::LISTING:
				return self::listing_schema();
			case PostTypes::PROJECT:
				return self::project_schema();
			case PostTypes::AGENT:
				return self::agent_schema();
			default:
				return array();
		}
	}

	/**
	 * Sanitize one value against its field definition.
	 *
	 * @param mixed                $value Raw value.
	 * @param array<string,mixed>  $field Field definition.
	 * @return mixed
	 */
	public static function sanitize_value( $value, array $field ) {
		$type = (string) ( $field['type'] ?? 'text' );
		switch ( $type ) {
			case 'int':
				return Sanitize::int( $value, 0, 100000000 );
			case 'float':
				return Sanitize::float( $value );
			case 'bool':
				return Sanitize::bool( $value ) ? 1 : 0;
			case 'textarea':
				return Sanitize::textarea( $value );
			case 'html':
				return Sanitize::html( $value );
			case 'email':
				return Sanitize::email( $value );
			case 'phone':
				return Sanitize::phone( $value );
			case 'url':
				return Sanitize::url( $value );
			case 'date':
				return Sanitize::date( $value );
			case 'lat':
				return Sanitize::latitude( $value );
			case 'lng':
				return Sanitize::longitude( $value );
			case 'ids':
				return Sanitize::id_list( $value );
			case 'choice':
				$vocab   = (string) ( $field['choices'] ?? '' );
				$allowed = Vocabulary::keys( $vocab );
				return Sanitize::choice( $value, $allowed, (string) ( $field['default'] ?? ( $allowed[0] ?? '' ) ) );
			default:
				return Sanitize::text( $value );
		}
	}
}
