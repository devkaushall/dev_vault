<?php
/**
 * How a form looks.
 *
 * Styling is deliberately kept apart from what a form MEANS. Nothing in this
 * file can change a field id, a label, whether a field is required, or where a
 * submission goes. It only ever produces CSS custom properties.
 *
 * Every value is validated on the way in, so a style setting can never become
 * a way to inject CSS or script into a page.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Forms;

use EstatOS\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and prints form styling.
 */
final class Style {

	/**
	 * Font stacks the office may choose, by key.
	 *
	 * Only named stacks are allowed. A free-text font name would let arbitrary
	 * text reach a style attribute.
	 *
	 * @return array<string,array{label:string,stack:string}>
	 */
	public static function fonts(): array {
		return array(
			'theme'  => array(
				'label' => __( 'Same as the rest of the site', 'estat-os' ),
				'stack' => 'inherit',
			),
			'system' => array(
				'label' => __( 'Clean and modern', 'estat-os' ),
				'stack' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
			),
			'serif'  => array(
				'label' => __( 'Traditional', 'estat-os' ),
				'stack' => 'Georgia, "Times New Roman", Times, serif',
			),
			'mono'   => array(
				'label' => __( 'Typewriter', 'estat-os' ),
				'stack' => 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
			),
		);
	}

	/**
	 * Button width choices.
	 *
	 * @return array<string,string>
	 */
	public static function button_widths(): array {
		return array(
			'auto' => __( 'As wide as its text', 'estat-os' ),
			'full' => __( 'Full width', 'estat-os' ),
		);
	}

