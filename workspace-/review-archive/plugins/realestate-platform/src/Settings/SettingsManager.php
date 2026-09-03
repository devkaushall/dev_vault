<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Settings;

use InvalidArgumentException;
use Mayfair\RealEstatePlatform\Contracts\SettingsInterface;

final class SettingsManager implements SettingsInterface {
	public function register(): void {
		foreach ( array( 'general', 'performance', 'privacy', 'advanced' ) as $g ) {
			register_setting(
				'realestate_platform_' . $g,
				'realestate_platform_settings_' . $g,
				array(
					'type'              => 'array',
					'sanitize_callback' => fn( $v )=>$this->sanitizeGroup( $g, $v ),
					'default'           => $this->defaults( $g ),
					'show_in_rest'      => false,
				)
			);
		}} public function initializeDefaults(): void {
		foreach ( array( 'general', 'performance', 'privacy', 'advanced' ) as $g ) {
			if ( get_option( 'realestate_platform_settings_' . $g, null ) === null ) {
				add_option( 'realestate_platform_settings_' . $g, $this->defaults( $g ), '', false );
			}
		}} public function get( string $key, mixed $fallback = null ): mixed {
			$d = SettingsSchema::definitions()[ $key ] ?? null;
			if ( ! $d ) {
				return $fallback;
			}$o = (array) get_option( 'realestate_platform_settings_' . $d->group, array() );
			return $o[ $key ] ?? $d->default_value;
		} public function update( string $key, mixed $value ): bool {
			$d = SettingsSchema::definitions()[ $key ] ?? throw new InvalidArgumentException( 'Unknown setting.' );
			if ( ! current_user_can( $d->capability ) ) {
				return false;
			}$clean = ( $d->sanitize )( $value );
			if ( ! ( ( $d->validate )( $clean ) ) ) {
				throw new InvalidArgumentException( 'Invalid setting value.' );
			}$name     = 'realestate_platform_settings_' . $d->group;
			$o         = (array) get_option( $name, array() );
			$o[ $key ] = $clean;
			return update_option( $name, $o, false );
		} /** @return array<string, mixed> */
		public function sanitizeGroup( string $group, mixed $input ): array {
			if ( ! current_user_can( 'manage_realestate_settings' ) ) {
				return (array) get_option( 'realestate_platform_settings_' . $group, array() );
			}$out = array();
			foreach ( SettingsSchema::definitions() as $d ) {
				if ( $d->group === $group ) {
					$v              = is_array( $input ) && array_key_exists( $d->key, $input ) ? $input[ $d->key ] : $d->default_value;
					$v              = ( $d->sanitize )( $v );
					$out[ $d->key ] = ( $d->validate )( $v ) ? $v : $d->default_value;
				}
			}return $out;
		} /** @return array<string, mixed> */
		private function defaults( string $group ): array {
			$o = array();
			foreach ( SettingsSchema::definitions() as $d ) {
				if ( $d->group === $group ) {
					$o[ $d->key ] = $d->default_value;
				}
			}return $o;}
}
