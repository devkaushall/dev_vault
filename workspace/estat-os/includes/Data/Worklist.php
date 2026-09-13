<?php
/**
 * What the office should actually do next.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Data;

use EstatOS\Leads\Leads;
use EstatOS\Leads\Visits;
use EstatOS\Support\Format;

defined( 'ABSPATH' ) || exit;

/**
 * A dashboard full of numbers tells an office how it is doing but not what to
 * do. This turns the same information into a short, ordered list of real jobs,
 * each one a link straight to the screen where it gets done.
 *
 * Everything here is bounded: a handful of rows per source, never a full table
 * scan, so the dashboard stays fast on a busy office.
 */
final class Worklist {

	/**
	 * How many jobs to show at once.
	 */
	public const LIMIT = 12;

	/**
	 * Build the list of jobs, most urgent first.
	 *
	 * @param int $limit How many to return.
	 * @return array<int,array<string,mixed>>
	 */
	public static function tasks( int $limit = self::LIMIT ): array {
		$limit = max( 1, min( 50, $limit ) );

		$tasks = array_merge(
			self::overdue_followups(),
			self::visits_today(),
			self::field_requests(),
			self::new_enquiries(),
			self::unassigned_enquiries(),
			self::incomplete_listings()
		);

		// Lower urgency number sorts first; ties keep their original order.
		usort(
			$tasks,
			static function ( array $a, array $b ): int {
				return $a['urgency'] <=> $b['urgency'];
			}
		);

		/**
		 * Filter the office to-do list.
		 *
		 * @param array<int,array<string,mixed>> $tasks Jobs to show.
		 */
		$tasks = (array) apply_filters( 'estat_worklist', $tasks );

		return array_slice( $tasks, 0, $limit );
	}

