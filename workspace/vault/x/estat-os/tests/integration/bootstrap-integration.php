<?php
/**
 * Shared setup for the integration suite.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

require_once __DIR__ . '/wp-harness.php';

/**
 * Reset every store to a clean slate between test files.
 *
 * @return void
 */
function estat_reset_world(): void {
	global $wpdb;

	$GLOBALS['estat_posts']    = array();
	$GLOBALS['estat_postmeta'] = array();
	$GLOBALS['estat_terms']    = array();
	$GLOBALS['estat_termrel']  = array();
	$GLOBALS['estat_mail']     = array();
	$GLOBALS['estat_http']     = array();
	$GLOBALS['estat_redirects'] = array();
	$GLOBALS['estat_died']     = array();
	$GLOBALS['estat_transients'] = array();
	$GLOBALS['estat_cache']    = array();
	$GLOBALS['estat_next_post'] = 100;
	$GLOBALS['estat_next_term'] = 500;

	foreach ( array_keys( $wpdb->tables ) as $table ) {
		$wpdb->tables[ $table ] = array();
	}
	$wpdb->queries = array();

	EstatOS\Settings\Settings::flush();
	EstatOS\Install\Schema::flush_health();
}

/**
 * Boot the plugin exactly the way WordPress does.
 *
 * @return void
 */
function estat_boot_plugin(): void {
	static $booted = false;
	if ( $booted ) {
		return;
	}
	$booted = true;

	EstatOS\Plugin::instance()->boot();
	do_action( 'plugins_loaded' );
	do_action( 'init' );

	// Run the genuine activation routine: tables, roles and the starter form.
	EstatOS\Install\Activator::activate();

	// Fire the REST bootstrap so the routes really are registered.
	do_action( 'rest_api_init' );
}

/**
 * Give the current test user every capability.
 *
 * @return void
 */
function estat_login_owner(): void {
	$GLOBALS['estat_current_user'] = 1;
	$GLOBALS['estat_caps'][1]      = array( '__all__' => true );
}

/**
 * Log in as a user with an explicit, limited capability set.
 *
 * @param int      $user_id User ID.
 * @param string[] $caps    Capabilities.
 * @return void
 */
function estat_login_with( int $user_id, array $caps ): void {
	$GLOBALS['estat_current_user'] = $user_id;
	$GLOBALS['estat_caps'][ $user_id ] = array();
	foreach ( $caps as $cap ) {
		$GLOBALS['estat_caps'][ $user_id ][ $cap ] = true;
	}
}
