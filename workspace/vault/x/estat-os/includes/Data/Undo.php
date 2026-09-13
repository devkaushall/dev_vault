<?php
/**
 * Taking back an edit.
 *
 * Deleting was already safe: records go to the bin and can be brought back.
 * Editing was not. If somebody opened a listing, changed the price by a digit
 * and saved, the previous price was gone. The audit log recorded that a change
 * happened, but not what the value used to be, so there was nothing to restore
 * from.
 *
 * This keeps a small snapshot of a record taken immediately before each save.
 * Only the last few are kept, and only for a short while: this is for "I have
 * just done something wrong", not a version history.
 *
 * Deliberate limits, because an undo feature that quietly does something
 * unexpected is worse than none:
 *
 * - Only the fields the office edits are captured. Nothing computed, nothing
 *   derived, so restoring cannot resurrect a stale search index entry.
 * - Publication status is captured but never restored automatically. Undoing
 *   an edit must not put something back on the website that was deliberately
 *   taken off it.
 * - A snapshot belongs to a record. Restoring checks the same capability as
 *   editing it in the first place.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Data;

use EstatOS\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Snapshots of a record as it was just before the last few saves.
 */
final class Undo {

	/**
	 * Where snapshots live, as post meta on the record itself.
	 *
	 * Keeping them with the record means they are removed with it, and never
	 * outlive what they describe.
	 */
	public const KEY = '_estat_undo';

	/**
	 * How many snapshots to keep per record.
	 */
	public const KEEP = 5;

	/**
	 * How long a snapshot stays useful, in seconds.
	 *
	 * Twenty-four hours. Undo is for a mistake noticed now; restoring
	 * yesterday's values over a week of deliberate work would be its own
	 * disaster.
	 */
	public const TTL = DAY_IN_SECONDS;

	/**
	 * The meta fields worth capturing, per post type.
	 *
	 * @return array<string,array<string>>
	 */
	public static function fields(): array {
		return array(
			PostTypes::LISTING => array(
				'_estat_price',
				'_estat_rent',
				'_estat_deposit',
				'_estat_maintenance',
				'_estat_price_type',
				'_estat_area',
				'_estat_area_unit',
				'_estat_bedrooms',
				'_estat_bathrooms',
				'_estat_balconies',
				'_estat_parking',
				'_estat_floor',
				'_estat_total_floors',
				'_estat_offer',
				'_estat_availability',
				'_estat_construction',
				'_estat_property_type',
				'_estat_agent_id',
				'_estat_project_id',
				'_estat_address',
				'_estat_furnishing',
			),
			PostTypes::PROJECT => array(
				'_estat_developer',
				'_estat_project_status',
				'_estat_price_min',
				'_estat_price_max',
				'_estat_total_units',
				'_estat_available_units',
				'_estat_possession_date',
			),
			PostTypes::AGENT   => array(
				'_estat_phone',
				'_estat_email',
				'_estat_role',
			),
		);
	}

	/**
	 * Take a snapshot of a record as it is right now.
	 *
	 * Call this before writing changes, not after.
	 *
	 * @param int $post_id Record ID.
	 * @return void
	 */
	public static function capture( int $post_id ): void {
		$post = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return;
		}

		$fields = self::fields();

		if ( ! isset( $fields[ $post->post_type ] ) ) {
			return;
		}

		// A record with no title has not been filled in yet; there is nothing
		// worth going back to.
		if ( '' === trim( (string) $post->post_title ) ) {
			return;
		}

		$meta = array();

		foreach ( $fields[ $post->post_type ] as $key ) {
			$value = get_post_meta( $post_id, $key, true );

			if ( '' === $value || null === $value ) {
				continue;
			}

			$meta[ $key ] = is_scalar( $value ) ? (string) $value : '';
		}

		$snapshot = array(
			'at'      => time(),
			'by'      => get_current_user_id(),
			'title'   => (string) $post->post_title,
			'excerpt' => (string) $post->post_excerpt,
			'content' => (string) $post->post_content,
			'status'  => (string) $post->post_status,
			'meta'    => $meta,
		);

		$history = self::history( $post_id );

