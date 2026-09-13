<?php
/**
 * Shortcodes: the Elementor-free way to place plugin components.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Frontend;

use EstatOS\Forms\Renderer;
use EstatOS\I18n\Language;
use EstatOS\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode names are a public contract.
 */
final class Shortcodes {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'estat_properties', array( __CLASS__, 'properties' ) );
		add_shortcode( 'estat_search', array( __CLASS__, 'search' ) );
		add_shortcode( 'estat_form', array( __CLASS__, 'form' ) );
		add_shortcode( 'estat_team', array( __CLASS__, 'team' ) );
		add_shortcode( 'estat_compare', array( __CLASS__, 'compare' ) );
		add_shortcode( 'estat_favorites', array( __CLASS__, 'favorites' ) );
		add_shortcode( 'estat_language', array( __CLASS__, 'language' ) );
	}

	/**
	 * [estat_properties] property directory.
	 *
	 * @param array<string,mixed>|string $atts Attributes.
	 * @return string
	 */
	public static function properties( $atts ): string {
		$atts = shortcode_atts(
			array(
				'offer'      => '',
				'type'       => '',
				'locality'   => '',
				'featured'   => '',
				'project'    => '',
				'agent'      => '',
				'orderby'    => 'recent',
				'per_page'   => 12,
			),
			(array) $atts,
			'estat_properties'
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['estat_page'] ) ? Sanitize::int( wp_unslash( $_GET['estat_page'] ), 1, 10000 ) : 1;

		return Components::directory(
			array(
				'offer'         => $atts['offer'],
				'property_type' => '' !== $atts['type'] ? explode( ',', (string) $atts['type'] ) : array(),
				'locality'      => '' !== $atts['locality'] ? explode( ',', (string) $atts['locality'] ) : array(),
				'featured'      => '' !== $atts['featured'],
				'project_id'    => (int) $atts['project'],
				'agent_id'      => (int) $atts['agent'],
				'orderby'       => $atts['orderby'],
				'per_page'      => (int) $atts['per_page'],
				'page'          => $page,
			)
		);
	}

	/**
	 * [estat_search] search form.
	 *
	 * @param array<string,mixed>|string $atts Attributes.
	 * @return string
	 */
	public static function search( $atts ): string {
		$atts = shortcode_atts( array( 'action' => '' ), (array) $atts, 'estat_search' );
		return Components::search_form( array( 'action' => $atts['action'] ) );
	}

	/**
	 * [estat_form id="1"] a plugin form.
	 *
	 * @param array<string,mixed>|string $atts Attributes.
	 * @return string
	 */
	public static function form( $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0, 'listing' => 0, 'project' => 0, 'agent' => 0, 'class' => '' ), (array) $atts, 'estat_form' );
		$id   = (int) $atts['id'];
		if ( $id <= 0 ) {
			return '';
		}
		$listing = (int) $atts['listing'];
		if ( 0 === $listing && is_singular( \EstatOS\Data\PostTypes::LISTING ) ) {
			$listing = (int) get_the_ID();
		}
		return Renderer::render(
			$id,
			array(
				'listing_id' => $listing,
				'project_id' => (int) $atts['project'],
				'agent_id'   => (int) $atts['agent'],
				'class'      => (string) $atts['class'],
			)
		);
	}

	/**
	 * [estat_team] team directory.
	 *
	 * @param array<string,mixed>|string $atts Attributes.
	 * @return string
	 */
	public static function team( $atts ): string {
		$atts = shortcode_atts( array( 'limit' => 12 ), (array) $atts, 'estat_team' );
		return Components::agents( (int) $atts['limit'] );
	}

	/**
	 * [estat_compare] comparison tray.
	 *
	 * @return string
	 */
	public static function compare(): string {
		return Components::compare();
	}

	/**
	 * [estat_favorites] saved properties.
	 *
	 * @return string
	 */
	public static function favorites(): string {
		return Components::favorites();
	}

	/**
	 * [estat_language] language selector.
	 *
	 * @return string
	 */
	public static function language(): string {
		return Language::selector();
	}
}
