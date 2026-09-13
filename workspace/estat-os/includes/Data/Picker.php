<?php
/**
 * Building the lists that fill a dropdown.
 *
 * A dropdown cannot hold ten thousand entries, so it has to stop somewhere.
 * The dangerous part was never the limit; it was stopping silently. Agents and
 * projects were fetched 200 at a time, ordered by title, and the two hundred
 * and first simply was not in the list. To the office that looks exactly like
 * a record that has gone missing, and there was nothing on screen to say
 * otherwise.
 *
 * So this class does two things the old inline queries did not:
 *
 * 1. It reports whether anything was left out, so the screen can say so.
 * 2. It keeps the records the office is most likely to want. Recently updated
 *    first, because the project someone added this morning is the one they are
 *    about to attach a listing to.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Bounded, honest option lists.
 */
final class Picker {

	/**
	 * How many records a dropdown will hold.
	 *
	 * Comfortably more than a small or medium office has, and small enough
	 * that the page stays quick to render.
	 */
	public const LIMIT = 200;

	/**
	 * Build the options for a record picker.
	 *
	 * @param string $post_type Post type to list.
	 * @param string $empty     Label for the "nothing chosen" option.
	 * @param int    $keep      Optional ID that must appear even if it falls
	 *                          outside the limit, so editing an old record
	 *                          never quietly clears the field.
	 * @return array{options:array<string,string>,total:int,shown:int,truncated:bool}
	 */
	public static function options( string $post_type, string $empty, int $keep = 0 ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => self::LIMIT,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => false,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		$posts = $query->posts;
		$total = (int) $query->found_posts;

		// found_posts is not always populated by every environment; fall back
		// to the number we actually received rather than reporting nonsense.
		if ( $total < count( $posts ) ) {
			$total = count( $posts );
		}

		$options = array( '0' => $empty );
		$seen    = array();

		foreach ( $posts as $post ) {
			$options[ (string) $post->ID ] = self::label( $post );
			$seen[]                        = (int) $post->ID;
		}

		/*
		 * The record currently attached must always be selectable. Without
		 * this, opening a two-year-old listing whose agent has since dropped
		 * out of the most-recent 200 would show "Nobody yet", and saving would
		 * silently unassign them.
		 */
		if ( $keep > 0 && ! in_array( $keep, $seen, true ) ) {
			$kept = get_post( $keep );

			if ( $kept && $post_type === get_post_type( $kept ) ) {
				$options[ (string) $keep ] = self::label( $kept );
			}
		}

		// Alphabetical is how a person reads a dropdown, even though we chose
		// WHICH records to include by how recent they are.
		$empty_option = $options['0'];
		unset( $options['0'] );
		natcasesort( $options );
		$options = array( '0' => $empty_option ) + $options;

		return array(
			'options'   => $options,
			'total'     => $total,
			'shown'     => count( $posts ),
			'truncated' => $total > count( $posts ),
		);
	}

	/**
	 * A readable label for one record.
	 *
	 * An untitled record would otherwise render as a blank line that cannot be
	 * told apart from any other blank line.
	 *
	 * @param \WP_Post $post Record.
	 * @return string
	 */
	private static function label( \WP_Post $post ): string {
		$title = trim( (string) $post->post_title );

		if ( '' !== $title ) {
			return $title;
		}

		/* translators: %d: record number. */
		return sprintf( __( 'Untitled (#%d)', 'estat-os' ), (int) $post->ID );
	}

	/**
	 * The sentence a screen shows when a list had to be cut short.
	 *
	 * @param array $result Result from options().
	 * @return string Empty when nothing was left out.
	 */
	public static function notice( array $result ): string {
		if ( empty( $result['truncated'] ) ) {
			return '';
		}

		return sprintf(
			/* translators: 1: number shown, 2: total number. */
			__( 'Showing the %1$d most recently updated of %2$d. If the one you want is missing, open it directly and add the link from there.', 'estat-os' ),
			(int) $result['shown'],
			(int) $result['total']
		);
	}
}