	/**
	 * Follow-ups whose date has passed. The most costly thing to forget.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function overdue_followups(): array {
		if ( ! current_user_can( 'estat_manage_leads' ) ) {
			return array();
		}

		$result = Leads::query(
			array(
				'due_followup' => true,
				'per_page'     => 5,
			)
		);

		$out = array();

		foreach ( $result['items'] as $lead ) {
			$name = '' !== (string) $lead['name'] ? (string) $lead['name'] : __( 'Someone', 'estat-os' );

			$out[] = array(
				'urgency' => 1,
				'tone'    => 'danger',
				'icon'    => 'clock',
				'title'   => sprintf(
					/* translators: %s: person's name. */
					__( 'Call %s back', 'estat-os' ),
					$name
				),
				'detail'  => __( 'The follow-up date has passed.', 'estat-os' ),
				'action'  => __( 'Open enquiry', 'estat-os' ),
				'url'     => admin_url( 'admin.php?page=estat-enquiries&edit=' . (int) $lead['id'] ),
			);
		}

		if ( (int) $result['total'] > count( $out ) ) {
			$out[] = self::more_link(
				1,
				(int) $result['total'] - count( $out ),
				admin_url( 'admin.php?page=estat-enquiries&filter=followup' ),
				__( 'more follow-ups are overdue', 'estat-os' )
			);
		}

		return $out;
	}

	/**
	 * Visits happening today.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function visits_today(): array {
		if ( ! current_user_can( 'estat_manage_visits' ) ) {
			return array();
		}

		$start = get_gmt_from_date( current_time( 'Y-m-d' ) . ' 00:00:00' );
		$end   = get_gmt_from_date( current_time( 'Y-m-d' ) . ' 23:59:59' );

		$result = Visits::query(
			array(
				'outcome'  => 'pending',
				'from'     => $start,
				'to'       => $end,
				'per_page' => 5,
			)
		);

		$out = array();

		foreach ( $result['items'] as $visit ) {
			$listing = (int) $visit['listing_id'] > 0 ? get_the_title( (int) $visit['listing_id'] ) : '';
			$when    = Format::time( (string) $visit['scheduled_at'] );

			$out[] = array(
				'urgency' => 2,
				'tone'    => 'warn',
				'icon'    => 'car',
				'title'   => '' !== $listing
					? sprintf(
						/* translators: 1: time, 2: property name. */
						__( 'Site visit at %1$s — %2$s', 'estat-os' ),
						$when,
						$listing
					)
					: sprintf(
						/* translators: %s: time. */
						__( 'Site visit at %s', 'estat-os' ),
						$when
					),
				'detail'  => __( 'Happening today.', 'estat-os' ),
				'action'  => __( 'Open visit', 'estat-os' ),
				'url'     => admin_url( 'admin.php?page=estat-visits' ),
			);
		}

		return $out;
	}

	/**
	 * Enquiries nobody has looked at yet.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function new_enquiries(): array {
		if ( ! current_user_can( 'estat_manage_leads' ) ) {
			return array();
		}

		$result = Leads::query(
			array(
				'status'   => 'new',
				'per_page' => 5,
			)
		);

		$out = array();

		foreach ( $result['items'] as $lead ) {
			$name    = '' !== (string) $lead['name'] ? (string) $lead['name'] : __( 'Someone', 'estat-os' );
			$listing = (int) $lead['listing_id'] > 0 ? get_the_title( (int) $lead['listing_id'] ) : '';

			$out[] = array(
				'urgency' => 3,
				'tone'    => 'accent',
				'icon'    => 'inbox',
				'title'   => sprintf(
					/* translators: %s: person's name. */
					__( 'Reply to %s', 'estat-os' ),
					$name
				),
				'detail'  => '' !== $listing
					? sprintf(
						/* translators: %s: property name. */
						__( 'Asked about %s.', 'estat-os' ),
						$listing
					)
					: __( 'A new enquiry nobody has answered.', 'estat-os' ),
				'action'  => __( 'Open enquiry', 'estat-os' ),
				'url'     => admin_url( 'admin.php?page=estat-enquiries&edit=' . (int) $lead['id'] ),
			);
		}

		if ( (int) $result['total'] > count( $out ) ) {
			$out[] = self::more_link(
				3,
				(int) $result['total'] - count( $out ),
				admin_url( 'admin.php?page=estat-enquiries&status=new' ),
				__( 'more enquiries are waiting', 'estat-os' )
			);
		}

		return $out;
	}

	/**
	 * Enquiries with nobody responsible for them.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function unassigned_enquiries(): array {
		if ( ! current_user_can( 'estat_assign_leads' ) ) {
			return array();
		}

		$result = Leads::query( array( 'per_page' => 25 ) );
		$count  = 0;

		foreach ( $result['items'] as $lead ) {
			$closed = in_array( (string) $lead['status'], array( 'converted', 'lost' ), true );
			if ( ! $closed && (int) $lead['agent_id'] <= 0 ) {
				++$count;
			}
		}

		if ( $count < 1 ) {
			return array();
		}

		return array(
			array(
				'urgency' => 4,
				'tone'    => 'accent',
				'icon'    => 'person',
				'title'   => sprintf(
					/* translators: %s: number of enquiries. */
					_n( 'Give %s enquiry to someone', 'Give %s enquiries to someone', $count, 'estat-os' ),
					number_format_i18n( $count )
				),
				'detail'  => __( 'Nobody is responsible for these yet.', 'estat-os' ),
				'action'  => __( 'Assign them', 'estat-os' ),
				'url'     => admin_url( 'admin.php?page=estat-enquiries' ),
			),
		);
	}

	/**
	 * Listings that are missing information buyers look for.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function incomplete_listings(): array {
		if ( ! current_user_can( 'estat_manage_listings' ) ) {
			return array();
		}

		$query = new \WP_Query(
			array(
				'post_type'      => PostTypes::LISTING,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 5,
				'meta_key'       => '_estat_completeness', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$out = array();

		foreach ( (array) $query->posts as $post ) {
			$score = (int) get_post_meta( (int) $post->ID, '_estat_completeness', true );

			if ( $score >= 80 ) {
				continue;
			}

			$out[] = array(
				'urgency' => 5,
				'tone'    => 'plain',
				'icon'    => 'home',
				'title'   => sprintf(
					/* translators: %s: property name. */
					__( 'Finish “%s”', 'estat-os' ),
					get_the_title( $post )
				),
				'detail'  => sprintf(
					/* translators: %d: percentage complete. */
					__( 'Only %d%% complete. More detail brings more enquiries.', 'estat-os' ),
					$score
				),
				'action'  => __( 'Complete it', 'estat-os' ),
				'url'     => admin_url( 'admin.php?page=estat-listings&edit=' . (int) $post->ID ),
			);
		}

		return $out;
	}

	/**
	 * Corrections an agent proposed from the property itself.
	 *
	 * Sits above an incomplete listing in the queue: a website telling buyers
	 * the wrong price is a live fault, while a thin description only costs a
	 * few enquiries.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function field_requests(): array {
		if ( ! current_user_can( 'estat_review_listings' ) ) {
			return array();
		}

		$pending = \EstatOS\Field\Requests::query(
			array(
				'status'   => 'pending',
				'per_page' => 5,
			)
		);

		if ( ! $pending['items'] ) {
			return array();
		}

		$out = array();

		foreach ( $pending['items'] as $request ) {
			$out[] = array(
				'urgency' => 3,
				'tone'    => 'warn',
				'icon'    => 'inbox',
				'title'   => sprintf(
					/* translators: %s: property name. */
					__( 'A change is waiting on “%s”', 'estat-os' ),
					(string) get_the_title( (int) $request['record_id'] )
				),
				'detail'  => __( 'Proposed from the property, by the person standing in it.', 'estat-os' ),
				'action'  => __( 'Decide', 'estat-os' ),
				'url'     => admin_url( 'admin.php?page=estat-requests' ),
			);
		}

		$left = (int) $pending['total'] - 5;

		if ( $left > 0 ) {
			$out[] = self::more_link( 3, $left, admin_url( 'admin.php?page=estat-requests' ), __( 'more changes waiting', 'estat-os' ) );
		}

		return $out;
	}

	/**
	 * A single "and N more" row.
	 *
	 * @param int    $urgency Sort weight.
	 * @param int    $count   How many more.
	 * @param string $url     Where to see them.
	 * @param string $label   What they are.
	 * @return array<string,mixed>
	 */
	private static function more_link( int $urgency, int $count, string $url, string $label ): array {
		return array(
			'urgency' => $urgency,
			'tone'    => 'plain',
			'icon'    => 'arrow-right',
			'title'   => sprintf(
				/* translators: 1: number, 2: what they are. */
				__( '%1$s %2$s', 'estat-os' ),
				number_format_i18n( $count ),
				$label
			),
			'detail'  => '',
			'action'  => __( 'See all', 'estat-os' ),
			'url'     => $url,
		);
	}
}
