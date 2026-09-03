<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Diagnostics;

use Mayfair\RealEstatePlatform\Contracts\DiagnosticCheckInterface;
use Mayfair\RealEstatePlatform\Compatibility\CompatibilityDetector;
use Mayfair\RealEstatePlatform\Capabilities\CapabilityManager;
use Mayfair\RealEstatePlatform\Core\Environment;

final class DiagnosticsRunner {
	/** @var array<string, DiagnosticCheckInterface> */
	private array $checks = array();
	public function register( DiagnosticCheckInterface $c ): void {
		$this->checks[ $c->name() ] = $c;
	} /** @return list<DiagnosticResult> */
	public function run(): array {
		$r = array();
		foreach ( $this->checks as $c ) {
			try {
				$r[] = $c->run();
			} catch ( \Throwable $e ) {
				$r[] = new DiagnosticResult( $c->name(), DiagnosticResult::FAIL, 'Check could not run.', array( 'exception' => get_class( $e ) ), 'Review error logs.' );
			}
		}return $r;
	} /** @return array<string, int> */
	public function summary(): array {
		$s = array(
			'PASS' => 0,
			'WARN' => 0,
			'FAIL' => 0,
		);
		foreach ( $this->run() as $r ) {
			++$s[ $r->status ];
		}return $s;
	} public static function withFoundationChecks( CompatibilityDetector $detector ): self {
		$x     = new self();
		$tests = array(
			'PHP version'           => fn()=>self::versionResult( 'PHP version', PHP_VERSION, Environment::PHP_MIN ),
			'WordPress version'     => function () {
				global $wp_version;
				return self::versionResult( 'WordPress version', (string) $wp_version, Environment::WP_MIN );},
			'Plugin version'        => fn()=>new DiagnosticResult( 'Plugin version', DiagnosticResult::PASS, REALESTATE_PLATFORM_VERSION ),
			'Database schema'       => fn()=>new DiagnosticResult( 'Database schema', get_option( 'realestate_platform_db_version', '0' ) === REALESTATE_PLATFORM_DB_VERSION ? DiagnosticResult::PASS : DiagnosticResult::FAIL, 'Installed schema: ' . get_option( 'realestate_platform_db_version', '0' ), array(), 'Run activation or migrations.' ),
			'PHP extensions'        => fn()=>new DiagnosticResult( 'PHP extensions', extension_loaded( 'json' ) && extension_loaded( 'mbstring' ) ? DiagnosticResult::PASS : DiagnosticResult::FAIL, 'Required: json, mbstring', array(), 'Enable missing PHP extensions.' ),
			'Filesystem'            => fn()=>new DiagnosticResult( 'Filesystem', is_readable( REALESTATE_PLATFORM_DIR ) && wp_is_writable( WP_CONTENT_DIR ) ? DiagnosticResult::PASS : DiagnosticResult::WARN, 'Plugin readable; content directory should be writable.', array(), 'Review WordPress filesystem permissions.' ),
			'REST availability'     => fn()=>new DiagnosticResult( 'REST availability', function_exists( 'rest_get_server' ) ? DiagnosticResult::PASS : DiagnosticResult::FAIL, 'REST API function availability.' ),
			'Cron availability'     => fn()=>new DiagnosticResult( 'Cron availability', defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? DiagnosticResult::WARN : DiagnosticResult::PASS, 'WP-Cron configuration.', array(), 'Configure a system cron runner if WP-Cron is disabled.' ),
			'Capabilities'          => function () {
					$administrator = get_role( 'administrator' );
					$registered = $administrator && array_reduce( CapabilityManager::CAPS, static fn( $ok, $cap ) => $ok && $administrator->has_cap( $cap ), true );
					return new DiagnosticResult( 'Capabilities', $registered ? DiagnosticResult::PASS : DiagnosticResult::FAIL, 'Administrator capability registration.', array(), 'Reactivate plugin or run capability repair.' );
			},
			'Settings integrity'    => fn()=>new DiagnosticResult( 'Settings integrity', is_array( get_option( 'realestate_platform_settings_general', null ) ) ? DiagnosticResult::PASS : DiagnosticResult::FAIL, 'Typed settings options.', array(), 'Initialize default settings.' ),
			'Database connectivity' => function () {
						global $wpdb;
						$ok = $wpdb->get_var( 'SELECT 1' ) === '1';
						return new DiagnosticResult( 'Database connectivity', $ok ? DiagnosticResult::PASS : DiagnosticResult::FAIL, 'Database query check.' );},
			'Compatibility'         => fn()=>new DiagnosticResult( 'Compatibility', DiagnosticResult::PASS, 'Detection completed.', $detector->snapshot() ),
		);
		foreach ( $tests as $n => $cb ) {
			$x->register( new CallbackCheck( $n, $cb ) );
		}return $x;
	} private static function versionResult( string $n, string $actual, string $min ): DiagnosticResult {
		return new DiagnosticResult( $n, version_compare( $actual, $min, '>=' ) ? DiagnosticResult::PASS : DiagnosticResult::FAIL, "{$actual}; minimum {$min}" );}
}
