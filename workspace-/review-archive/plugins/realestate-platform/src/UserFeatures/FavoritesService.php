<?php
/** User-owned favorite operations. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\UserFeatures;
final class FavoritesService {
	public function add( int $user_id, int $post_id ): bool|\WP_Error {
		if ( ! $this->validUser( $user_id ) ) {
			return $this->error( 'authentication_required', 'Authentication is required.', 401 );
		}if ( ! $this->publicProperty( $post_id ) ) {
			return $this->error( 'invalid_property', 'Property is not eligible.', 400 );
		}global$wpdb;
		$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->prefix}rep_favorites (user_id,post_id,created_at) VALUES (%d,%d,%s)", $user_id, $post_id, current_time( 'mysql', true ) ) );
		return false === $result ? $this->error( 'favorite_failed', 'Favorite could not be saved.', 500 ) : true;}
	public function remove( int $user_id, int $post_id ): bool|\WP_Error {
		if ( ! $this->validUser( $user_id ) ) {
			return $this->error( 'authentication_required', 'Authentication is required.', 401 );
		}global$wpdb;
		return false !== $wpdb->delete(
			$wpdb->prefix . 'rep_favorites',
			array(
				'user_id' => $user_id,
				'post_id' => $post_id,
			),
			array( '%d', '%d' )
		);}
	public function toggle( int $user_id, int $post_id ): bool|\WP_Error {
		if ( $this->contains( $user_id, $post_id ) ) {
			$x = $this->remove( $user_id, $post_id );
			return is_wp_error( $x ) ? $x : false;
		}$x = $this->add( $user_id, $post_id );
		return is_wp_error( $x ) ? $x : true;}
	public function contains( int $user_id, int $post_id ): bool {
		global$wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->prefix}rep_favorites WHERE user_id=%d AND post_id=%d", $user_id, $post_id ) );}
	/** @return array{items:list<int>,total:int,page:int,per_page:int}|\WP_Error */
	public function list( int $user_id, int $page = 1, int $per_page = 20 ): array|\WP_Error {
		if ( ! $this->validUser( $user_id ) ) {
			return $this->error( 'authentication_required', 'Authentication is required.', 401 );
		}if ( $page < 1 || $per_page < 1 || $per_page > 100 ) {
			return $this->error( 'invalid_pagination', 'Invalid pagination.', 400 );
		}global$wpdb;
		$from = " FROM {$wpdb->prefix}rep_favorites f JOIN {$wpdb->posts} p ON p.ID=f.post_id WHERE f.user_id=%d AND p.post_type='property' AND p.post_status='publish'";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Internal table fragment; value is prepared.
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*)' . $from, $user_id ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Internal table fragment; all values are prepared.
			$items = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT f.post_id' . $from . ' ORDER BY f.created_at DESC,f.post_id DESC LIMIT %d OFFSET %d', $user_id, $per_page, ( $page - 1 ) * $per_page ) ) );
		return compact( 'items', 'total', 'page', 'per_page' );}
	public function cleanupUser( int $user_id ): void {
		global$wpdb;
		$wpdb->delete( $wpdb->prefix . 'rep_favorites', array( 'user_id' => $user_id ), array( '%d' ) );}
	public function cleanupProperty( int $post_id ): void {
		global$wpdb;
		$wpdb->delete( $wpdb->prefix . 'rep_favorites', array( 'post_id' => $post_id ), array( '%d' ) );}
	private function validUser( int $id ): bool {
		return $id > 0 && get_user_by( 'id', $id ) instanceof \WP_User;}
	private function publicProperty( int $id ): bool {
		$p = get_post( $id );
		return $p instanceof \WP_Post && 'property' === $p->post_type && 'publish' === $p->post_status;}
	private function error( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );}
}
