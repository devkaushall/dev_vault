<?php
/**
 * Least-privilege Phase 2 editorial capabilities.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Capabilities;

final class ContentCapabilityManager {
	/** @return list<string> */
	public static function capabilities(): array {
		$caps = array( 'manage_properties', 'manage_projects', 'manage_insights', 'manage_agents', 'manage_agencies', 'manage_realestate_fields', 'manage_realestate_locations', 'manage_leads', 'view_leads', 'edit_leads', 'assign_leads', 'manage_forms', 'manage_site_visits', 'view_site_visits' );
		foreach ( array( 'properties', 'projects', 'insights', 'agents', 'agencies' ) as $plural ) {
			foreach ( array( 'edit_', 'edit_others_', 'edit_private_', 'edit_published_', 'publish_', 'read_private_', 'delete_', 'delete_private_', 'delete_published_', 'delete_others_' ) as $prefix ) {
				$caps[] = $prefix . $plural;
			}
		}
		return $caps;
	}

	public function register(): void {
		$administrator = get_role( 'administrator' );
		if ( ! $administrator ) {
			return;
		}
		foreach ( self::capabilities() as $capability ) {
			$administrator->add_cap( $capability );
		}
	}
}