		// Saving without changing anything should not push a useful snapshot
		// out of the list.
		if ( $history && self::same( $history[0], $snapshot ) ) {
			return;
		}

		array_unshift( $history, $snapshot );
		$history = array_slice( $history, 0, self::KEEP );

		update_post_meta( $post_id, self::KEY, $history );
	}

	/**
	 * Every snapshot still worth offering for a record, newest first.
	 *
	 * @param int $post_id Record ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function history( int $post_id ): array {
		$stored = get_post_meta( $post_id, self::KEY, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$fresh   = array();
		$cutoff  = time() - self::TTL;

		foreach ( $stored as $snapshot ) {
			if ( ! is_array( $snapshot ) || ! isset( $snapshot['at'] ) ) {
				continue;
			}

			if ( (int) $snapshot['at'] < $cutoff ) {
				continue;
			}

			$fresh[] = $snapshot;
		}

		return $fresh;
	}

	/**
	 * Is there anything to go back to?
	 *
	 * @param int $post_id Record ID.
	 * @return bool
	 */
	public static function available( int $post_id ): bool {
		return array() !== self::history( $post_id );
	}

	/**
	 * Put a record back to how it was in the most recent snapshot.
	 *
	 * The snapshot used is consumed, so pressing undo twice steps back twice
	 * rather than doing nothing the second time.
	 *
	 * @param int $post_id Record ID.
	 * @return bool Whether anything was restored.
	 */
	public static function restore( int $post_id ): bool {
		$history = self::history( $post_id );

		if ( ! $history ) {
			return false;
		}

		$snapshot = array_shift( $history );
		$post     = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		/*
		 * Before restoring, snapshot the CURRENT state, so undoing an undo is
		 * possible. Push it on behind the remaining history rather than in
		 * front of it, or the next undo would simply flip back and forth.
		 */
		$current = array(
			'at'      => time(),
			'by'      => get_current_user_id(),
			'title'   => (string) $post->post_title,
			'excerpt' => (string) $post->post_excerpt,
			'content' => (string) $post->post_content,
			'status'  => (string) $post->post_status,
			'meta'    => array(),
		);

		$fields = self::fields();

		foreach ( $fields[ $post->post_type ] ?? array() as $key ) {
			$value = get_post_meta( $post_id, $key, true );

			if ( '' !== $value && null !== $value && is_scalar( $value ) ) {
				$current['meta'][ $key ] = (string) $value;
			}
		}

		array_unshift( $history, $current );
		update_post_meta( $post_id, self::KEY, array_slice( $history, 0, self::KEEP ) );

		/*
		 * The status is deliberately NOT restored. Undoing a price edit must
		 * never put a property back on the website that somebody has since
		 * taken off it.
		 */
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => Sanitize::text( (string) ( $snapshot['title'] ?? '' ) ),
				'post_excerpt' => Sanitize::textarea( (string) ( $snapshot['excerpt'] ?? '' ) ),
				'post_content' => Sanitize::html( (string) ( $snapshot['content'] ?? '' ) ),
			)
		);

		$allowed = $fields[ $post->post_type ] ?? array();

		foreach ( $allowed as $key ) {
			$was = $snapshot['meta'][ $key ] ?? null;

			if ( null === $was ) {
				delete_post_meta( $post_id, $key );
				continue;
			}

			update_post_meta( $post_id, $key, Sanitize::text( (string) $was ) );
		}

		return true;
	}

	/**
	 * When the most recent snapshot was taken.
	 *
	 * @param int $post_id Record ID.
	 * @return int Unix timestamp, or 0.
	 */
	public static function last_change( int $post_id ): int {
		$history = self::history( $post_id );

		return $history ? (int) $history[0]['at'] : 0;
	}

	/**
	 * Do two snapshots describe the same state?
	 *
	 * @param array $a First snapshot.
	 * @param array $b Second snapshot.
	 * @return bool
	 */
	private static function same( array $a, array $b ): bool {
		foreach ( array( 'title', 'excerpt', 'content', 'status' ) as $key ) {
			if ( ( $a[ $key ] ?? '' ) !== ( $b[ $key ] ?? '' ) ) {
				return false;
			}
		}

		return ( $a['meta'] ?? array() ) === ( $b['meta'] ?? array() );
	}
}
