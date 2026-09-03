<?php
define( 'REALESTATE_PLATFORM_DIR', __DIR__ . '/../' );
define( 'REALESTATE_PLATFORM_FILE', __DIR__ . '/../realestate-platform.php' );
define( 'REALESTATE_PLATFORM_VERSION', '0.9.0' );
define( 'REALESTATE_PLATFORM_DB_VERSION', '004' );
if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		public static function add_command( string $name, callable $callable ): void {} public static function line( string|false $message ): void {}
	} }
if ( ! function_exists( 'get_field' ) ) {
	function get_field( string $selector, int|false $post_id = false, bool $format_value = true ): mixed {
		return null; } }