	/**
	 * Everything a form's appearance is made of, with safe defaults.
	 *
	 * Empty string on a colour means "do not set it, let the theme decide".
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Type.
			'font'             => 'theme',
			'label_size'       => 14,
			'input_size'       => 16,
			'label_weight'     => 600,

			// Colour.
			'label_colour'     => '',
			'input_colour'     => '',
			'input_bg'         => '',
			'border_colour'    => '',
			'focus_colour'     => '',
			'help_colour'      => '',
			'error_colour'     => '',

			// Shape.
			'radius'           => 6,
			'border_width'     => 1,
			'input_pad_y'      => 10,
			'input_pad_x'      => 12,
			'field_gap'        => 16,

			// Button.
			'button_bg'        => '',
			'button_text'      => '',
			'button_hover_bg'  => '',
			'button_radius'    => 6,
			'button_pad_y'     => 12,
			'button_pad_x'     => 22,
			'button_size'      => 16,
			'button_width'     => 'auto',
			'button_align'     => 'left',
		);
	}

	/**
	 * Validate a raw style array.
	 *
	 * @param mixed $style Raw style, possibly JSON.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $style ): array {
		if ( is_string( $style ) ) {
			$decoded = json_decode( $style, true );
			$style   = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $style ) ) {
			$style = array();
		}

		$d = self::defaults();

		return array(
			'font'            => Sanitize::choice( $style['font'] ?? $d['font'], array_keys( self::fonts() ), 'theme' ),
			'label_size'      => Sanitize::int( $style['label_size'] ?? $d['label_size'], 10, 28 ),
			'input_size'      => Sanitize::int( $style['input_size'] ?? $d['input_size'], 12, 28 ),
			'label_weight'    => Sanitize::choice( (string) ( $style['label_weight'] ?? $d['label_weight'] ), array( '400', '500', '600', '700' ), '600' ),

			'label_colour'    => self::colour( $style['label_colour'] ?? '' ),
			'input_colour'    => self::colour( $style['input_colour'] ?? '' ),
			'input_bg'        => self::colour( $style['input_bg'] ?? '' ),
			'border_colour'   => self::colour( $style['border_colour'] ?? '' ),
			'focus_colour'    => self::colour( $style['focus_colour'] ?? '' ),
			'help_colour'     => self::colour( $style['help_colour'] ?? '' ),
			'error_colour'    => self::colour( $style['error_colour'] ?? '' ),

			'radius'          => Sanitize::int( $style['radius'] ?? $d['radius'], 0, 40 ),
			'border_width'    => Sanitize::int( $style['border_width'] ?? $d['border_width'], 0, 6 ),
			'input_pad_y'     => Sanitize::int( $style['input_pad_y'] ?? $d['input_pad_y'], 2, 30 ),
			'input_pad_x'     => Sanitize::int( $style['input_pad_x'] ?? $d['input_pad_x'], 2, 40 ),
			'field_gap'       => Sanitize::int( $style['field_gap'] ?? $d['field_gap'], 0, 60 ),

			'button_bg'       => self::colour( $style['button_bg'] ?? '' ),
			'button_text'     => self::colour( $style['button_text'] ?? '' ),
			'button_hover_bg' => self::colour( $style['button_hover_bg'] ?? '' ),
			'button_radius'   => Sanitize::int( $style['button_radius'] ?? $d['button_radius'], 0, 40 ),
			'button_pad_y'    => Sanitize::int( $style['button_pad_y'] ?? $d['button_pad_y'], 4, 30 ),
			'button_pad_x'    => Sanitize::int( $style['button_pad_x'] ?? $d['button_pad_x'], 6, 60 ),
			'button_size'     => Sanitize::int( $style['button_size'] ?? $d['button_size'], 12, 28 ),
			'button_width'    => Sanitize::choice( $style['button_width'] ?? $d['button_width'], array_keys( self::button_widths() ), 'auto' ),
			'button_align'    => Sanitize::choice( $style['button_align'] ?? $d['button_align'], array( 'left', 'center', 'right' ), 'left' ),
		);
	}

	/**
	 * Accept only a plain hex colour, or nothing at all.
	 *
	 * `sanitize_hex_color()` returns null for anything else, which is exactly
	 * the behaviour we want: a rejected colour simply means "inherit".
	 *
	 * @param mixed $value Raw colour.
	 * @return string Hex colour including the hash, or an empty string.
	 */
	public static function colour( $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		if ( function_exists( 'sanitize_hex_color' ) ) {
			$clean = sanitize_hex_color( $value );

			return is_string( $clean ) ? $clean : '';
		}

		return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value ) ? strtolower( $value ) : '';
	}

	/**
	 * Turn a clean style array into CSS custom properties.
	 *
	 * The output is only ever "--name:value;" pairs built from validated
	 * numbers, named font stacks and hex colours, so it is safe to place in a
	 * style attribute.
	 *
	 * @param array $style Clean style.
	 * @return string
	 */
	public static function to_css( array $style ): string {
		$style = self::sanitize( $style );
		$fonts = self::fonts();
		$out   = array();

		$stack = $fonts[ $style['font'] ]['stack'] ?? 'inherit';
		if ( 'inherit' !== $stack ) {
			$out[] = '--estat-f-family:' . $stack;
		}

		$numbers = array(
			'label_size'    => '--estat-f-label-size:%dpx',
			'input_size'    => '--estat-f-font:%dpx',
			'radius'        => '--estat-f-radius:%dpx',
			'border_width'  => '--estat-f-border:%dpx',
			'input_pad_y'   => '--estat-f-pad-y:%dpx',
			'input_pad_x'   => '--estat-f-pad-x:%dpx',
			'field_gap'     => '--estat-f-gap:%dpx',
			'button_radius' => '--estat-f-btn-radius:%dpx',
			'button_pad_y'  => '--estat-f-btn-pad-y:%dpx',
			'button_pad_x'  => '--estat-f-btn-pad-x:%dpx',
			'button_size'   => '--estat-f-btn-size:%dpx',
		);

		foreach ( $numbers as $key => $template ) {
			$out[] = sprintf( $template, (int) $style[ $key ] );
		}

		$out[] = '--estat-f-label-weight:' . (int) $style['label_weight'];

		$colours = array(
			'label_colour'    => '--estat-f-label-ink',
			'input_colour'    => '--estat-f-ink',
			'input_bg'        => '--estat-f-field-bg',
			'border_colour'   => '--estat-f-line',
			'focus_colour'    => '--estat-f-accent',
			'help_colour'     => '--estat-f-muted',
			'error_colour'    => '--estat-f-danger',
			'button_bg'       => '--estat-f-btn-bg',
			'button_text'     => '--estat-f-btn-ink',
			'button_hover_bg' => '--estat-f-btn-hover',
		);

		foreach ( $colours as $key => $property ) {
			if ( '' !== $style[ $key ] ) {
				$out[] = $property . ':' . $style[ $key ];
			}
		}

		return implode( ';', $out );
	}
}
