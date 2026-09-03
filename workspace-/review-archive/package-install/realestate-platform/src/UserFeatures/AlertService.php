<?php
/** User-owned search alert configuration. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\UserFeatures;
final class AlertService {
	public const FREQUENCIES = array( 'daily', 'weekly' );
	/** @return array<string,mixed>|\WP_Error */
	public function save( int $user_id, int $saved_search_id, string $frequency, bool $enabled ): array|\WP_Error {
		if ( ! in_array( $frequency, self::FREQUENCIES, true ) ) {
			return $this->error( 'invalid_frequency', 'Invalid alert frequency.', 400 );
		}global$wpdb;
		$search = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}rep_saved_searches WHERE id=%d AND user_id=%d", $saved_search_id, $user_id ) );
		if ( ! $search ) {
			return $this->error( 'saved_search_not_found', 'Saved search not found.', 404 );
		}$now = current_time( 'mysql', true );
		$next = $enabled ? gmdate( 'Y-m-d H:i:s', time() + ( 'daily' === $frequency ? DAY_IN_SECONDS : WEEK_IN_SECONDS ) ) : null;
		$id   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}rep_search_alerts WHERE saved_search_id=%d AND user_id=%d", $saved_search_id, $user_id ) );
		$data = array(
			'frequency'   => $frequency,
			'enabled'     => (int) $enabled,
			'next_run_at' => $next,
			'updated_at'  => $now,
		);
		if ( $id ) {
			$wpdb->update(
				$wpdb->prefix . 'rep_search_alerts',
				$data,
				array(
					'id'      => (int) $id,
					'user_id' => $user_id,
				)
			);
		} else {
			$data += array(
				'saved_search_id' => $saved_search_id,
				'user_id'         => $user_id,
				'last_run_at'     => null,
				'notified_json'   => '[]',
				'failure_count'   => 0,
				'created_at'      => $now,
			);
			$wpdb->insert( $wpdb->prefix . 'rep_search_alerts', $data );
			$id = $wpdb->insert_id;
		}return $this->get( $user_id, (int) $id );}
	/** @return array<string,mixed>|\WP_Error */
	public function get( int $user_id, int $id ): array|\WP_Error {
		global$wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT id,saved_search_id,frequency,enabled,last_run_at,next_run_at,failure_count,created_at,updated_at FROM {$wpdb->prefix}rep_search_alerts WHERE id=%d AND user_id=%d", $id, $user_id ), ARRAY_A );
		if ( ! $r ) {
			return $this->error( 'alert_not_found', 'Alert not found.', 404 );
		}$r['id']             = (int) $r['id'];
		$r['saved_search_id'] = (int) $r['saved_search_id'];
		$r['enabled']         = (bool) $r['enabled'];
		$r['failure_count']   = (int) $r['failure_count'];
		return $r;}
	public function delete( int $user_id, int $id ): bool|\WP_Error {
		$x = $this->get( $user_id, $id );
		if ( is_wp_error( $x ) ) {
			return $x;
		}global$wpdb;
		return false !== $wpdb->delete(
			$wpdb->prefix . 'rep_search_alerts',
			array(
				'id'      => $id,
				'user_id' => $user_id,
			),
			array( '%d', '%d' )
		);}
	private function error( string $c, string $m, int $s ): \WP_Error {
		return new \WP_Error( $c, $m, array( 'status' => $s ) );}
}
