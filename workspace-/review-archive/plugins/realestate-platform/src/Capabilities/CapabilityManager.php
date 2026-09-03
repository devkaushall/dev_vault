<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Capabilities;

use Mayfair\RealEstatePlatform\Contracts\CapabilityInterface;

final class CapabilityManager implements CapabilityInterface {
	public const CAPS = array( 'manage_realestate', 'manage_realestate_settings', 'view_realestate_diagnostics', 'manage_realestate_migrations', 'manage_realestate_imports', 'manage_realestate_exports' );
	public function register(): void {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}foreach ( self::CAPS as $cap ) {
			$role->add_cap( $cap );
		}} public function currentUserCan( string $capability ): bool {
		return in_array( $capability, self::CAPS, true ) && current_user_can( $capability );}
}
