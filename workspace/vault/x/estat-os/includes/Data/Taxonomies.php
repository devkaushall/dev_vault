<?php
/**
 * Classification lists (localities, features, amenities, categories).
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Data;

defined( 'ABSPATH' ) || exit;

/**
 * These are presented to office users as simple lists, never as "taxonomies".
 */
final class Taxonomies {

	/**
	 * Locality taxonomy key.
	 */
	public const LOCALITY = 'estat_locality';

	/**
	 * Feature taxonomy key.
	 */
	public const FEATURE = 'estat_feature';

	/**
	 * Amenity taxonomy key.
	 */
	public const AMENITY = 'estat_amenity';

	/**
	 * Which of the three kinds an insight is: blog, article or insight.
	 */
	public const INSIGHT_KIND = 'estat_insight_kind';

	/**
	 * Subject categories for insights, for example "Market Trends".
	 */
	public const INSIGHT_TOPIC = 'estat_insight_topic';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 6 );
	}

	/**
	 * Register taxonomies.
	 *
	 * @return void
	 */
	public static function register_taxonomies(): void {
		register_taxonomy(
			self::LOCALITY,
			array( PostTypes::LISTING, PostTypes::PROJECT ),
			array(
				'labels'            => self::labels( __( 'Locality', 'estat-os' ), __( 'Localities', 'estat-os' ) ),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'locality', 'with_front' => false ),
				'capabilities'      => self::caps( 'estat_manage_listings' ),
			)
		);

		register_taxonomy(
			self::FEATURE,
			array( PostTypes::LISTING ),
			array(
				'labels'       => self::labels( __( 'Feature', 'estat-os' ), __( 'Features', 'estat-os' ) ),
				'hierarchical' => false,
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => false,
				'rewrite'      => array( 'slug' => 'feature', 'with_front' => false ),
				'capabilities' => self::caps( 'estat_manage_listings' ),
			)
		);

		register_taxonomy(
			self::AMENITY,
			array( PostTypes::LISTING, PostTypes::PROJECT ),
			array(
				'labels'       => self::labels( __( 'Amenity', 'estat-os' ), __( 'Amenities', 'estat-os' ) ),
				'hierarchical' => false,
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => false,
				'rewrite'      => array( 'slug' => 'amenity', 'with_front' => false ),
				'capabilities' => self::caps( 'estat_manage_listings' ),
			)
		);

		register_taxonomy(
			self::INSIGHT_KIND,
			array( PostTypes::INSIGHT ),
			array(
				'labels'            => self::labels( __( 'Kind', 'estat-os' ), __( 'Kinds', 'estat-os' ) ),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'insight-kind', 'with_front' => false ),
				'capabilities'      => self::caps( 'estat_manage_insights' ),
			)
		);

		register_taxonomy(
			self::INSIGHT_TOPIC,
			array( PostTypes::INSIGHT ),
			array(
				'labels'            => self::labels( __( 'Topic', 'estat-os' ), __( 'Topics', 'estat-os' ) ),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'topic', 'with_front' => false ),
				'capabilities'      => self::caps( 'estat_manage_insights' ),
			)
		);

		self::seed_insight_kinds();
	}

	/**
	 * Make sure the three kinds always exist, so the Insights screen has its
	 * tabs even on a brand new site.
	 *
	 * @return void
	 */
	public static function seed_insight_kinds(): void {
		foreach ( self::insight_kinds() as $slug => $label ) {
			if ( ! term_exists( $slug, self::INSIGHT_KIND ) ) {
				wp_insert_term( $label, self::INSIGHT_KIND, array( 'slug' => $slug ) );
			}
		}
	}

	/**
	 * The three kinds an insight can be.
	 *
	 * @return array<string,string>
	 */
	public static function insight_kinds(): array {
		return array(
			'blog'    => __( 'Blog', 'estat-os' ),
			'article' => __( 'Article', 'estat-os' ),
			'insight' => __( 'Insight', 'estat-os' ),
		);
	}

	/**
	 * Build taxonomy labels.
	 *
	 * @param string $singular Singular.
	 * @param string $plural   Plural.
	 * @return array<string,string>
	 */
	private static function labels( string $singular, string $plural ): array {
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			'add_new_item'  => sprintf( /* translators: %s: item */ __( 'Add %s', 'estat-os' ), $singular ),
			'edit_item'     => sprintf( /* translators: %s: item */ __( 'Edit %s', 'estat-os' ), $singular ),
			'search_items'  => $plural,
			'all_items'     => $plural,
			'menu_name'     => $plural,
			'not_found'     => __( 'Nothing added yet.', 'estat-os' ),
		);
	}

	/**
	 * Capability map for a taxonomy.
	 *
	 * @param string $cap Capability.
	 * @return array<string,string>
	 */
	private static function caps( string $cap ): array {
		return array(
			'manage_terms' => $cap,
			'edit_terms'   => $cap,
			'delete_terms' => $cap,
			'assign_terms' => $cap,
		);
	}

	/**
	 * Seed a small set of neutral, non company specific starter amenities.
	 * Idempotent: existing terms are never duplicated.
	 *
	 * @return void
	 */
	public static function seed(): void {
		$amenities = array(
			__( 'Lift', 'estat-os' ),
			__( 'Power backup', 'estat-os' ),
			__( 'Security', 'estat-os' ),
			__( 'Parking', 'estat-os' ),
			__( 'Park', 'estat-os' ),
			__( 'Gym', 'estat-os' ),
			__( 'Swimming pool', 'estat-os' ),
			__( 'Clubhouse', 'estat-os' ),
			__( 'Water supply', 'estat-os' ),
			__( 'Gated community', 'estat-os' ),
		);
		foreach ( $amenities as $name ) {
			if ( ! term_exists( $name, self::AMENITY ) ) {
				wp_insert_term( $name, self::AMENITY );
			}
		}
	}

	/**
	 * All taxonomies owned by the plugin.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::LOCALITY, self::FEATURE, self::AMENITY );
	}
}
