<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Core;

use Mayfair\RealEstatePlatform\Capabilities\CapabilityManager;
use Mayfair\RealEstatePlatform\Capabilities\ContentCapabilityManager;
use Mayfair\RealEstatePlatform\Database\WpDatabase;
use Mayfair\RealEstatePlatform\Logging\OptionLogger;
use Mayfair\RealEstatePlatform\Migration\MigrationRunner;
use Mayfair\RealEstatePlatform\Settings\SettingsManager;
final class Lifecycle {
	public static function maybeUpgrade(): void {
		if ( ! defined( 'REALESTATE_PLATFORM_DIR' ) || ! function_exists( 'get_option' ) ) {
			return;
		}
		$current = (string) get_option( 'realestate_platform_db_version', '0' );
		if ( version_compare( $current, REALESTATE_PLATFORM_DB_VERSION, '>=' ) ) {
			return;
		}
		Environment::assertSupported();
		( new MigrationRunner( new WpDatabase(), new OptionLogger(), REALESTATE_PLATFORM_DIR . 'migrations' ) )->run();
		update_option( 'realestate_platform_version', REALESTATE_PLATFORM_VERSION, false );
	}

	public static function activate(): void {
		Environment::assertSupported();
		$logger = new OptionLogger();
		( new MigrationRunner( new WpDatabase(), $logger, REALESTATE_PLATFORM_DIR . 'migrations' ) )->run();
		( new CapabilityManager() )->register();
		( new ContentCapabilityManager() )->register();
		( new SettingsManager() )->initializeDefaults();
		update_option( 'realestate_platform_version', REALESTATE_PLATFORM_VERSION, false );
	} public static function deactivate(): void {
		wp_clear_scheduled_hook( 'realestate_platform_maintenance' );
		wp_clear_scheduled_hook( 'realestate_platform_lead_notifications' ); }
}
