<?php
/**
 * The website pages an office needs on day one.
 *
 * An office that has just switched the plugin on has an empty website. They can
 * add ten properties and still see nothing, because nothing tells WordPress
 * where those properties should appear. This class builds the handful of pages
 * that make the plugin visible to visitors, so the office is not left staring
 * at a website that looks broken.
 *
 * Everything here is safe to run twice: a page that already exists is reused,
 * never duplicated, and a page the office has edited is never overwritten.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Install;

use EstatOS\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and remembers the office's public pages.
 */
final class Pages {

	/**
	 * Meta key marking a page as one we created.
	 *
	 * @var string
	 */
	public const MARKER = '_estat_page';

	/**
	 * Settings key holding the map of page slug to post ID.
	 *
	 * @var string
	 */
	public const SETTING = 'pages';

	/**
	 * The pages we know how to build.
	 *
	 * Each one is described in the office's language, not in web jargon, so the
	 * setup screen can list them as plain choices.
	 *
	 * @return array<string,array{title:string,slug:string,description:string,shortcode:string}>
	 */
	public static function catalogue(): array {
		return array(
			'properties' => array(
				'title'       => __( 'Properties', 'estat-os' ),
				'slug'        => 'properties',
				'description' => __( 'The page where visitors browse everything you have for sale or rent.', 'estat-os' ),
				'shortcode'   => '[estat_search][estat_properties]',
			),
			'contact'    => array(
				'title'       => __( 'Contact us', 'estat-os' ),
				'slug'        => 'contact-us',
				'description' => __( 'A page with your enquiry form, so visitors can reach you.', 'estat-os' ),
				'shortcode'   => '[estat_form]',
			),
			'team'       => array(
				'title'       => __( 'Our team', 'estat-os' ),
				'slug'        => 'our-team',
				'description' => __( 'Introduces the people in your office, with their phone numbers.', 'estat-os' ),
				'shortcode'   => '[estat_team]',
			),
			'shortlist'  => array(
				'title'       => __( 'Saved properties', 'estat-os' ),
				'slug'        => 'saved-properties',
				'description' => __( 'Where a visitor finds the properties they marked as favourites.', 'estat-os' ),
				'shortcode'   => '[estat_favorites][estat_compare]',
			),
		);
	}

	/**
	 * The pages this office currently has, as slug => post ID.
	 *
	 * A page the office has since deleted is quietly dropped, so a stale id can
	 * never send anyone to a missing page.
	 *
	 * @return array<string,int>
	 */
	public static function existing(): array {
		$stored = Settings::get( self::SETTING );
		$stored = is_array( $stored ) ? $stored : array();
		$live   = array();

		foreach ( self::catalogue() as $key => $unused ) {
			$id = isset( $stored[ $key ] ) ? (int) $stored[ $key ] : 0;
			if ( $id <= 0 ) {
				continue;
			}
			$post = get_post( $id );
			if ( $post && 'page' === $post->post_type && 'trash' !== $post->post_status ) {
				$live[ $key ] = $id;
			}
		}

		return $live;
	}

	/**
	 * Build the requested pages.
	 *
	 * @param array<int,string> $keys  Which pages to build. Empty means all.
	 * @param bool              $ready Fill each page with a ready-made layout
	 *                                 rather than a bare shortcode.
	 * @return array<string,int> Slug to post ID for every page now in place.
	 */
	public static function create( array $keys = array(), bool $ready = true ): array {
		$catalogue = self::catalogue();
		$keys      = array() === $keys ? array_keys( $catalogue ) : $keys;
		$pages     = self::existing();

		foreach ( $keys as $key ) {
			$key = (string) $key;
			if ( ! isset( $catalogue[ $key ] ) || isset( $pages[ $key ] ) ) {
				// Unknown, or the office already has it. Never build a second copy.
				continue;
			}

			$spec = $catalogue[ $key ];

			// If a page with this slug already exists, adopt it instead of
			// creating a confusing near-duplicate.
			$found = get_page_by_path( $spec['slug'] );
			if ( $found instanceof \WP_Post ) {
				$pages[ $key ] = (int) $found->ID;
				update_post_meta( (int) $found->ID, self::MARKER, $key );
				continue;
			}

			$id = wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => $spec['title'],
					'post_name'      => $spec['slug'],
					'post_content'   => $ready ? self::layout( $key, $spec ) : $spec['shortcode'],
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				),
				true
			);

			if ( is_wp_error( $id ) || ! $id ) {
				continue;
			}

			update_post_meta( (int) $id, self::MARKER, $key );
			$pages[ $key ] = (int) $id;
		}

		Settings::update( array( self::SETTING => $pages ) );

		return $pages;
	}

	/**
	 * The address of one of the office's pages, or an empty string.
	 *
	 * @param string $key Page key.
	 * @return string
	 */
	public static function url( string $key ): string {
		$pages = self::existing();
		if ( ! isset( $pages[ $key ] ) ) {
			return '';
		}
		return (string) get_permalink( $pages[ $key ] );
	}

	/**
	 * A ready-made layout for one page.
	 *
	 * Plain WordPress content with a heading and an introduction around the
	 * shortcode, so the page looks finished the moment it is created rather
	 * than showing a bare grid with no context. The office can edit or replace
	 * any of it, in Elementor or anywhere else.
	 *
	 * @param string                                                                     $key  Page key.
	 * @param array{title:string,slug:string,description:string,shortcode:string} $spec Page spec.
	 * @return string
	 */
	private static function layout( string $key, array $spec ): string {
		$office = (string) Settings::get( 'office_name' );
		$office = '' !== $office ? $office : __( 'our office', 'estat-os' );

		switch ( $key ) {
			case 'properties':
				$heading = __( 'Find your next home', 'estat-os' );
				$intro   = sprintf(
					/* translators: %s: office name. */
					__( 'Browse everything %s currently has available. Use the search box to narrow things down by budget, area or number of bedrooms.', 'estat-os' ),
					$office
				);
				break;

			case 'contact':
				$heading = __( 'Get in touch', 'estat-os' );
				$intro   = __( 'Tell us what you are looking for and we will get back to you. There is no obligation.', 'estat-os' );
				break;

			case 'team':
				$heading = __( 'The people you will deal with', 'estat-os' );
				$intro   = sprintf(
					/* translators: %s: office name. */
					__( 'Everyone at %s who can help you buy, sell or rent.', 'estat-os' ),
					$office
				);
				break;

			case 'shortlist':
				$heading = __( 'Your saved properties', 'estat-os' );
				$intro   = __( 'The properties you marked with a heart are kept here so you can compare them side by side.', 'estat-os' );
				break;

			default:
				$heading = $spec['title'];
				$intro   = $spec['description'];
				break;
		}

		return sprintf(
			"<!-- wp:heading {\"level\":1} -->\n<h1>%1\$s</h1>\n<!-- /wp:heading -->\n\n"
			. "<!-- wp:paragraph -->\n<p>%2\$s</p>\n<!-- /wp:paragraph -->\n\n"
			. "<!-- wp:shortcode -->\n%3\$s\n<!-- /wp:shortcode -->",
			esc_html( $heading ),
			esc_html( $intro ),
			$spec['shortcode']
		);
	}
}
