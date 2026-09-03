<?php
/** Bounded WordPress cron alert scheduler. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\UserFeatures;
final class AlertScheduler {
	public const HOOK = 'realestate_platform_alerts_run';
	public function __construct( private AlertEvaluator $evaluator ) {}
	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::HOOK );
		}}
	/** @return array{processed:int,failed:int} */
	public function run( int $limit = 25 ): array {
		$limit = max( 1, min( 100, $limit ) );
		global$wpdb;
		$ids       = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}rep_search_alerts WHERE enabled=1 AND next_run_at IS NOT NULL AND next_run_at<=%s ORDER BY next_run_at,id LIMIT %d", current_time( 'mysql', true ), $limit ) );
		$processed = 0;
		$failed    = 0;
		foreach ( $ids as$id ) {
			is_wp_error( $this->evaluator->evaluate( (int) $id ) ) ? ++$failed : ++$processed;
		}return compact( 'processed', 'failed' );}
}
