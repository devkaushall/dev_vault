<?php
/**
 * Property highlights: the small tick-boxes a seller checks, which then appear
 * as chips on the property card.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Data;

use EstatOS\Support\Format;
use EstatOS\Support\Vocabulary;

defined( 'ABSPATH' ) || exit;

/**
 * One place that decides what a property can be highlighted for, and which of
 * those highlights are worth showing on a card.
 *
 * Two kinds of highlight exist:
 *
 * 1. Tick highlights   - the seller checks a box ("Corner plot", "Park facing").
 * 2. Automatic facts   - derived from data already entered (bedrooms, area,
 *                        parking, furnishing, possession).
 *
 * The card shows a small number of the strongest ones so it stays readable.
 */
final class Highlights {

	/**
	 * Meta key holding the ticked highlight slugs.
	 */
	public const META_KEY = '_estat_highlights';

	/**
	 * How many chips a card shows before it stops.
	 */
	public const CARD_LIMIT = 3;

	/**
	 * Every highlight a seller can tick, grouped for the editor.
	 *
	 * `icon` is a plain character so no icon font is needed anywhere.
	 * `priority` decides which chips win the limited space on a card: lower
	 * numbers are shown first.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		$items = array(

			// Position and outlook: what buyers ask about first.
			'corner_plot'    => array( 'label' => __( 'Corner plot', 'estat-os' ),        'group' => 'position', 'icon' => 'corner', 'priority' => 10 ),
			'park_facing'    => array( 'label' => __( 'Park facing', 'estat-os' ),        'group' => 'position', 'icon' => 'tree', 'priority' => 10 ),
			'main_road'      => array( 'label' => __( 'Main road', 'estat-os' ),          'group' => 'position', 'icon' => 'road', 'priority' => 20 ),
			'corner_shop'    => array( 'label' => __( 'Corner shop', 'estat-os' ),        'group' => 'position', 'icon' => 'shop', 'priority' => 30 ),
			'gated_society'  => array( 'label' => __( 'Gated society', 'estat-os' ),      'group' => 'position', 'icon' => 'lock', 'priority' => 15 ),
			'east_facing'    => array( 'label' => __( 'East facing', 'estat-os' ),        'group' => 'position', 'icon' => 'sun', 'priority' => 25 ),
			'north_facing'   => array( 'label' => __( 'North facing', 'estat-os' ),       'group' => 'position', 'icon' => 'compass', 'priority' => 25 ),

			// Condition and readiness.
			'ready_to_move'  => array( 'label' => __( 'Ready to move', 'estat-os' ),      'group' => 'condition', 'icon' => 'key', 'priority' => 5 ),
			'newly_built'    => array( 'label' => __( 'Newly built', 'estat-os' ),        'group' => 'condition', 'icon' => 'sparkle', 'priority' => 15 ),
			'newly_painted'  => array( 'label' => __( 'Newly painted', 'estat-os' ),      'group' => 'condition', 'icon' => 'brush', 'priority' => 40 ),
			'vastu'          => array( 'label' => __( 'Vastu compliant', 'estat-os' ),    'group' => 'condition', 'icon' => 'lotus', 'priority' => 20 ),

			// Paperwork: the things that close a deal.
			'clear_title'    => array( 'label' => __( 'Clear title', 'estat-os' ),        'group' => 'paperwork', 'icon' => 'certificate', 'priority' => 10 ),
			'loan_approved'  => array( 'label' => __( 'Loan approved', 'estat-os' ),      'group' => 'paperwork', 'icon' => 'bank', 'priority' => 15 ),
			'rera_approved'  => array( 'label' => __( 'RERA approved', 'estat-os' ),      'group' => 'paperwork', 'icon' => 'shield', 'priority' => 15 ),
			'no_dues'        => array( 'label' => __( 'No dues', 'estat-os' ),            'group' => 'paperwork', 'icon' => 'receipt', 'priority' => 35 ),

			// Comfort and amenities people search for.
			'lift'           => array( 'label' => __( 'Lift', 'estat-os' ),               'group' => 'comfort', 'icon' => 'lift', 'priority' => 30 ),
			'power_backup'   => array( 'label' => __( 'Power backup', 'estat-os' ),       'group' => 'comfort', 'icon' => 'battery', 'priority' => 25 ),
			'security'       => array( 'label' => __( '24x7 security', 'estat-os' ),      'group' => 'comfort', 'icon' => 'guard', 'priority' => 25 ),
			'water_247'      => array( 'label' => __( '24x7 water', 'estat-os' ),         'group' => 'comfort', 'icon' => 'drop', 'priority' => 30 ),
			'modular_kitchen'=> array( 'label' => __( 'Modular kitchen', 'estat-os' ),    'group' => 'comfort', 'icon' => 'kitchen', 'priority' => 35 ),
			'balcony'        => array( 'label' => __( 'Balcony', 'estat-os' ),            'group' => 'comfort', 'icon' => 'balcony', 'priority' => 35 ),
			'garden'         => array( 'label' => __( 'Private garden', 'estat-os' ),     'group' => 'comfort', 'icon' => 'leaf', 'priority' => 30 ),
			'swimming_pool'  => array( 'label' => __( 'Swimming pool', 'estat-os' ),      'group' => 'comfort', 'icon' => 'pool', 'priority' => 30 ),
			'gym'            => array( 'label' => __( 'Gym', 'estat-os' ),                'group' => 'comfort', 'icon' => 'gym', 'priority' => 35 ),
			'club_house'     => array( 'label' => __( 'Club house', 'estat-os' ),         'group' => 'comfort', 'icon' => 'columns', 'priority' => 35 ),

			// Nearby: distance sells.
			'near_metro'     => array( 'label' => __( 'Near metro', 'estat-os' ),         'group' => 'nearby', 'icon' => 'metro', 'priority' => 10 ),
			'near_school'    => array( 'label' => __( 'Near school', 'estat-os' ),        'group' => 'nearby', 'icon' => 'school', 'priority' => 20 ),
			'near_hospital'  => array( 'label' => __( 'Near hospital', 'estat-os' ),      'group' => 'nearby', 'icon' => 'hospital', 'priority' => 25 ),
			'near_market'    => array( 'label' => __( 'Near market', 'estat-os' ),        'group' => 'nearby', 'icon' => 'cart', 'priority' => 25 ),
			'near_airport'   => array( 'label' => __( 'Near airport', 'estat-os' ),       'group' => 'nearby', 'icon' => 'plane', 'priority' => 30 ),

			// Money angle.
			'good_rental'    => array( 'label' => __( 'Good rental yield', 'estat-os' ),  'group' => 'money', 'icon' => 'trend-up', 'priority' => 20 ),
			'price_negotiable' => array( 'label' => __( 'Price negotiable', 'estat-os' ), 'group' => 'money', 'icon' => 'handshake', 'priority' => 20 ),
			'no_brokerage'   => array( 'label' => __( 'No brokerage', 'estat-os' ),       'group' => 'money', 'icon' => 'no-fee', 'priority' => 15 ),
		);

		/**
		 * Filter the highlight catalogue.
		 *
		 * @param array<string,array<string,mixed>> $items Highlights.
		 */
		return (array) apply_filters( 'estat_highlights', $items );
	}

	/**
	 * Group labels for the editor.
	 *
	 * @return array<string,string>
	 */
	public static function groups(): array {
		return array(
			'position'  => __( 'Position & outlook', 'estat-os' ),
			'condition' => __( 'Condition', 'estat-os' ),
			'paperwork' => __( 'Paperwork', 'estat-os' ),
			'comfort'   => __( 'Comfort & amenities', 'estat-os' ),
			'nearby'    => __( "What's nearby", 'estat-os' ),
			'money'     => __( 'Money', 'estat-os' ),
		);
	}

	/**
	 * The catalogue arranged by group, for rendering the editor.
	 *
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public static function by_group(): array {
		$out = array();
		foreach ( self::groups() as $group => $label ) {
			$out[ $group ] = array();
		}
		foreach ( self::all() as $key => $item ) {
			$group = (string) ( $item['group'] ?? 'comfort' );
			if ( ! isset( $out[ $group ] ) ) {
				$out[ $group ] = array();
			}
			$out[ $group ][ $key ] = $item;
		}
		return array_filter( $out );
	}

	/**
	 * Keep only slugs that exist in the catalogue.
	 *
	 * @param mixed $value Raw input.
	 * @return string[]
	 */
	public static function sanitize( $value ): array {
		$known = self::all();
		$out   = array();
		foreach ( (array) $value as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( isset( $known[ $slug ] ) && ! in_array( $slug, $out, true ) ) {
				$out[] = $slug;
			}
		}
		return $out;
	}

	/**
	 * The slugs ticked on a listing.
	 *
	 * @param int $listing_id Listing ID.
	 * @return string[]
	 */
	public static function get( int $listing_id ): array {
		$stored = get_post_meta( $listing_id, self::META_KEY, true );
		return self::sanitize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Save the ticked slugs.
	 *
	 * @param int   $listing_id Listing ID.
	 * @param mixed $value      Slugs.
	 * @return void
	 */
	public static function save( int $listing_id, $value ): void {
		$clean = self::sanitize( $value );
		if ( $clean ) {
			update_post_meta( $listing_id, self::META_KEY, $clean );
		} else {
			delete_post_meta( $listing_id, self::META_KEY );
		}
	}

	/**
	 * Facts worth a chip that we can work out on our own, so a card still has
	 * something useful even when nothing was ticked.
	 *
	 * @param int $listing_id Listing ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function automatic( int $listing_id ): array {
		$out = array();

		$beds = (int) get_post_meta( $listing_id, '_estat_bedrooms', true );
		if ( $beds > 0 ) {
			$out[] = array(
				'key'      => 'beds',
				/* translators: %d: number of bedrooms. */
				'label'    => sprintf( _n( '%d Bed', '%d Beds', $beds, 'estat-os' ), $beds ),
				'icon'     => "\u{1F6CF}",
				'priority' => 1,
			);
		}

		$baths = (int) get_post_meta( $listing_id, '_estat_bathrooms', true );
		if ( $baths > 0 ) {
			$out[] = array(
				'key'      => 'baths',
				/* translators: %d: number of bathrooms. */
				'label'    => sprintf( _n( '%d Bath', '%d Baths', $baths, 'estat-os' ), $baths ),
				'icon'     => "\u{1F6C1}",
				'priority' => 2,
			);
		}

		$area = (float) get_post_meta( $listing_id, '_estat_area_sqft', true );
		if ( $area > 0 ) {
			$out[] = array(
				'key'      => 'area',
				'label'    => Format::area( $area, 'sqft' ),
				'icon'     => "\u{1F4D0}",
				'priority' => 3,
			);
		}

		$parking = (int) get_post_meta( $listing_id, '_estat_parking', true );
		if ( $parking > 0 ) {
			$out[] = array(
				'key'      => 'parking',
				/* translators: %d: number of parking spaces. */
				'label'    => sprintf( _n( '%d Parking', '%d Parking', $parking, 'estat-os' ), $parking ),
				'icon'     => "\u{1F697}",
				'priority' => 4,
			);
		}

		$furnishing = (string) get_post_meta( $listing_id, '_estat_furnishing', true );
		if ( '' !== $furnishing && 'unfurnished' !== $furnishing ) {
			$out[] = array(
				'key'      => 'furnishing',
				'label'    => Vocabulary::label( 'furnishing', $furnishing ),
				'icon'     => "\u{1F6CB}",
				'priority' => 6,
			);
		}

		return $out;
	}

	/**
	 * Everything worth showing for a listing, ticked plus automatic, already
	 * sorted so the most useful chips come first.
	 *
	 * @param int $listing_id Listing ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function for_listing( int $listing_id ): array {
		$catalogue = self::all();
		$chips     = self::automatic( $listing_id );

		foreach ( self::get( $listing_id ) as $slug ) {
			$item    = $catalogue[ $slug ];
			$chips[] = array(
				'key'      => $slug,
				'label'    => (string) $item['label'],
				'icon'     => (string) $item['icon'],
				'priority' => (int) ( $item['priority'] ?? 50 ),
			);
		}

		usort(
			$chips,
			static function ( array $a, array $b ): int {
				return ( (int) $a['priority'] ) <=> ( (int) $b['priority'] );
			}
		);

		/**
		 * Filter the chips shown for a listing.
		 *
		 * @param array<int,array<string,mixed>> $chips      Chips.
		 * @param int                            $listing_id Listing ID.
		 */
		return (array) apply_filters( 'estat_listing_highlights', $chips, $listing_id );
	}

	/**
	 * The few chips a card has room for.
	 *
	 * @param int $listing_id Listing ID.
	 * @param int $limit      How many.
	 * @return array<int,array<string,mixed>>
	 */
	public static function for_card( int $listing_id, int $limit = self::CARD_LIMIT ): array {
		$limit = max( 1, $limit );
		$all   = self::for_listing( $listing_id );

		// What the seller actually ticked is the interesting part, so it is
		// never crowded out by bedroom and area counts that the card shows
		// elsewhere anyway. Ticked chips fill the row first, then automatic
		// facts top it up if there is room left.
		$catalogue = self::all();
		$ticked    = array();
		$automatic = array();
		foreach ( $all as $chip ) {
			if ( isset( $catalogue[ (string) $chip['key'] ] ) ) {
				$ticked[] = $chip;
			} else {
				$automatic[] = $chip;
			}
		}

		return array_slice( array_merge( $ticked, $automatic ), 0, $limit );
	}
}
