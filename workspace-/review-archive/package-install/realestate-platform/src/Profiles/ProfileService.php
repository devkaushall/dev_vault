<?php
/** Agent and agency application service. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Profiles;
final class ProfileService {
	private const TYPES = array( 'agent', 'agency' );
	private const META  = array( 'public_phone', 'public_email', 'website', 'license_number', 'office_address' );
	/** @param array<string,mixed> $input */
	public function create( string $type, array $input, int $actor_id ): array|\WP_Error {
		if ( ! $this->typeAllowed( $type ) || ! $this->canCreate( $type, $actor_id ) ) {
			return $this->error( 'profile_forbidden', 403 ); }
		$title = $input['title'] ?? null;
		if ( ! is_string( $title ) || '' === trim( $title ) || strlen( $title ) > 200 ) {
			return $this->error( 'invalid_title', 400 ); }
		$id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'draft',
				'post_title'   => sanitize_text_field( $title ),
				'post_content' => is_string( $input['content'] ?? null ) ? wp_kses_post( $input['content'] ) : '',
				'post_author'  => $actor_id,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id; }
		$this->saveMeta( $id, $input );
		return $this->get( $type, $id, $actor_id, true );
	}
	/** @param array<string,mixed> $input */
	public function update( string $type, int $id, array $input, int $actor_id ): array|\WP_Error {
		$post = $this->owned( $type, $id, $actor_id );
		if ( is_wp_error( $post ) ) {
			return $post; }
		$change = array( 'ID' => $id );
		if ( array_key_exists( 'title', $input ) ) {
			if ( ! is_string( $input['title'] ) || '' === trim( $input['title'] ) || strlen( $input['title'] ) > 200 ) {
				return $this->error( 'invalid_title', 400 );
			} $change['post_title'] = sanitize_text_field( $input['title'] ); }
		if ( array_key_exists( 'content', $input ) ) {
			if ( ! is_string( $input['content'] ) ) {
				return $this->error( 'invalid_content', 400 );
			} $change['post_content'] = wp_kses_post( $input['content'] ); }
		if ( array_key_exists( 'status', $input ) ) {
			if ( ! in_array( $input['status'], array( 'draft', 'publish' ), true ) || ( 'publish' === $input['status'] && ! current_user_can( 'publish_' . $this->plural( $type ) ) ) ) {
				return $this->error( 'invalid_status', 403 );
			} $change['post_status'] = $input['status']; }
		$result = wp_update_post( $change, true );
		if ( is_wp_error( $result ) ) {
			return $result; }
		$this->saveMeta( $id, $input );
		return $this->get( $type, $id, $actor_id, true );
	}
	public function delete( string $type, int $id, int $actor_id ): bool|\WP_Error {
		$post = $this->owned( $type, $id, $actor_id );
		if ( is_wp_error( $post ) ) {
			return $post; }
		if ( 'agency' === $type && get_posts(
			array(
				'post_type'      => array( 'agent', 'property' ),
				'post_status'    => 'any',
				'meta_key'       => 'rep_agency_id',
				'meta_value'     => $id,
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		) ) {
			return $this->error( 'agency_in_use', 409 ); }
		return false !== wp_delete_post( $id, true );
	}
	public function get( string $type, int $id, int $actor_id = 0, bool $include_private = false ): array|\WP_Error {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || $type !== $post->post_type ) {
			return $this->error( 'profile_not_found', 404 ); }
		if ( 'publish' !== $post->post_status && ( ! $include_private || is_wp_error( $this->owned( $type, $id, $actor_id ) ) ) ) {
			return $this->error( 'profile_not_found', 404 ); }
		$data = array(
			'id'        => $id,
			'type'      => $type,
			'title'     => wp_strip_all_tags( $post->post_title ),
			'content'   => wp_kses_post( $post->post_content ),
			'url'       => (string) get_permalink( $id ),
			'avatar_id' => get_post_thumbnail_id( $id ) ? get_post_thumbnail_id( $id ) : null,
		);
		foreach ( self::META as $key ) {
			$data[ $key ] = get_post_meta( $id, 'rep_' . $key, true ) ? get_post_meta( $id, 'rep_' . $key, true ) : null; }
		if ( 'agent' === $type ) {
			$data['agency_id'] = (int) get_post_meta( $id, 'rep_agency_id', true ) ? (int) get_post_meta( $id, 'rep_agency_id', true ) : null; }
		if ( $include_private && ! is_wp_error( $this->owned( $type, $id, $actor_id ) ) ) {
			$data['status']        = $post->post_status;
			$data['private_notes'] = get_post_meta( $id, 'rep_private_notes', true ) ? get_post_meta( $id, 'rep_private_notes', true ) : null; }
		return $data;
	}
	/** @return array{items:list<array<string,mixed>>,total:int} */
	public function listPublic( string $type, int $page = 1, int $per_page = 20, string $search = '' ): array {
		if ( ! $this->typeAllowed( $type ) ) {
			return array(
				'items' => array(),
				'total' => 0,
			); }
		$query = new \WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'paged'          => max( 1, $page ),
				'posts_per_page' => max( 1, min( 100, $per_page ) ),
				's'              => sanitize_text_field( $search ),
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			$item = $this->get( $type, $post->ID );
			if ( is_array( $item ) ) {
				$items[] = $item; }
		}
		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
		);
	}
	public function assignAgency( int $agent_id, int $agency_id, int $actor_id ): bool|\WP_Error {
		if ( is_wp_error( $this->owned( 'agent', $agent_id, $actor_id ) ) ) {
			return $this->error( 'profile_not_found', 404 ); }
		$agency = get_post( $agency_id );
		if ( ! $agency instanceof \WP_Post || 'agency' !== $agency->post_type || ! $this->usable( $agency, $actor_id ) ) {
			return $this->error( 'invalid_agency', 400 ); }
		return false !== update_post_meta( $agent_id, 'rep_agency_id', $agency_id );
	}
	public function removeAgency( int $agent_id, int $actor_id ): bool|\WP_Error {
		if ( is_wp_error( $this->owned( 'agent', $agent_id, $actor_id ) ) ) {
			return $this->error( 'profile_not_found', 404 ); }
		return delete_post_meta( $agent_id, 'rep_agency_id' );
	}
	public function assignProperty( int $property_id, int $agent_id, int $agency_id, int $actor_id ): bool|\WP_Error {
		if ( ! current_user_can( 'edit_post', $property_id ) || get_post_type( $property_id ) !== 'property' ) {
			return $this->error( 'property_forbidden', 403 ); }
		$agent  = get_post( $agent_id );
		$agency = get_post( $agency_id );
		if ( ! $agent instanceof \WP_Post || ! $agency instanceof \WP_Post || 'agent' !== $agent->post_type || 'agency' !== $agency->post_type || ! $this->usable( $agent, $actor_id ) || ! $this->usable( $agency, $actor_id ) ) {
			return $this->error( 'invalid_relationship', 400 ); }
		if ( (int) get_post_meta( $agent_id, 'rep_agency_id', true ) !== $agency_id ) {
			return $this->error( 'invalid_relationship', 400 ); }
		update_post_meta( $property_id, 'rep_agent_id', $agent_id );
		update_post_meta( $property_id, 'rep_agency_id', $agency_id );
		return true;
	}
	public function removeProperty( int $property_id, int $actor_id ): bool|\WP_Error {
		if ( $actor_id < 1 || ! current_user_can( 'edit_post', $property_id ) || 'property' !== get_post_type( $property_id ) ) {
			return $this->error( 'property_forbidden', 403 );
		}
		delete_post_meta( $property_id, 'rep_agent_id' );
		delete_post_meta( $property_id, 'rep_agency_id' );
		return true;
	}
	public function cleanupProfile( int $post_id ): void {
		$type = get_post_type( $post_id );
		if ( 'agent' === $type ) {
			delete_metadata( 'post', 0, 'rep_agent_id', $post_id, true ); }
		if ( 'agency' === $type ) {
			delete_metadata( 'post', 0, 'rep_agency_id', $post_id, true ); }
	}
	/** @param array<string,mixed> $input */
	private function saveMeta( int $id, array $input ): void {
		foreach ( self::META as $key ) {
			if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) ) {
				update_post_meta( $id, 'rep_' . $key, 'website' === $key ? esc_url_raw( $input[ $key ] ) : sanitize_text_field( $input[ $key ] ) ); }
		}
		if ( isset( $input['private_notes'] ) && is_string( $input['private_notes'] ) ) {
			update_post_meta( $id, 'rep_private_notes', sanitize_textarea_field( $input['private_notes'] ) ); }
	}
	private function owned( string $type, int $id, int $actor_id ): \WP_Post|\WP_Error {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || $type !== $post->post_type || ( (int) $post->post_author !== $actor_id && ! current_user_can( 'edit_others_' . $this->plural( $type ) ) ) ) {
			return $this->error( 'profile_not_found', 404 );
		} return $post;
	}
	private function usable( \WP_Post $post, int $actor_id ): bool {
		return 'publish' === $post->post_status || (int) $post->post_author === $actor_id || current_user_can( 'edit_others_' . $this->plural( $post->post_type ) );
	}
	private function canCreate( string $type, int $actor_id ): bool {
		return $actor_id > 0 && current_user_can( 'edit_' . $this->plural( $type ) ); }
	private function plural( string $type ): string {
		return 'agency' === $type ? 'agencies' : 'agents'; }
	private function typeAllowed( string $type ): bool {
		return in_array( $type, self::TYPES, true ); }
	private function error( string $code, int $status ): \WP_Error {
		return new \WP_Error( $code, 'Profile request could not be completed.', array( 'status' => $status ) ); }
}
