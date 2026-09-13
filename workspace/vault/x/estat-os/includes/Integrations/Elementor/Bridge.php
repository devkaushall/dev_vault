<?php
/**
 * Elementor integration. Entirely optional.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor is a presentation layer. The plugin owns the data; these widgets
 * only render it. Nothing here loads unless Elementor is actually active, and
 * every widget calls the same components the shortcodes use.
 */
final class Bridge {

	/**
	 * Widget category slug.
	 */
	public const CATEGORY = 'estat-real-estate';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'add_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * Whether Elementor is available.
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return did_action( 'elementor/loaded' ) > 0;
	}

	/**
	 * Add the branded widget category.
	 *
	 * @param object $manager Elementor category manager.
	 * @return void
	 */
	public static function add_category( $manager ): void {
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'add_category' ) ) {
			return;
		}
		$manager->add_category(
			self::CATEGORY,
			array(
				'title' => __( 'Real Estate', 'estat-os' ),
				'icon'  => 'eicon-home-heart',
			)
		);
	}

	/**
	 * Register every widget.
	 *
	 * @param object $widgets_manager Elementor widget manager.
	 * @return void
	 */
	public static function register_widgets( $widgets_manager ): void {
		if ( ! self::active() || ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'register' ) ) {
			return;
		}
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once __DIR__ . '/BaseWidget.php';
		require_once __DIR__ . '/Widgets.php';

		foreach ( Widgets::classes() as $class_name ) {
			if ( class_exists( $class_name ) ) {
				$widgets_manager->register( new $class_name() );
			}
		}
	}
}
