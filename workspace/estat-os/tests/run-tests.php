<?php
/**
 * A tiny dependency-free unit test runner.
 *
 * PHPUnit is a development-only dependency and the plugin ships without
 * Composer, so the suite that guards the risky pure logic runs with nothing
 * but the PHP binary:
 *
 *     php tests/run-tests.php
 *
 * For the WordPress integration suite see tests/integration/run-integration.php.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/run-tests-lib.php';

foreach ( glob( __DIR__ . '/test-*.php' ) as $file ) {
	require_once $file;
}

exit( EstatTests::summary() );
