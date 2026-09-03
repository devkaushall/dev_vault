<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\Logging;

use Mayfair\RealEstatePlatform\Contracts\LoggerInterface;

final class OptionLogger implements LoggerInterface {
	private const LEVELS     = array( 'debug', 'info', 'notice', 'warning', 'error', 'critical' );
	private const CATEGORIES = array( 'system', 'database', 'migration', 'security', 'rest', 'admin', 'integration' );

	/** @param array<string, mixed> $context */
	public function log( string $level, string $message, array $context = array(), string $category = 'system' ): void {
		if ( ! in_array( $level, self::LEVELS, true ) || ! in_array( $category, self::CATEGORIES, true ) ) {
			return;
		}

		$performance    = (array) get_option( 'realestate_platform_settings_performance', array() );
		$retention_days = max( 1, (int) ( $performance['log_retention_days'] ?? 30 ) );
		$context        = $this->redact( $context );
		$rows           = (array) get_option( 'realestate_platform_log', array() );
		$rows[]         = array(
			'time'     => gmdate( 'c' ),
			'level'    => $level,
			'category' => $category,
			'message'  => wp_strip_all_tags( $message ),
			'context'  => $context,
		);
		$cutoff         = time() - ( $retention_days * DAY_IN_SECONDS );
		$rows           = array_values(
			array_filter(
				array_slice( $rows, -500 ),
				static fn( array $row ): bool => strtotime( (string) ( $row['time'] ?? '' ) ) >= $cutoff
			)
		);
		update_option( 'realestate_platform_log', $rows, false );
	}

	/** @param array<string, mixed> $context
	 * @return array<string, mixed> */
	private function redact( array $context ): array {
		foreach ( $context as $key => $value ) {
			if ( preg_match( '/pass|secret|token|api.?key|authorization|email|phone/i', (string) $key ) ) {
				$context[ $key ] = '[REDACTED]';
			} elseif ( is_array( $value ) ) {
				$context[ $key ] = $this->redact( $value );
			}
		}
		return $context;
	}
}
