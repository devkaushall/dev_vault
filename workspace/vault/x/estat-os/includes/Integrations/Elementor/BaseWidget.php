<?php
/**
 * Shared base for every Estat Elementor widget.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Integrations\Elementor;

use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps each widget tiny: category, icon and asset handling live here.
 */
abstract class BaseWidget extends Widget_Base {

	/**
	 * Widget category.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( Bridge::CATEGORY );
	}

	/**
	 * Default icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-posts-grid';
	}

	/**
	 * Styles this widget needs. Elementor loads them only on pages that use it.
	 *
	 * @return string[]
	 */
	public function get_style_depends(): array {
		return array( 'estat-public' );
	}

	/**
	 * Scripts this widget needs.
	 *
	 * @return string[]
	 */
	public function get_script_depends(): array {
		return array( 'estat-public' );
	}

	/**
	 * Keywords for the Elementor search box.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'property', 'real estate', 'listing', 'estat' );
	}
}
