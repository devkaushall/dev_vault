<?php
/**
 * The shared assertion collector used by both the unit and integration runners.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

/**
 * Very small assertion collector.
 */
final class EstatTests {

	/**
	 * Passing assertions.
	 *
	 * @var int
	 */
	public static int $passed = 0;

	/**
	 * Failure messages.
	 *
	 * @var string[]
	 */
	public static array $failures = array();

	/**
	 * Current group name.
	 *
	 * @var string
	 */
	public static string $group = '';

	/**
	 * Start a group.
	 *
	 * @param string $name Group name.
	 * @return void
	 */
	public static function group( string $name ): void {
		self::$group = $name;
		echo "\n" . $name . "\n";
	}

	/**
	 * Assert two values match.
	 *
	 * @param mixed  $expected Expected.
	 * @param mixed  $actual   Actual.
	 * @param string $message  Description.
	 * @return void
	 */
	public static function is( $expected, $actual, string $message ): void {
		if ( $expected === $actual ) {
			++self::$passed;
			echo '  ok    ' . $message . "\n";
			return;
		}
		self::$failures[] = self::$group . ' / ' . $message;
		echo '  FAIL  ' . $message . "\n";
		echo '        expected: ' . var_export( $expected, true ) . "\n";
		echo '        actual:   ' . var_export( $actual, true ) . "\n";
	}

	/**
	 * Assert a condition is true.
	 *
	 * @param bool   $condition Condition.
	 * @param string $message   Description.
	 * @return void
	 */
	public static function ok( bool $condition, string $message ): void {
		self::is( true, $condition, $message );
	}

	/**
	 * Print the summary and return an exit code.
	 *
	 * @return int
	 */
	public static function summary(): int {
		echo "\n";
		if ( self::$failures ) {
			echo count( self::$failures ) . " failing, " . self::$passed . " passing\n";
			foreach ( self::$failures as $failure ) {
				echo '  - ' . $failure . "\n";
			}
			return 1;
		}
		echo self::$passed . " passing\n";
		return 0;
	}
}

