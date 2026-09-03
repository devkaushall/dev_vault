<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Admin;

use Mayfair\RealEstatePlatform\Core\ServiceRegistry;
use Mayfair\RealEstatePlatform\Compatibility\CompatibilityDetector;
use Mayfair\RealEstatePlatform\Diagnostics\DiagnosticsRunner;
use Mayfair\RealEstatePlatform\Settings\SettingsManager;

final class AdminModule {
	public function __construct( private ServiceRegistry $s ) {} public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action(
			'admin_init',
			function (): void {
				$settings = $this->s->get( 'settings' );
				assert( $settings instanceof SettingsManager );
				$settings->register();
			}
		);
	} public function menu(): void {
		add_menu_page( __( 'Real Estate', 'realestate-platform' ), __( 'Real Estate', 'realestate-platform' ), 'manage_realestate', 'realestate-platform', array( $this, 'dashboard' ), 'dashicons-admin-home', 26 );
		add_submenu_page( 'realestate-platform', __( 'Dashboard', 'realestate-platform' ), __( 'Dashboard', 'realestate-platform' ), 'manage_realestate', 'realestate-platform', array( $this, 'dashboard' ) );
		add_submenu_page( 'realestate-platform', __( 'Diagnostics', 'realestate-platform' ), __( 'Diagnostics', 'realestate-platform' ), 'view_realestate_diagnostics', 'realestate-platform-diagnostics', array( $this, 'diagnostics' ) );
		add_submenu_page( 'realestate-platform', __( 'Settings', 'realestate-platform' ), __( 'Settings', 'realestate-platform' ), 'manage_realestate_settings', 'realestate-platform-settings', array( $this, 'settings' ) );
	} public function dashboard(): void {
		$compatibility = $this->s->get( 'compatibility' );
		$diagnostics   = $this->s->get( 'diagnostics' );
		assert( $compatibility instanceof CompatibilityDetector );
		assert( $diagnostics instanceof DiagnosticsRunner );
		$c = $compatibility->snapshot();
		$d = $diagnostics->summary();
		echo '<div class="wrap"><h1>' . esc_html__( 'Real Estate Platform', 'realestate-platform' ) . '</h1><table class="widefat striped"><tbody>';
		foreach ( array(
			__( 'Platform Version', 'realestate-platform' ) => REALESTATE_PLATFORM_VERSION,
			__( 'WordPress Version', 'realestate-platform' ) => $c['wordpress'],
			__( 'PHP Version', 'realestate-platform' )    => $c['php'],
			__( 'Schema Version', 'realestate-platform' ) => get_option( 'realestate_platform_db_version', '0' ),
			__( 'Operating Mode', 'realestate-platform' ) => $compatibility->recommendedMode()->value,
			__( 'System Health', 'realestate-platform' )  => wp_json_encode( $d ),
		) as $k => $v ) {
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . esc_html( (string) $v ) . '</td></tr>';
		}echo '</tbody></table></div>';
	} public function diagnostics(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Real Estate Diagnostics', 'realestate-platform' ) . '</h1>';
		$diagnostics = $this->s->get( 'diagnostics' );
		assert( $diagnostics instanceof DiagnosticsRunner );
		foreach ( $diagnostics->run() as $r ) {
			echo '<h2>' . esc_html( $r->status . ' — ' . $r->name ) . '</h2><p>' . esc_html( $r->message ) . '</p><p>' . esc_html( $r->remediation ) . '</p>';
		}echo '</div>';
	} public function settings(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Real Estate Settings', 'realestate-platform' ) . '</h1><p>' . esc_html__( 'Foundation settings are registered through the WordPress Settings API. A field UI will be expanded only as modules require it.', 'realestate-platform' ) . '</p></div>';}
}
