<?php
/**
 * A real-WordPress integration harness.
 *
 * This is NOT a mock suite. It loads the genuine WordPress source files for
 * every subsystem that does not require a database connection:
 *
 *   - plugin.php      real add_action/add_filter/do_action/apply_filters
 *   - formatting.php  real sanitize_text_field, esc_html, esc_attr, esc_url…
 *   - kses.php        real wp_kses_post
 *   - shortcodes.php  real add_shortcode/do_shortcode
 *
 * Only the storage layer is substituted, because no MySQL server or PDO driver
 * is available in this environment. The substitute is a real in-memory record
 * store with a genuine (small) SQL interpreter for the queries this plugin
 * actually issues, so SQL shape errors and prepare() mistakes still surface.
 *
 * What this proves: hook wiring, escaping, sanitising, capability logic,
 * validation, formatting, signing, and the full call graph of the plugin.
 * What it cannot prove: dbDelta table creation against real MySQL, and real
 * HTTP webhook delivery. Those are listed as untested in the release report.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

define( 'WP_ROOT', '/tmp/wordpress/' );

if ( ! is_dir( WP_ROOT ) ) {
	fwrite( STDERR, "WordPress source not found at " . WP_ROOT . "\n" );
	exit( 2 );
}

// ABSPATH points at a shim tree so that the plugin's correct
// require_once ABSPATH . 'wp-admin/includes/upgrade.php' resolves to a harness
// file instead of the real one, which needs a live database.
define( 'ABSPATH', __DIR__ . '/shim/' );
define( 'WPINC', 'wp-includes' );
define( 'WP_DEBUG', true );
define( 'WP_CONTENT_DIR', WP_ROOT . 'wp-content' );
define( 'WP_LANG_DIR', WP_CONTENT_DIR . '/languages' );
define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
define( 'EMPTY_TRASH_DAYS', 30 );
define( 'WP_DEFAULT_THEME', 'twentytwentyfive' );

$GLOBALS['wp_filter']         = array();
$GLOBALS['wp_actions']        = array();
$GLOBALS['wp_current_filter'] = array();

// ---------------------------------------------------------------------------
// Real WordPress subsystems.
// ---------------------------------------------------------------------------

require_once WP_ROOT . 'wp-includes/plugin.php';

// compat/load shims that formatting.php expects.
if ( ! function_exists( 'mb_strlen' ) ) {
	require_once WP_ROOT . 'wp-includes/compat.php';
}

require_once WP_ROOT . 'wp-includes/class-wp-error.php';
// The genuine WP_Post class, so plugin type hints are exercised for real.
require_once WP_ROOT . 'wp-includes/class-wp-post.php';
// Genuine REST request and response objects, so parameter handling, header
// casing and status codes behave exactly as they do in production.
require_once WP_ROOT . 'wp-includes/class-wp-http-response.php';
require_once WP_ROOT . 'wp-includes/rest-api/class-wp-rest-request.php';
require_once WP_ROOT . 'wp-includes/rest-api/class-wp-rest-response.php';


/**
 * WordPress core asks this before touching options.
 *
 * @return bool
 */
function wp_installing( $is_installing = null ) {
	return false;
}

// The harness storage layer is declared first, so that the function_exists()
// guards inside WordPress core defer to it instead of trying to reach MySQL.
$GLOBALS['estat_options']    = array();
$GLOBALS['estat_transients'] = array();
$GLOBALS['estat_cache']      = array();
$GLOBALS['estat_mail']       = array();
$GLOBALS['estat_http']       = array();
$GLOBALS['estat_cron']       = array();
$GLOBALS['estat_redirects']  = array();
$GLOBALS['estat_died']       = array();
$GLOBALS['estat_table_columns'] = array();

require_once __DIR__ . '/wp-stubs.php';
require_once __DIR__ . '/class-fake-wpdb.php';

