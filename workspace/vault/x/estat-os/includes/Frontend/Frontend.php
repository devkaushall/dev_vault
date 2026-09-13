<?php
/**
 * Public website: asset registration and template fallbacks.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Frontend;

use EstatOS\Data\PostTypes;
use EstatOS\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Assets are registered globally but only enqueued by the component that needs
 * them, so a page without a property widget loads no plugin CSS or JavaScript.
 */
final class Frontend {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_filter( 'template_include', array( __CLASS__, 'template_fallback' ) );
		add_filter( 'pre_get_posts', array( __CLASS__, 'archive_query' ) );
	}

	/**
	 * Register (not enqueue) every public asset.
	 *
	 * @return void
	 */
	public static function register_assets(): void {
		wp_register_style( 'estat-public', ESTAT_URL . 'assets/css/public.css', array(), ESTAT_VERSION );
		wp_register_style( 'estat-forms', ESTAT_URL . 'assets/css/forms.css', array(), ESTAT_VERSION );

		wp_register_script( 'estat-forms', ESTAT_URL . 'assets/js/forms.js', array(), ESTAT_VERSION, true );
		wp_register_script( 'estat-public', ESTAT_URL . 'assets/js/public.js', array(), ESTAT_VERSION, true );

		$strings = array(
			'sending'      => __( 'Sending…', 'estat-os' ),
			'genericError' => __( 'Something went wrong. Please try again.', 'estat-os' ),
			'required'     => __( 'This information is needed.', 'estat-os' ),
			'saved'        => __( 'Saved.', 'estat-os' ),
			'compareFull'  => __( 'You can compare up to four properties at a time.', 'estat-os' ),
			'added'        => __( 'Added', 'estat-os' ),
			'removed'      => __( 'Removed', 'estat-os' ),

			// Used by the repayment estimate.
			'crore'        => __( 'Cr', 'estat-os' ),
			'lakh'         => __( 'Lakh', 'estat-os' ),
			'thousand'     => __( 'K', 'estat-os' ),
			/* translators: %s: total amount repaid over the whole loan. */
			'emiTotal'     => __( '%s repaid in total', 'estat-os' ),
			'emiCheck'     => __( 'Please check the numbers.', 'estat-os' ),
		);

		wp_localize_script(
			'estat-forms',
			'estatForms',
			array(
				'restUrl' => esc_url_raw( rest_url( 'estat/v1/' ) ),
				'i18n'    => $strings,
			)
		);
		wp_localize_script(
			'estat-public',
			'estatPublic',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'estat/v1/' ) ),
				'favorites' => (bool) Settings::get( 'enable_favorites' ),
				'compare'   => (bool) Settings::get( 'enable_compare' ),

				/*
				 * The repayment estimate formats its answer in the browser, so
				 * it needs the same currency settings the server uses. Without
				 * these the calculator would print a raw number beside prices
				 * written as "45 Lakh", which reads as two different websites.
				 */
				'currency'  => (string) Settings::get( 'currency_symbol' ),
				'priceStyle' => (string) Settings::get( 'price_format' ),
				'i18n'      => $strings,
			)
		);
	}

	/**
	 * Provide simple templates when the theme has none, so the website works
	 * with or without Elementor and with any theme.
	 *
	 * @param string $template Resolved template.
	 * @return string
	 */
	public static function template_fallback( $template ) {
		$types = array(
			PostTypes::LISTING => 'listing',
			PostTypes::PROJECT => 'project',
			PostTypes::AGENT   => 'agent',
		);

		foreach ( $types as $post_type => $slug ) {
			if ( is_singular( $post_type ) ) {
				$theme = locate_template( array( "single-{$post_type}.php" ) );
				if ( $theme ) {
					return $theme;
				}
				$fallback = ESTAT_DIR . "includes/Frontend/templates/single-{$slug}.php";
				if ( is_readable( $fallback ) ) {
					return $fallback;
				}
			}
			if ( is_post_type_archive( $post_type ) || ( PostTypes::LISTING === $post_type && is_tax( \EstatOS\Data\Taxonomies::LOCALITY ) ) ) {
				$theme = locate_template( array( "archive-{$post_type}.php" ) );
				if ( $theme ) {
					return $theme;
				}
				$fallback = ESTAT_DIR . "includes/Frontend/templates/archive-{$slug}.php";
				if ( is_readable( $fallback ) ) {
					return $fallback;
				}
			}
		}

		return $template;
	}

	/**
	 * Apply the configured page size to plugin archives (never unbounded).
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public static function archive_query( $query ): void {
		if ( is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->is_post_type_archive( PostTypes::LISTING ) || $query->is_tax( \EstatOS\Data\Taxonomies::LOCALITY ) ) {
			$query->set( 'posts_per_page', (int) Settings::get( 'listings_per_page', 12 ) );
		}
	}
}
