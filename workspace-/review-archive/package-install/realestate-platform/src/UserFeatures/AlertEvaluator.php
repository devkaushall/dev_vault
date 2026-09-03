<?php
/** Bounded saved-search evaluator using the canonical SearchEngine. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\UserFeatures;

use Mayfair\RealEstatePlatform\Search\SearchCriteria;
use Mayfair\RealEstatePlatform\Search\SearchEngine;
final class AlertEvaluator {
	public function __construct( private SearchEngine $engine, private NotificationProviderInterface $notifications ) {}
	/** @return array{matched:int,new:int,sent:bool}|\WP_Error */
	public function evaluate( int $alert_id ): array|\WP_Error {
		global$wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT a.*,s.criteria_json,s.title,s.enabled search_enabled FROM {$wpdb->prefix}rep_search_alerts a JOIN {$wpdb->prefix}rep_saved_searches s ON s.id=a.saved_search_id AND s.user_id=a.user_id WHERE a.id=%d", $alert_id ), ARRAY_A );
		if ( ! $row || ! (bool) $row['enabled'] || ! (bool) $row['search_enabled'] || ! get_user_by( 'id', (int) $row['user_id'] ) ) {
			return new \WP_Error( 'alert_unavailable', 'Alert is unavailable.', array( 'status' => 404 ) );
		}$input = json_decode( $row['criteria_json'], true );
		if ( ! is_array( $input ) ) {
			return $this->failure( $row, 'Invalid saved criteria.' );
		}$input['page']    = 1;
		$input['per_page'] = 100;
		try {
			$page = $this->engine->execute( SearchCriteria::fromArray( $input ) );
		} catch ( \Throwable $e ) {
			return $this->failure( $row, 'Alert evaluation failed.' );
		}$ids = array();
		foreach ( $page->results as$item ) {
			$ids[] = (int) $item->jsonSerialize()['id'];
		}$previous = array_values( array_filter( array_map( 'absint', (array) json_decode( $row['notified_json'], true ) ) ) );
		$new       = array_values( array_diff( $ids, $previous ) );
		$sent      = ! $new || $this->notifications->send( (int) $row['user_id'], (string) $row['title'], array_slice( $new, 0, 100 ) );
		if ( ! $sent ) {
			return $this->failure( $row, 'Notification delivery failed.' );
		}$frequency = 'daily' === $row['frequency'] ? DAY_IN_SECONDS : WEEK_IN_SECONDS;
		$wpdb->update(
			$wpdb->prefix . 'rep_search_alerts',
			array(
				'last_run_at'   => current_time( 'mysql', true ),
				'next_run_at'   => gmdate( 'Y-m-d H:i:s', time() + $frequency ),
				'notified_json' => (string) wp_json_encode( array_slice( $ids, 0, 100 ) ),
				'failure_count' => 0,
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => $alert_id )
		);
		return array(
			'matched' => count( $ids ),
			'new'     => count( $new ),
			'sent'    => (bool) $new,
		);}
	private function failure( array $row, string $message ): \WP_Error {
		global$wpdb;
		$fail = min( 10, (int) $row['failure_count'] + 1 );
		$wpdb->update(
			$wpdb->prefix . 'rep_search_alerts',
			array(
				'failure_count' => $fail,
				'next_run_at'   => gmdate( 'Y-m-d H:i:s', time() + min( DAY_IN_SECONDS, 300 * ( 2 ** $fail ) ) ),
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $row['id'] )
		);
		return new \WP_Error( 'alert_failed', $message, array( 'status' => 500 ) );}
}
