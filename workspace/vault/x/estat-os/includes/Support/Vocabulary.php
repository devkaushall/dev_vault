<?php
/**
 * Canonical business vocabularies (status models, types, units).
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for every controlled list in the system.
 *
 * Keys are stable data-contract values and must not be renamed.
 * Labels are translated at call time, never stored.
 */
final class Vocabulary {

	/**
	 * Property types.
	 *
	 * @return array<string,string>
	 */
	public static function property_types(): array {
		return array(
			'apartment'         => __( 'Apartment', 'estat-os' ),
			'flat'              => __( 'Flat', 'estat-os' ),
			'villa'             => __( 'Villa', 'estat-os' ),
			'independent_house' => __( 'Independent House', 'estat-os' ),
			'builder_floor'     => __( 'Builder Floor', 'estat-os' ),
			'plot'              => __( 'Plot', 'estat-os' ),
			'commercial_shop'   => __( 'Commercial Shop', 'estat-os' ),
			'office'            => __( 'Office', 'estat-os' ),
			'warehouse'         => __( 'Warehouse', 'estat-os' ),
			'industrial'        => __( 'Industrial Property', 'estat-os' ),
			'farm_land'         => __( 'Farm Land', 'estat-os' ),
			'studio'            => __( 'Studio', 'estat-os' ),
			'penthouse'         => __( 'Penthouse', 'estat-os' ),
			'other'             => __( 'Other', 'estat-os' ),
		);
	}

	/**
	 * Offer types (what the office is doing with the property).
	 *
	 * @return array<string,string>
	 */
	public static function offers(): array {
		return array(
			'sale'  => __( 'For Sale', 'estat-os' ),
			'rent'  => __( 'For Rent', 'estat-os' ),
			'lease' => __( 'For Lease', 'estat-os' ),
		);
	}

	/**
	 * Availability states.
	 *
	 * @return array<string,string>
	 */
	public static function availability(): array {
		return array(
			'available' => __( 'Available', 'estat-os' ),
			'on_hold'   => __( 'On Hold / Under Offer', 'estat-os' ),
			'sold'      => __( 'Sold', 'estat-os' ),
			'rented'    => __( 'Rented', 'estat-os' ),
		);
	}

	/**
	 * Construction states.
	 *
	 * @return array<string,string>
	 */
	public static function construction(): array {
		return array(
			'ready'        => __( 'Ready to Move', 'estat-os' ),
			'construction' => __( 'Under Construction', 'estat-os' ),
			'new_launch'   => __( 'New Launch', 'estat-os' ),
		);
	}

	/**
	 * Verification states.
	 *
	 * @return array<string,string>
	 */
	public static function verification(): array {
		return array(
			'unverified'      => __( 'Not checked yet', 'estat-os' ),
			'self_verified'   => __( 'Checked by the owner/agent', 'estat-os' ),
			'office_verified' => __( 'Verified by our office', 'estat-os' ),
		);
	}

	/**
	 * Price types.
	 *
	 * @return array<string,string>
	 */
	public static function price_types(): array {
		return array(
			'fixed'         => __( 'Fixed price', 'estat-os' ),
			'negotiable'    => __( 'Negotiable', 'estat-os' ),
			'starting_from' => __( 'Starting from', 'estat-os' ),
			'on_request'    => __( 'Price on request', 'estat-os' ),
		);
	}

	/**
	 * Area measurement types.
	 *
	 * @return array<string,string>
	 */
	public static function area_types(): array {
		return array(
			'carpet'  => __( 'Carpet area', 'estat-os' ),
			'builtup' => __( 'Built-up area', 'estat-os' ),
			'super'   => __( 'Super built-up area', 'estat-os' ),
			'plot'    => __( 'Plot area', 'estat-os' ),
		);
	}

	/**
	 * Area units.
	 *
	 * @return array<string,string>
	 */
	public static function area_units(): array {
		return array(
			'sqft' => __( 'Square feet', 'estat-os' ),
			'sqyd' => __( 'Square yards', 'estat-os' ),
			'sqm'  => __( 'Square metres', 'estat-os' ),
		);
	}

	/**
	 * Furnishing options.
	 *
	 * @return array<string,string>
	 */
	public static function furnishing(): array {
		return array(
			''             => __( 'Not specified', 'estat-os' ),
			'unfurnished'  => __( 'Unfurnished', 'estat-os' ),
			'semi'         => __( 'Semi furnished', 'estat-os' ),
			'furnished'    => __( 'Fully furnished', 'estat-os' ),
		);
	}

	/**
	 * Facing directions.
	 *
	 * @return array<string,string>
	 */
	public static function facing(): array {
		return array(
			''           => __( 'Not specified', 'estat-os' ),
			'north'      => __( 'North', 'estat-os' ),
			'south'      => __( 'South', 'estat-os' ),
			'east'       => __( 'East', 'estat-os' ),
			'west'       => __( 'West', 'estat-os' ),
			'north_east' => __( 'North East', 'estat-os' ),
			'north_west' => __( 'North West', 'estat-os' ),
			'south_east' => __( 'South East', 'estat-os' ),
			'south_west' => __( 'South West', 'estat-os' ),
		);
	}

	/**
	 * Lead statuses.
	 *
	 * @return array<string,string>
	 */
	public static function lead_statuses(): array {
		return array(
			'new'             => __( 'New', 'estat-os' ),
			'contacted'       => __( 'Contacted', 'estat-os' ),
			'qualified'       => __( 'Qualified', 'estat-os' ),
			'visit_scheduled' => __( 'Visit scheduled', 'estat-os' ),
			'converted'       => __( 'Converted', 'estat-os' ),
			'lost'            => __( 'Lost', 'estat-os' ),
		);
	}

	/**
	 * Visit outcomes.
	 *
	 * @return array<string,string>
	 */
	public static function visit_outcomes(): array {
		return array(
			'pending'   => __( 'Pending', 'estat-os' ),
			'done'      => __( 'Done', 'estat-os' ),
			'no_show'   => __( 'No-show', 'estat-os' ),
			'cancelled' => __( 'Cancelled', 'estat-os' ),
		);
	}

	/**
	 * Project statuses.
	 *
	 * @return array<string,string>
	 */
	public static function project_statuses(): array {
		return self::construction() + array( 'completed' => __( 'Completed', 'estat-os' ) );
	}

	/**
	 * Helper: the keys of a vocabulary.
	 *
	 * @param string $name Vocabulary method name.
	 * @return string[]
	 */
	public static function keys( string $name ): array {
		if ( ! method_exists( __CLASS__, $name ) ) {
			return array();
		}
		/** @var array<string,string> $list */
		$list = call_user_func( array( __CLASS__, $name ) );
		return array_map( 'strval', array_keys( $list ) );
	}

	/**
	 * Helper: label for a key.
	 *
	 * @param string $name Vocabulary method name.
	 * @param string $key  Key.
	 * @return string
	 */
	public static function label( string $name, string $key ): string {
		if ( ! method_exists( __CLASS__, $name ) ) {
			return $key;
		}
		/** @var array<string,string> $list */
		$list = call_user_func( array( __CLASS__, $name ) );
		return isset( $list[ $key ] ) ? (string) $list[ $key ] : $key;
	}
}
