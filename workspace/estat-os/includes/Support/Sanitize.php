<?php
/**
 * Input sanitising helpers used across admin, REST and CSV entry points.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Every untrusted value passes through one of these helpers.
 */
final class Sanitize {

	/**
	 * Sanitize plain text.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function text( $value ): string {
		$text = sanitize_text_field( (string) ( is_scalar( $value ) ? $value : '' ) );

		/*
		 * Core's sanitize_text_field() does not remove a NUL byte, and a NUL
		 * in a title truncates it in anything that hands the string to a
		 * C-level function - exports, some database tools, some mail clients.
		 * Strip every C0 control character except tab, which is harmless.
		 */
		return (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
	}

	/**
	 * Text with a hard length limit, for anything that becomes a heading.
	 *
	 * A 6,000-character property headline saved perfectly happily and then
	 * wrecked every table and card it appeared in. Titles are cut on a word
	 * boundary where possible, so the result still reads as a sentence.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $max      Maximum characters.
	 * @return string
	 */
	public static function title( $value, int $max = 180 ): string {
		$text = self::text( $value );

		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) <= $max : strlen( $text ) <= $max ) {
			return $text;
		}

		$cut  = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
		$space = strrpos( $cut, ' ' );

		// Only break on a word if that does not throw most of it away.
		if ( false !== $space && $space > (int) ( $max * 0.6 ) ) {
			$cut = substr( $cut, 0, $space );
		}

		return rtrim( $cut );
	}

	/**
	 * Sanitize multiline text.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function textarea( $value ): string {
		return sanitize_textarea_field( (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	/**
	 * Sanitize rich content allowing safe post HTML.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function html( $value ): string {
		return wp_kses_post( (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	/**
	 * Sanitize an email address.
	 *
	 * @param mixed $value Raw value.
	 * @return string Empty string when invalid.
	 */
	public static function email( $value ): string {
		$email = sanitize_email( (string) ( is_scalar( $value ) ? $value : '' ) );
		return is_email( $email ) ? $email : '';
	}

	/**
	 * Sanitize a phone number to digits, spaces and + - ( ).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function phone( $value ): string {
		$raw = (string) ( is_scalar( $value ) ? $value : '' );
		$out = preg_replace( '/[^0-9+\-() ]/', '', $raw );
		return trim( substr( (string) $out, 0, 32 ) );
	}

	/**
	 * Sanitize a URL.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function url( $value ): string {
		return esc_url_raw( (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	/**
	 * Sanitize an integer with optional bounds.
	 *
	 * @param mixed    $value Raw value.
	 * @param int      $min   Minimum.
	 * @param int|null $max   Maximum.
	 * @return int
	 */
	public static function int( $value, int $min = 0, ?int $max = null ): int {
		$int = (int) ( is_scalar( $value ) ? $value : 0 );
		if ( $int < $min ) {
			$int = $min;
		}
		if ( null !== $max && $int > $max ) {
			$int = $max;
		}
		return $int;
	}

	/**
	 * Sanitize a float.
	 *
	 * @param mixed $value Raw value.
	 * @param float $min   Minimum.
	 * @return float
	 */
	public static function float( $value, float $min = 0.0 ): float {
		$float = (float) ( is_scalar( $value ) ? str_replace( array( ',', ' ' ), '', (string) $value ) : 0 );
		return $float < $min ? $min : round( $float, 4 );
	}

	/**
	 * Sanitize a boolean-ish value.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function bool( $value ): bool {
		return in_array( $value, array( true, 1, '1', 'yes', 'true', 'on' ), true );
	}

	/**
	 * Restrict a value to an allowed list.
	 *
	 * @param mixed    $value    Raw value.
	 * @param string[] $allowed  Allowed values.
	 * @param string   $fallback Fallback value.
	 * @return string
	 */
	public static function choice( $value, array $allowed, string $fallback ): string {
		$value = self::text( $value );
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Sanitize a Y-m-d date string.
	 *
	 * @param mixed $value Raw value.
	 * @return string Empty when invalid.
	 */
	public static function date( $value ): string {
		$raw = self::text( $value );
		if ( '' === $raw ) {
			return '';
		}
		$parts = date_parse( $raw );
		if ( ! empty( $parts['error_count'] ) || empty( $parts['year'] ) ) {
			return '';
		}
		$time = strtotime( $raw );
		return $time ? gmdate( 'Y-m-d', $time ) : '';
	}

	/**
	 * Sanitize a date and time string to MySQL format.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function datetime( $value ): string {
		$raw  = self::text( $value );
		$time = $raw ? strtotime( $raw ) : false;
		return $time ? gmdate( 'Y-m-d H:i:s', $time ) : '';
	}

	/**
	 * Sanitize a latitude value.
	 *
	 * @param mixed $value Raw value.
	 * @return string Empty when out of range.
	 */
	public static function latitude( $value ): string {
		$float = (float) ( is_scalar( $value ) ? $value : 0 );
		if ( 0.0 === $float || $float < -90 || $float > 90 ) {
			return '';
		}
		return (string) round( $float, 7 );
	}

	/**
	 * Sanitize a longitude value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function longitude( $value ): string {
		$float = (float) ( is_scalar( $value ) ? $value : 0 );
		if ( 0.0 === $float || $float < -180 || $float > 180 ) {
			return '';
		}
		return (string) round( $float, 7 );
	}

	/**
	 * Sanitize a list of attachment IDs.
	 *
	 * @param mixed $value Raw value (array or comma list).
	 * @return int[]
	 */
	public static function id_list( $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$ids = array_map( 'absint', $value );
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		return array_slice( $ids, 0, 200 );
	}

	/**
	 * Recursively sanitize an array of scalar values as text.
	 *
	 * @param mixed $value Raw value.
	 * @return array<mixed>
	 */
	public static function text_array( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $key => $item ) {
			$key         = self::text( $key );
			$out[ $key ] = is_array( $item ) ? self::text_array( $item ) : self::text( $item );
		}
		return $out;
	}
}
