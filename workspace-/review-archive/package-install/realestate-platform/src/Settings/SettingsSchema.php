<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Settings;

final class SettingsSchema {
	/** @return array<string, SettingDefinition> */
	public static function definitions(): array {
		$bool = static fn( $v )=> (bool) $v;
		$int  = static fn( $v )=>max( 1, min( 3650, (int) $v ) );
		return array(
			'operating_mode'      => new SettingDefinition( 'operating_mode', 'general', 'string', 'compatibility', static fn( $v )=>sanitize_key( (string) $v ), static fn( $v )=>in_array( $v, array( 'standalone', 'compatibility', 'migration' ), true ), 'manage_realestate_settings', 'Platform operating mode.' ),
			'log_retention_days'  => new SettingDefinition( 'log_retention_days', 'performance', 'integer', 30, $int, static fn( $v )=>is_int( $v ) && $v >= 1 && $v <= 3650, 'manage_realestate_settings', 'Log retention in days.' ),
			'privacy_enabled'     => new SettingDefinition( 'privacy_enabled', 'privacy', 'boolean', true, $bool, static fn( $v )=>is_bool( $v ), 'manage_realestate_settings', 'Enable privacy integration.' ),
			'purge_on_uninstall'  => new SettingDefinition( 'purge_on_uninstall', 'advanced', 'boolean', false, $bool, static fn( $v )=>is_bool( $v ), 'manage_realestate_settings', 'Delete plugin-owned data on uninstall.' ),
			'map_provider'        => new SettingDefinition( 'map_provider', 'general', 'string', 'openstreetmap', static fn( $v )=>sanitize_key( (string) $v ), static fn( $v )=>in_array( $v, array( 'openstreetmap', 'none' ), true ), 'manage_realestate_settings', 'Public map rendering provider.' ),
			'geocoder_provider'   => new SettingDefinition( 'geocoder_provider', 'general', 'string', 'none', static fn( $v )=>sanitize_key( (string) $v ), static fn( $v )=>'none' === $v, 'manage_realestate_settings', 'Server-side geocoder provider.' ),
			'coordinate_privacy'  => new SettingDefinition( 'coordinate_privacy', 'privacy', 'string', 'exact', static fn( $v )=>sanitize_key( (string) $v ), static fn( $v )=>in_array( $v, array( 'exact', 'rounded', 'approximate', 'hidden' ), true ), 'manage_realestate_settings', 'Default public coordinate precision.' ),
			'maximum_geo_radius'  => new SettingDefinition( 'maximum_geo_radius', 'performance', 'integer', 500, static fn( $v )=>max( 1, min( 500, (int) $v ) ), static fn( $v )=>is_int( $v ) && $v >= 1 && $v <= 500, 'manage_realestate_settings', 'Maximum radius-search distance.' ),
			'maximum_map_results' => new SettingDefinition( 'maximum_map_results', 'performance', 'integer', 100, static fn( $v )=>max( 1, min( 500, (int) $v ) ), static fn( $v )=>is_int( $v ) && $v >= 1 && $v <= 500, 'manage_realestate_settings', 'Maximum markers per viewport response.' ),
			'marker_clustering'   => new SettingDefinition( 'marker_clustering', 'performance', 'boolean', true, $bool, static fn( $v )=>is_bool( $v ), 'manage_realestate_settings', 'Enable client provider clustering.' ),
			'geocode_cache_ttl'   => new SettingDefinition( 'geocode_cache_ttl', 'performance', 'integer', 86400, static fn( $v )=>max( 60, min( 2592000, (int) $v ) ), static fn( $v )=>is_int( $v ) && $v >= 60 && $v <= 2592000, 'manage_realestate_settings', 'Geocoding cache lifetime in seconds.' ),
		);}
}
