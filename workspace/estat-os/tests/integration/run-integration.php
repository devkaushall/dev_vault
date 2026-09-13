<?php
/**
 * Integration test runner.
 *
 *     php tests/integration/run-integration.php
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

require_once __DIR__ . '/bootstrap-integration.php';
require_once dirname( __DIR__ ) . '/run-tests-lib.php';

estat_boot_plugin();

$files = glob( __DIR__ . '/it-*.php' );
sort( $files );
foreach ( $files as $file ) {
	require_once $file;
}

exit( EstatTests::summary() );