$GLOBALS['wpdb'] = new Estat_Fake_WPDB();

/**
 * Small helpers that formatting.php calls. Defined here rather than pulling in
 * the whole of functions.php, which wants a live database connection.
 *
 * @param string|null $blog_charset Charset.
 * @return bool
 */
function is_utf8_charset( $blog_charset = null ) {
	return true;
}

/**
 * UTF-8 validity check used by wp_check_invalid_utf8(). Lives in
 * wp-includes/functions.php in core, which needs a database.
 *
 * @param string $str String.
 * @return bool
 */
function wp_is_valid_utf8( $str ) {
	return (bool) preg_match( '//u', (string) $str );
}

/**
 * Charset canonicalisation used by _wp_specialchars().
 *
 * @param string $charset Charset.
 * @return string
 */
function _canonical_charset( $charset ) {
	$charset = strtolower( (string) $charset );
	return ( 'utf-8' === $charset || 'utf8' === $charset ) ? 'UTF-8' : $charset;
}

/**
 * Protocols kses will permit in URLs. Matches the core default list.
 *
 * @return string[]
 */
function wp_allowed_protocols() {
	return array( 'http', 'https', 'mailto', 'tel', 'ftp', 'ftps', 'news', 'irc', 'feed', 'webcal' );
}

// The genuine WordPress escaping, sanitising, kses and shortcode engines.
require_once WP_ROOT . 'wp-includes/formatting.php';
// kses now leans on the HTML API for attribute parsing.
require_once WP_ROOT . 'wp-includes/html-api/class-wp-html-attribute-token.php';
require_once WP_ROOT . 'wp-includes/html-api/class-wp-html-span.php';
require_once WP_ROOT . 'wp-includes/html-api/class-wp-html-text-replacement.php';
require_once WP_ROOT . 'wp-includes/html-api/class-wp-html-decoder.php';
require_once WP_ROOT . 'wp-includes/html-api/class-wp-html-tag-processor.php';
require_once WP_ROOT . 'wp-includes/kses.php';
require_once WP_ROOT . 'wp-includes/shortcodes.php';

// kses needs its global allowed-tag tables.
$GLOBALS['allowedposttags']       = wp_kses_allowed_html( 'post' );
$GLOBALS['allowedtags']           = wp_kses_allowed_html( 'strip' );
$GLOBALS['allowedentitynames']    = $GLOBALS['allowedentitynames'] ?? array();

// ---------------------------------------------------------------------------
// Storage substitute: options, transients, object cache.
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// The plugin itself.
// ---------------------------------------------------------------------------

// Read the real version numbers out of the plugin file so the harness can
// never drift from what ships.
$estat_main_file = file_get_contents( dirname( __DIR__, 2 ) . '/estat-os.php' );

preg_match( "/define\(\s*'ESTAT_VERSION',\s*'([^']+)'/", (string) $estat_main_file, $estat_v );
preg_match( "/define\(\s*'ESTAT_DB_VERSION',\s*'([^']+)'/", (string) $estat_main_file, $estat_dbv );

if ( empty( $estat_v[1] ) || empty( $estat_dbv[1] ) ) {
	fwrite( STDERR, "Could not read ESTAT_VERSION / ESTAT_DB_VERSION from estat-os.php\n" );
	exit( 1 );
}

define( 'ESTAT_VERSION', $estat_v[1] );
define( 'ESTAT_DB_VERSION', $estat_dbv[1] );
define( 'ESTAT_FILE', dirname( __DIR__, 2 ) . '/estat-os.php' );
define( 'ESTAT_DIR', dirname( __DIR__, 2 ) . '/' );
define( 'ESTAT_URL', 'https://example.test/wp-content/plugins/estat-os/' );
define( 'ESTAT_BASENAME', 'estat-os/estat-os.php' );

require_once ESTAT_DIR . 'includes/Autoloader.php';
EstatOS\Autoloader::register();
