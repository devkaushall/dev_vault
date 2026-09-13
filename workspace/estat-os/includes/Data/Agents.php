<?php
/**
 * Team member repository.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Data;

use EstatOS\Audit\AuditLog;
use EstatOS\Support\Sanitize;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Agents can optionally be linked to a WordPress login account.
 */
final class Agents {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
	}

	/**
	 * Create or update a team member.
	 *
	 * @param array<string,mixed> $input   Raw input.
	 * @param int                 $post_id Existing ID or 0.
	 * @return int|WP_Error
	 */
	public static function save( array $input, int $post_id = 0 ) {
		$name = Sanitize::title( $input['title'] ?? $input['name'] ?? '' );
		if ( 0 === $post_id && '' === $name ) {
			return new WP_Error( 'estat_invalid_name', __( 'Please enter the person\'s name.', 'estat-os' ) );
		}
		$email = Sanitize::email( $input['email'] ?? $input['_estat_email'] ?? '' );
		if ( isset( $input['email'] ) && '' !== trim( (string) $input['email'] ) && '' === $email ) {
			return new WP_Error( 'estat_invalid_email', __( 'That email address does not look right. Example: name@example.com', 'estat-os' ) );
		}

		$postarr = array(
			'post_type'   => PostTypes::AGENT,
			'post_title'  => $name,
			'post_status' => 'publish',
		);
		if ( isset( $input['bio'] ) ) {
			$postarr['post_content'] = Sanitize::html( $input['bio'] );
		}
		if ( $post_id > 0 ) {
			$postarr['ID'] = $post_id;

			// Snapshot what is there now, before any of it is overwritten.
			Undo::capture( $post_id );
			$result        = wp_update_post( $postarr, true );
		} else {
			$result = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$agent_id = (int) $result;

		foreach ( Meta::agent_schema() as $key => $field ) {
			if ( ! empty( $field['computed'] ) ) {
				continue;
			}
			$short = substr( $key, strlen( '_estat_' ) );
			if ( array_key_exists( $key, $input ) ) {
				$raw = $input[ $key ];
			} elseif ( array_key_exists( $short, $input ) ) {
				$raw = $input[ $short ];
			} else {
				continue;
			}
			$value = Meta::sanitize_value( $raw, $field );
			if ( '' === $value ) {
				delete_post_meta( $agent_id, $key );
			} else {
				update_post_meta( $agent_id, $key, $value );
			}
		}

		if ( isset( $input['photo_id'] ) ) {
			$photo = Sanitize::int( $input['photo_id'] );
			if ( $photo ) {
				set_post_thumbnail( $agent_id, $photo );
			}
		}

		AuditLog::record( $post_id > 0 ? 'agent.updated' : 'agent.created', 'agent', $agent_id, array() );
		return $agent_id;
	}

	/**
	 * Number of published listings for an agent.
	 *
	 * @param int $agent_id Agent ID.
	 * @return int
	 */
	public static function listing_count( int $agent_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'estat_index';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE agent_id = %d AND post_status = 'publish'", $agent_id ) );
	}

	/**
	 * Resolve the agent record linked to a WordPress user.
	 *
	 * @param int $user_id User ID.
	 * @return int Agent post ID or 0.
	 */
	public static function for_user( int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}
		$found = get_posts(
			array(
				'post_type'     => PostTypes::AGENT,
				'numberposts'   => 1,
				'fields'        => 'ids',
				'meta_key'      => '_estat_user_id',
				'meta_value'    => $user_id,
				'no_found_rows' => true,
			)
		);
		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Unassign listings and leads when a team member is removed.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_delete( int $post_id ): void {
		if ( PostTypes::AGENT !== get_post_type( $post_id ) ) {
			return;
		}
		global $wpdb;
		$listings = get_posts(
			array(
				'post_type'   => PostTypes::LISTING,
				'post_status' => 'any',
				'numberposts' => 500,
				'fields'      => 'ids',
				'meta_key'    => '_estat_agent_id',
				'meta_value'  => $post_id,
			)
		);
		foreach ( $listings as $listing_id ) {
			delete_post_meta( (int) $listing_id, '_estat_agent_id' );
			Listings::refresh_derived( (int) $listing_id );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $wpdb->prefix . 'estat_leads', array( 'agent_id' => 0 ), array( 'agent_id' => $post_id ), array( '%d' ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $wpdb->prefix . 'estat_visits', array( 'agent_id' => 0 ), array( 'agent_id' => $post_id ), array( '%d' ), array( '%d' ) );

		AuditLog::record( 'agent.deleted', 'agent', $post_id, array() );
	}

	/**
	 * Public safe representation.
	 *
	 * @param int $agent_id Agent ID.
	 * @return array<string,mixed>
	 */
	public static function to_array( int $agent_id ): array {
		$post = get_post( $agent_id );
		if ( ! $post || PostTypes::AGENT !== $post->post_type ) {
			return array();
		}
		return array(
			'id'       => $agent_id,
			'name'     => $post->post_title,
			'role'     => (string) get_post_meta( $agent_id, '_estat_role', true ),
			'phone'    => (string) get_post_meta( $agent_id, '_estat_phone', true ),
			'whatsapp' => (string) get_post_meta( $agent_id, '_estat_whatsapp', true ),
			'email'    => (string) get_post_meta( $agent_id, '_estat_email', true ),
			'photo'    => get_the_post_thumbnail_url( $agent_id, 'medium' ) ?: '',
			'url'      => get_permalink( $agent_id ),
			'listings' => self::listing_count( $agent_id ),
		);
	}
}
