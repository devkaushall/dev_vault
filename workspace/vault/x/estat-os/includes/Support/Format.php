<?php
/**
 * Money, area and date formatting helpers.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Support;

use EstatOS\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Pure formatting helpers. Storage is always numeric; formatting is display only.
 */
final class Format {

	/**
	 * Area conversion factors to square feet.
	 *
	 * @var array<string,float>
	 */
	public const AREA_FACTORS = array(
		'sqft' => 1.0,
		'sqyd' => 9.0,
		'sqm'  => 10.7639,
	);

	/**
	 * Convert an area value to square feet.
	 *
	 * @param float  $value Area value.
	 * @param string $unit  Unit key.
	 * @return float Square feet, rounded to 2 decimals.
	 */
	public static function to_sqft( float $value, string $unit ): float {
		$factor = self::AREA_FACTORS[ $unit ] ?? 1.0;
		return round( $value * $factor, 2 );
	}

	/**
	 * Convert square feet into another unit.
	 *
	 * @param float  $sqft Square feet.
	 * @param string $unit Target unit.
	 * @return float
	 */
	public static function from_sqft( float $sqft, string $unit ): float {
		$factor = self::AREA_FACTORS[ $unit ] ?? 1.0;
		return round( $sqft / $factor, 2 );
	}

	/**
	 * Human readable area string.
	 *
	 * @param float  $value Value.
	 * @param string $unit  Unit key.
	 * @return string
	 */
	public static function area( float $value, string $unit = 'sqft' ): string {
		$labels = array(
			'sqft' => __( 'sq ft', 'estat-os' ),
			'sqyd' => __( 'sq yd', 'estat-os' ),
			'sqm'  => __( 'sq m', 'estat-os' ),
		);
		$label  = $labels[ $unit ] ?? $labels['sqft'];
		return number_format_i18n( $value, ( floor( $value ) === $value ) ? 0 : 2 ) . ' ' . $label;
	}

	/**
	 * Format a monetary amount for display.
	 *
	 * @param float       $amount   Numeric amount in the office currency.
	 * @param string|null $symbol   Currency symbol override.
	 * @param string|null $style    'indian' or 'international'.
	 * @return string
	 */
	public static function money( float $amount, ?string $symbol = null, ?string $style = null ): string {
		$settings = Settings::all();
		$symbol   = null !== $symbol ? $symbol : (string) $settings['currency_symbol'];
		$style    = null !== $style ? $style : (string) $settings['price_format'];

		if ( $amount < 0 ) {
			return $symbol . '0';
		}

		if ( 'indian' === $style ) {
			if ( $amount >= 10000000 ) {
				return $symbol . self::trim_number( $amount / 10000000 ) . ' ' . __( 'Cr', 'estat-os' );
			}
			if ( $amount >= 100000 ) {
				return $symbol . self::trim_number( $amount / 100000 ) . ' ' . __( 'Lakh', 'estat-os' );
			}
			if ( $amount >= 1000 ) {
				return $symbol . self::trim_number( $amount / 1000 ) . ' ' . __( 'K', 'estat-os' );
			}
			return $symbol . number_format_i18n( $amount, 0 );
		}

		if ( $amount >= 1000000 ) {
			return $symbol . self::trim_number( $amount / 1000000 ) . 'M';
		}
		if ( $amount >= 1000 ) {
			return $symbol . self::trim_number( $amount / 1000 ) . 'K';
		}
		return $symbol . number_format_i18n( $amount, 0 );
	}

	/**
	 * Trim trailing zeros from a short decimal.
	 *
	 * @param float $number Number.
	 * @return string
	 */
	private static function trim_number( float $number ): string {
		$rounded = round( $number, 2 );
		if ( floor( $rounded ) === $rounded ) {
			return (string) (int) $rounded;
		}
		return rtrim( rtrim( number_format( $rounded, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * Format a stored Y-m-d date for humans.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	public static function date( string $date ): string {
		$time = strtotime( $date );
		if ( ! $time ) {
			return '';
		}
		return date_i18n( (string) get_option( 'date_format', 'Y-m-d' ), $time );
	}

	/**
	 * A time of day, in whatever format the site prefers.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	public static function time( string $date ): string {
		$time = strtotime( $date );
		if ( ! $time ) {
			return '';
		}
		return date_i18n( (string) get_option( 'time_format', 'H:i' ), $time );
	}
}
