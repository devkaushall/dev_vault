<?php
/**
 * Test bootstrap.
 *
 * These tests are deliberately WordPress-free: they cover the pure logic that
 * carries the most risk (money and area maths, sanitising, vocabulary,
 * signature generation) without needing a database or a WordPress install, so
 * they run anywhere in under a second.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

define( 'ABSPATH', __DIR__ . '/' );

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'MB_IN_BYTES', 1048576 );
}

define( 'ESTAT_VERSION', '1.0.0' );
define( 'ESTAT_DIR', dirname( __DIR__ ) . '/' );
define( 'ESTAT_URL', 'https://example.test/wp-content/plugins/estat-os/' );

/**
 * Minimal stand-ins for the handful of WordPress functions the pure helpers
 * touch. Anything more than this belongs in an integration test.
 */
if ( ! function_exists( '__' ) ) {
	/**
	 * Pass-through translation.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Pass-through escaped translation.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * Pass-through plural.
	 *
	 * @param string $single Singular.
	 * @param string $plural Plural.
	 * @param int    $number Count.
	 * @param string $domain Domain.
	 * @return string
	 */
	function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Very small sanitiser mirroring the shape of the real one.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function sanitize_text_field( string $value ): string {
		$value = wp_strip_all_tags( $value );
		$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Strip tags.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function wp_strip_all_tags( string $value ): string {
		return trim( (string) wp_kses_no_null( strip_tags( $value ) ) );
	}
}

if ( ! function_exists( 'wp_kses_no_null' ) ) {
	/**
	 * Remove null bytes.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function wp_kses_no_null( string $value ): string {
		return str_replace( "\0", '', $value );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	/**
	 * Validate an email address.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function sanitize_email( string $value ): string {
		$value = trim( $value );
		return filter_var( $value, FILTER_VALIDATE_EMAIL ) ? $value : '';
	}
}

if ( ! function_exists( 'is_email' ) ) {
	/**
	 * Check an email address.
	 *
	 * @param string $value Raw value.
	 * @return bool
	 */
	function is_email( string $value ): bool {
		return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Validate a URL.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function esc_url_raw( string $value ): string {
		return filter_var( trim( $value ), FILTER_VALIDATE_URL ) ? trim( $value ) : '';
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Absolute integer.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * No-op filter.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( string $hook, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	/**
	 * Locale-free number formatting.
	 *
	 * @param float $number   Number.
	 * @param int   $decimals Decimals.
	 * @return string
	 */
	function number_format_i18n( float $number, int $decimals = 0 ): string {
		return number_format( $number, $decimals );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * In-memory options table.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	function get_option( string $name, $default = false ) {
		global $estat_test_options;
		return array_key_exists( $name, (array) $estat_test_options ) ? $estat_test_options[ $name ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Write to the in-memory options table.
	 *
	 * @param string $name     Option name.
	 * @param mixed  $value    Value.
	 * @param bool   $autoload Ignored.
	 * @return bool
	 */
	function update_option( string $name, $value, bool $autoload = true ): bool {
		global $estat_test_options;
		$estat_test_options[ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'date_i18n' ) ) {
	/**
	 * Locale-free date formatting.
	 *
	 * @param string   $format Format.
	 * @param int|null $time   Timestamp.
	 * @return string
	 */
	function date_i18n( string $format, ?int $time = null ): string {
		return gmdate( $format, null === $time ? time() : $time );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * Multi-line text sanitiser.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function sanitize_textarea_field( string $value ): string {
		return trim( wp_strip_all_tags( $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Lowercase key sanitiser.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function sanitize_key( string $value ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? '';
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * Very small rich-text filter.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function wp_kses_post( string $value ): string {
		return strip_tags( $value, '<p><br><strong><em><ul><ol><li><a><h2><h3><h4><blockquote>' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON encode.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	function wp_json_encode( $value ): string {
		return (string) json_encode( $value );
	}
}

$GLOBALS['estat_test_options'] = array();

require_once ESTAT_DIR . 'includes/Autoloader.php';
EstatOS\Autoloader::register();
