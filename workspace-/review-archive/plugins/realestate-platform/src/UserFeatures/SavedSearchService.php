<?php
/** User-owned canonical saved searches. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\UserFeatures;

use Mayfair\RealEstatePlatform\Search\SearchCriteria;
final class SavedSearchService {
	public const MAX_PER_USER = 25;
	/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
	public function create( int $user_id, string $title, array $input ): array|\WP_Error {
		if ( ! $this->validUser( $user_id ) ) {
			return $this->error( 'authentication_required', 'Authentication is required.', 401 );
		}$title = sanitize_text_field( $title );
		if ( '' === $title || mb_strlen( $title ) > 120 ) {
			return $this->error( 'invalid_title', 'Invalid saved-search title.', 400 );
		}global$wpdb;
		if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}rep_saved_searches WHERE user_id=%d", $user_id ) ) >= self::MAX_PER_USER ) {
			return $this->error( 'saved_search_limit', 'Saved-search limit reached.', 429 );
		}$normalized = $this->normalize( $input );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}rep_saved_searches WHERE user_id=%d AND criteria_hash=%s", $user_id, $normalized['hash'] ) ) ) {
			return $this->error( 'duplicate_search', 'This search is already saved.', 409 );
		}
		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			$wpdb->prefix . 'rep_saved_searches',
			array(
				'user_id'       => $user_id,
				'title'         => $title,
				'criteria_json' => $normalized['json'],
				'criteria_hash' => $normalized['hash'],
				'enabled'       => 1,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( false === $ok ) {
			return $this->error( 'duplicate_search', 'This search is already saved.', 409 );
		}return $this->get( $user_id, (int) $wpdb->insert_id );}
	/** @return array<string,mixed>|\WP_Error */
	public function get( int $user_id, int $id ): array|\WP_Error {
		global$wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rep_saved_searches WHERE id=%d AND user_id=%d", $id, $user_id ), ARRAY_A );
		return is_array( $row ) ? $this->publicRow( $row ) : $this->error( 'saved_search_not_found', 'Saved search not found.', 404 );}
	/** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}|\WP_Error */
	public function list( int $user_id, int $page = 1, int $per_page = 20 ): array|\WP_Error {
		if ( ! $this->validUser( $user_id ) || $page < 1 || $per_page < 1 || $per_page > 100 ) {
			return $this->error( 'invalid_request', 'Invalid saved-search request.', 400 );
		}global$wpdb;
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}rep_saved_searches WHERE user_id=%d", $user_id ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rep_saved_searches WHERE user_id=%d ORDER BY updated_at DESC,id DESC LIMIT %d OFFSET %d", $user_id, $per_page, ( $page - 1 ) * $per_page ), ARRAY_A );
		return array(
			'items'    => array_map( fn( $r )=>$this->publicRow( $r ), $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);}
	/** @param array<string,mixed> $criteria @return array<string,mixed>|\WP_Error */
	public function update( int $user_id, int $id, string $title, array $criteria, bool $enabled ): array|\WP_Error {
		$existing = $this->get( $user_id, $id );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}$title = sanitize_text_field( $title );
		if ( '' === $title || mb_strlen( $title ) > 120 ) {
			return $this->error( 'invalid_title', 'Invalid saved-search title.', 400 );
		}$normalized = $this->normalize( $criteria );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}global$wpdb;
		$ok = $wpdb->update(
			$wpdb->prefix . 'rep_saved_searches',
			array(
				'title'         => $title,
				'criteria_json' => $normalized['json'],
				'criteria_hash' => $normalized['hash'],
				'enabled'       => (int) $enabled,
				'updated_at'    => current_time( 'mysql', true ),
			),
			array(
				'id'      => $id,
				'user_id' => $user_id,
			)
		);
		return false === $ok ? $this->error( 'saved_search_update_failed', 'Saved search could not be updated.', 409 ) : $this->get( $user_id, $id );}
	public function delete( int $user_id, int $id ): bool|\WP_Error {
		$existing = $this->get( $user_id, $id );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}global$wpdb;
		$wpdb->delete(
			$wpdb->prefix . 'rep_search_alerts',
			array(
				'saved_search_id' => $id,
				'user_id'         => $user_id,
			),
			array( '%d', '%d' )
		);
		return false !== $wpdb->delete(
			$wpdb->prefix . 'rep_saved_searches',
			array(
				'id'      => $id,
				'user_id' => $user_id,
			),
			array( '%d', '%d' )
		);}
	public function cleanupUser( int $user_id ): void {
		global$wpdb;
		$wpdb->delete( $wpdb->prefix . 'rep_search_alerts', array( 'user_id' => $user_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'rep_saved_searches', array( 'user_id' => $user_id ), array( '%d' ) );}
	/** @param array<string,mixed> $input @return array{json:string,hash:string}|\WP_Error */
	private function normalize( array $input ): array|\WP_Error {
		try {
			$canonical = SearchCriteria::fromArray( $input )->canonical();
			$json      = (string) wp_json_encode( $canonical, JSON_UNESCAPED_SLASHES );
			if ( strlen( $json ) > 4096 ) {
				return $this->error( 'criteria_too_large', 'Saved-search criteria is too large.', 400 );
			}return array(
				'json' => $json,
				'hash' => hash( 'sha256', $json ),
			);
		} catch ( \InvalidArgumentException $e ) {
			return $this->error( 'invalid_criteria', $e->getMessage(), 400 );}}
	/** @param array<string,mixed> $row @return array<string,mixed> */
	private function publicRow( array $row ): array {
		return array(
			'id'            => (int) $row['id'],
			'title'         => $row['title'],
			'criteria'      => json_decode( $row['criteria_json'], true ),
			'criteria_hash' => $row['criteria_hash'],
			'enabled'       => (bool) $row['enabled'],
			'created_at'    => $row['created_at'],
			'updated_at'    => $row['updated_at'],
		);}
	private function validUser( int $id ): bool {
		return $id > 0 && get_user_by( 'id', $id ) instanceof \WP_User;}
	private function error( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );}
}
