<?php
/**
 * Stands in for wp-admin/includes/upgrade.php.
 *
 * The plugin correctly does require_once ABSPATH . 'wp-admin/includes/upgrade.php'
 * before calling dbDelta(). In the harness ABSPATH points here, so that require
 * resolves to this file and the harness dbDelta() defined in wp-stubs.php is used.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

// dbDelta() is defined in tests/integration/wp-stubs.php.
