<?php
/**
 * Custom content types. Labels use office language, never WordPress jargon.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the business objects stored as posts: listings, projects, agents,
 * agencies and documents. Leads, visits, forms and submissions live in their
 * own tables because they are operational records, not content.
 */
final class PostTypes {

	/**
	 * Listing post type key (data contract).
	 */
	public const LISTING = 'estat_listing';

	/**
	 * Project post type key.
	 */
	public const PROJECT = 'estat_project';

	/**
	 * Agent post type key.
	 */
	public const AGENT = 'estat_agent';

	/**
	 * Agency post type key.
	 */
	public const AGENCY = 'estat_agency';

	/**
	 * Document post type key.
	 */
	public const DOCUMENT = 'estat_document';

	/**
	 * Insight post type key. Covers blogs, articles and market insights, which
	 * are three kinds of the same thing and live on one screen together.
	 */
	public const INSIGHT = 'estat_insight';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 5 );
	}

	/**
	 * Register every post type.
	 *
	 * @return void
	 */
	public static function register_post_types(): void {
		register_post_type(
			self::LISTING,
			array(
				'labels'              => self::labels( __( 'Listing', 'estat-os' ), __( 'Listings', 'estat-os' ) ),
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'has_archive'         => 'properties',
				'rewrite'             => array( 'slug' => 'property', 'with_front' => false ),
				'menu_icon'           => 'dashicons-admin-home',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'author', 'revisions', 'excerpt' ),
				'capability_type'     => array( 'estat_listing', 'estat_listings' ),
				'map_meta_cap'        => true,
				'capabilities'        => array(
					// Per-post checks get their own private names; see simple_caps().
					'edit_post'              => 'edit_estat_listing',
					'read_post'              => 'read_estat_listing',
					'delete_post'            => 'delete_estat_listing',
					'edit_posts'             => 'estat_manage_listings',
					'edit_others_posts'      => 'estat_manage_listings',
					'publish_posts'          => 'estat_publish_listings',
					'read_private_posts'     => 'estat_manage_listings',
					'delete_posts'           => 'estat_delete_listings',
					'delete_others_posts'    => 'estat_delete_listings',
					'edit_published_posts'   => 'estat_manage_listings',
					'delete_published_posts' => 'estat_delete_listings',
					'create_posts'           => 'estat_manage_listings',
				),
			)
		);

		register_post_type(
			self::PROJECT,
			array(
				'labels'          => self::labels( __( 'Society / Project', 'estat-os' ), __( 'Societies & Projects', 'estat-os' ) ),
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'has_archive'     => 'projects',
				'rewrite'         => array( 'slug' => 'project', 'with_front' => false ),
				'menu_icon'       => 'dashicons-building',
				'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => self::simple_caps( 'estat_manage_projects', 'estat_project' ),
			)
		);

		register_post_type(
			self::AGENT,
			array(
				'labels'          => self::labels( __( 'Team Member', 'estat-os' ), __( 'Team', 'estat-os' ) ),
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'has_archive'     => 'team',
				'rewrite'         => array( 'slug' => 'team', 'with_front' => false ),
				'menu_icon'       => 'dashicons-groups',
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => self::simple_caps( 'estat_manage_team', 'estat_agent' ),
			)
		);

		register_post_type(
			self::AGENCY,
			array(
				'labels'          => self::labels( __( 'Company', 'estat-os' ), __( 'Companies', 'estat-os' ) ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => self::simple_caps( 'estat_manage_team', 'estat_agency' ),
			)
		);

		register_post_type(
			self::INSIGHT,
			array(
				'labels'          => self::labels( __( 'Insight', 'estat-os' ), __( 'Insights', 'estat-os' ) ),
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'has_archive'     => 'insights',
				'rewrite'         => array( 'slug' => 'insights', 'with_front' => false ),
				'menu_icon'       => 'dashicons-lightbulb',
				'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => self::simple_caps( 'estat_manage_insights', 'estat_insight' ),
			)
		);

		register_post_type(
			self::DOCUMENT,
			array(
				'labels'          => self::labels( __( 'Document', 'estat-os' ), __( 'Documents & Brochures', 'estat-os' ) ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => self::simple_caps( 'estat_manage_listings', 'estat_document' ),
			)
		);
	}

	/**
	 * Build a full label set from a singular/plural pair.
	 *
	 * @param string $singular Singular label.
	 * @param string $plural   Plural label.
	 * @return array<string,string>
	 */
	private static function labels( string $singular, string $plural ): array {
		return array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'add_new'            => __( 'Add new', 'estat-os' ),
			/* translators: %s: item name */
			'add_new_item'       => sprintf( __( 'Add %s', 'estat-os' ), $singular ),
			/* translators: %s: item name */
			'edit_item'          => sprintf( __( 'Edit %s', 'estat-os' ), $singular ),
			/* translators: %s: item name */
			'new_item'           => sprintf( __( 'New %s', 'estat-os' ), $singular ),
			/* translators: %s: item name */
			'view_item'          => sprintf( __( 'View %s', 'estat-os' ), $singular ),
			'search_items'       => $plural,
			/* translators: %s: items name */
			'not_found'          => sprintf( __( 'No %s yet.', 'estat-os' ), strtolower( $plural ) ),
			'not_found_in_trash' => __( 'Nothing here.', 'estat-os' ),
			'all_items'          => $plural,
			'menu_name'          => $plural,
		);
	}

	/**
	 * Map every capability of a post type to one plugin capability.
	 *
	 * @param string $cap Capability.
	 * @return array<string,string>
	 */
	private static function simple_caps( string $cap, string $singular = '', string $plural = '' ): array {
		// The three "meta" capabilities (edit_post, read_post, delete_post) must
		// stay unique per post type. WordPress records them in a global lookup
		// and, on every later check, treats the mapped name as a per-post
		// capability that needs a post ID. If two post types map them to the
		// same plugin capability, a plain current_user_can( 'estat_manage_x' )
		// call with no post ID silently fails for everyone, including
		// administrators, and the menu entry disappears. So they get their own
		// private names, and only the list-level capabilities point at the
		// plugin capability the office actually manages.
		$singular = '' !== $singular ? $singular : $cap . '_item';
		$plural   = '' !== $plural ? $plural : $cap . '_items';

		return array(
			'edit_post'              => 'edit_' . $singular,
			'read_post'              => 'read_' . $singular,
			'delete_post'            => 'delete_' . $singular,
			'edit_posts'             => $cap,
			'edit_others_posts'      => $cap,
			'publish_posts'          => $cap,
			'read_private_posts'     => $cap,
			'delete_posts'           => $cap,
			'delete_others_posts'    => $cap,
			'edit_published_posts'   => $cap,
			'delete_published_posts' => $cap,
			'create_posts'           => $cap,
		);
	}

	/**
	 * All post types owned by the plugin.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::LISTING, self::PROJECT, self::AGENT, self::AGENCY, self::DOCUMENT );
	}
}
