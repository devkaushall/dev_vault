<?php
/** Read-only Phase-5 user-state health. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Diagnostics;

use Mayfair\RealEstatePlatform\Contracts\DiagnosticCheckInterface;
final class UserFeaturesCheck implements DiagnosticCheckInterface {
	public function name(): string {
		return 'User features';}
	public function run(): DiagnosticResult {
		global$wpdb;
		$tables = array();
		foreach ( array( 'rep_favorites', 'rep_saved_searches', 'rep_search_alerts' )as$s ) {
			$t            = $wpdb->prefix . $s;
			$tables[ $s ] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) ) === $t;
		}if ( in_array( false, $tables, true ) ) {
			return new DiagnosticResult( $this->name(), DiagnosticResult::FAIL, 'Phase-5 tables are missing.', array( 'tables' => $tables ), 'Run activation migrations.' );
		}$favorites = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}rep_favorites f LEFT JOIN {$wpdb->users} u ON u.ID=f.user_id LEFT JOIN {$wpdb->posts} p ON p.ID=f.post_id WHERE u.ID IS NULL OR p.ID IS NULL" );
		$alerts     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}rep_search_alerts a LEFT JOIN {$wpdb->prefix}rep_saved_searches s ON s.id=a.saved_search_id AND s.user_id=a.user_id WHERE s.id IS NULL" );
		$failed     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}rep_search_alerts WHERE failure_count>0" );
		$invalid    = 0;
		foreach ( $wpdb->get_col( "SELECT criteria_json FROM {$wpdb->prefix}rep_saved_searches" )as$json ) {
			if ( ! is_array( json_decode( $json, true ) ) ) {
				++$invalid;
			}
		}
		$scheduler      = wp_next_scheduled( 'realestate_platform_alerts_run' );
		$schema_current = get_option( 'realestate_platform_db_version' ) === REALESTATE_PLATFORM_DB_VERSION;
		$details        = array(
			'tables'                 => $tables,
			'schema_current'         => $schema_current,
			'orphaned_favorites'     => $favorites,
			'invalid_saved_searches' => $invalid,
			'broken_alerts'          => $alerts,
			'failed_alerts'          => $failed,
			'scheduler'              => $scheduler ? $scheduler : null,
		);
		$status         = ( ! $schema_current || ( $favorites + $invalid + $alerts ) > 0 ) ? DiagnosticResult::FAIL : ( $failed > 0 || ! $scheduler ? DiagnosticResult::WARN : DiagnosticResult::PASS );
		return new DiagnosticResult( $this->name(), $status, 'User-feature state health.', $details, 'Review failed or invalid records; diagnostics does not repair data.' );}
}
