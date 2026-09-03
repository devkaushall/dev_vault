<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
require dirname( __DIR__ ) . '/src/Core/Autoloader.php';
\Mayfair\RealEstatePlatform\Core\Autoloader::register();

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( public string $code = '', public string $message = '', public array $data = array() ) {} public function get_error_code(): string {
			return $this->code; }
	} }
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $value ): bool {
		return $value instanceof WP_Error; } }
if ( ! function_exists( 'is_email' ) ) {
	function is_email( mixed $value ): string|false {
		return is_string( $value ) && false !== filter_var( $value, FILTER_VALIDATE_EMAIL ) ? $value : false; } }
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( mixed $value ): string {
		return is_string( $value ) ? strtolower( trim( $value ) ) : ''; } }
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value ): string|false {
		return json_encode( $value ); } }
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( string $scheme = 'auth' ): string {
		return 'unit-test-salt-' . $scheme; } }
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, mixed $default = false ): mixed {
		return $default; } }
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $v ): string {
		return trim( strip_tags( (string) $v ) ); } }
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $v ): string {
		return trim( strip_tags( (string) $v ) ); } }
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ): int {
		return abs( (int) $v ); } }
if ( ! function_exists( 'rest_sanitize_boolean' ) ) {
	function rest_sanitize_boolean( $v ): bool {
		return filter_var( $v, FILTER_VALIDATE_BOOLEAN ); } }
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $v, $protocols = array() ): string {
		return filter_var( $v, FILTER_VALIDATE_URL ) ? (string) $v : ''; } }
if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( $v ): string|false {
		return filter_var( $v, FILTER_VALIDATE_URL ) ? (string) $v : false; } }
if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $id ): string|false {
		return $id === 10 ? 'attachment' : false; } }
