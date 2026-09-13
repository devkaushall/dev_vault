<?php
/**
 * Reports.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Data\PostTypes;
use EstatOS\Install\Schema;
use EstatOS\Support\Format;
use EstatOS\Support\Vocabulary;

defined( 'ABSPATH' ) || exit;

/**
 * Plain-language reporting built from the search index and the lead tables,
 * so it stays fast even with ten thousand listings.
 */
final class Reports {

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'estat_view_reports' ) ) {
			wp_die( esc_html__( 'You do not have permission to see reports.', 'estat-os' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$days = isset( $_GET['days'] ) ? absint( wp_unslash( $_GET['days'] ) ) : 30;
		$days = in_array( $days, array( 7, 30, 90, 365 ), true ) ? $days : 30;
		?>
		<form method="get" class="estat-filters">
			<input type="hidden" name="page" value="estat-reports" />
			<label for="estat-days"><?php esc_html_e( 'Looking at the last', 'estat-os' ); ?></label>
			<select id="estat-days" name="days" onchange="this.form.submit()">
				<option value="7" <?php selected( $days, 7 ); ?>><?php esc_html_e( '7 days', 'estat-os' ); ?></option>
				<option value="30" <?php selected( $days, 30 ); ?>><?php esc_html_e( '30 days', 'estat-os' ); ?></option>
				<option value="90" <?php selected( $days, 90 ); ?>><?php esc_html_e( '3 months', 'estat-os' ); ?></option>
				<option value="365" <?php selected( $days, 365 ); ?>><?php esc_html_e( '1 year', 'estat-os' ); ?></option>
			</select>
			<noscript><button type="submit" class="button"><?php esc_html_e( 'Show', 'estat-os' ); ?></button></noscript>
		</form>
		<?php

		if ( ! Schema::healthy() ) {
			Partials::empty_state( __( 'Reports need the office database tables. Open Office Settings and run the repair tool.', 'estat-os' ), admin_url( 'admin.php?page=estat-settings' ), __( 'Open Office Settings', 'estat-os' ) );
			return;
		}

		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		self::enquiry_summary( $since, $days );
		self::pipeline( $since );
		self::agent_table( $since );
		self::inventory();
		self::localities();
		self::sources( $since );
	}

	/**
	 * Headline numbers for enquiries.
	 *
	 * @param string $since GMT datetime.
	 * @param int    $days  Window length.
	 * @return void
	 */
	private static function enquiry_summary( string $since, int $days ): void {
		global $wpdb;
		$leads  = Schema::table( 'estat_leads' );
		$visits = Schema::table( 'estat_visits' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads} WHERE created_at >= %s AND erased = 0", $since ) );
		$won       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads} WHERE created_at >= %s AND erased = 0 AND status = 'won'", $since ) );
		$booked    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$visits} WHERE created_at >= %s", $since ) );
		$completed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$visits} WHERE created_at >= %s AND outcome = 'done'", $since ) );
		// phpcs:enable

		$per_week   = $days > 0 ? round( ( $total / $days ) * 7, 1 ) : 0.0;
		$conversion = $total > 0 ? round( ( $won / $total ) * 100 ) : 0;

		Partials::card_open( __( 'How the office is doing', 'estat-os' ) );
		echo '<div class="estat-stats">';
		Partials::stat( __( 'Enquiries received', 'estat-os' ), (string) $total, '', 'plain' );
		Partials::stat( __( 'Enquiries per week', 'estat-os' ), (string) $per_week, '', 'plain' );
		Partials::stat( __( 'Site visits booked', 'estat-os' ), (string) $booked, '', 'plain' );
		Partials::stat( __( 'Visits that happened', 'estat-os' ), (string) $completed, '', $completed > 0 ? 'good' : 'plain' );
		Partials::stat( __( 'Deals closed', 'estat-os' ), (string) $won, '', $won > 0 ? 'good' : 'plain' );
		Partials::stat( __( 'Enquiries that became deals', 'estat-os' ), $conversion . '%', '', $conversion >= 5 ? 'good' : 'plain' );
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'A healthy office turns roughly one in twenty enquiries into a deal. Use this as a direction, not a rule.', 'estat-os' ) . '</p>';
		Partials::card_close();
	}

	/**
	 * Where enquiries are sitting right now.
	 *
	 * @param string $since GMT datetime.
	 * @return void
	 */
	private static function pipeline( string $since ): void {
		global $wpdb;
		$leads = Schema::table( 'estat_leads' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS total FROM {$leads} WHERE created_at >= %s AND erased = 0 GROUP BY status", $since ), ARRAY_A );
		$by_status = array();
		foreach ( (array) $rows as $row ) {
			$by_status[ (string) $row['status'] ] = (int) $row['total'];
		}
		$max = $by_status ? max( $by_status ) : 0;

		Partials::card_open( __( 'Where your enquiries are sitting', 'estat-os' ) );
		if ( ! $max ) {
			Partials::empty_state(
				array(
					'icon'       => 'chart',
					'title'      => __( 'Nothing to report for this period', 'estat-os' ),
					'message'    => __( 'No enquiries came in during these dates. Try a wider date range above.', 'estat-os' ),
					'reassuring' => true,
				)
			);
			Partials::card_close();
			return;
		}
		echo '<ul class="estat-bars">';
		foreach ( Vocabulary::lead_statuses() as $status => $label ) {
			$count = $by_status[ $status ] ?? 0;
			$width = $max > 0 ? (int) round( ( $count / $max ) * 100 ) : 0;
			echo '<li class="estat-bar-row">';
			echo '<span class="estat-bar-label">' . esc_html( (string) $label ) . '</span>';
			echo '<span class="estat-bar"><span class="estat-bar-fill" style="width:' . esc_attr( (string) $width ) . '%"></span></span>';
			echo '<span class="estat-bar-value">' . esc_html( (string) $count ) . '</span>';
			echo '</li>';
		}
		echo '</ul>';
		Partials::card_close();
	}

	/**
	 * Per-person performance.
	 *
	 * @param string $since GMT datetime.
	 * @return void
	 */
	private static function agent_table( string $since ): void {
		global $wpdb;
		$leads = Schema::table( 'estat_leads' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT agent_id,
					COUNT(*) AS total,
					SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) AS won,
					SUM(CASE WHEN status IN ('new','contacted') THEN 1 ELSE 0 END) AS open_leads
				 FROM {$leads}
				 WHERE created_at >= %s AND erased = 0 AND agent_id > 0
				 GROUP BY agent_id ORDER BY total DESC LIMIT 25",
				$since
			),
			ARRAY_A
		);

		Partials::card_open( __( 'How each person is doing', 'estat-os' ) );
		if ( ! $rows ) {
			Partials::empty_state( __( 'No enquiries have been assigned to anyone in this period.', 'estat-os' ), admin_url( 'admin.php?page=estat-enquiries' ), __( 'Assign some now', 'estat-os' ) );
			Partials::card_close();
			return;
		}
		echo '<table class="widefat estat-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Person', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Enquiries handled', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Still open', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Deals closed', 'estat-os' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( (array) $rows as $row ) {
			$name = (string) get_the_title( (int) $row['agent_id'] );
			echo '<tr>';
			echo '<th scope="row" data-label="' . esc_attr__( 'Person', 'estat-os' ) . '">' . esc_html( '' !== $name ? $name : __( 'Unknown', 'estat-os' ) ) . '</th>';
			echo '<td data-label="' . esc_attr__( 'Enquiries handled', 'estat-os' ) . '">' . esc_html( (string) (int) $row['total'] ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Still open', 'estat-os' ) . '">' . esc_html( (string) (int) $row['open_leads'] ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Deals closed', 'estat-os' ) . '">' . esc_html( (string) (int) $row['won'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		Partials::card_close();
	}

	/**
	 * Inventory shape.
	 *
	 * @return void
	 */
	private static function inventory(): void {
		global $wpdb;
		$index = Schema::table( 'estat_index' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT offer, COUNT(*) AS total, AVG(NULLIF(price,0)) AS avg_price FROM {$index} WHERE post_status = 'publish' GROUP BY offer", ARRAY_A );
		$avg_score = (float) $wpdb->get_var( "SELECT AVG(completeness) FROM {$index}" );
		// phpcs:enable

		Partials::card_open( __( 'What you have to sell', 'estat-os' ) );
		echo '<div class="estat-stats">';
		Partials::stat( __( 'Average listing quality', 'estat-os' ), round( $avg_score ) . '%', admin_url( 'admin.php?page=estat-listings' ), $avg_score >= 70 ? 'good' : 'warn' );
		Partials::stat( __( 'Properties on the website', 'estat-os' ), (string) (int) wp_count_posts( PostTypes::LISTING )->publish, admin_url( 'admin.php?page=estat-listings' ), 'plain' );
		Partials::stat( __( 'Still being prepared', 'estat-os' ), (string) (int) wp_count_posts( PostTypes::LISTING )->draft, admin_url( 'admin.php?page=estat-listings&status=draft' ), 'plain' );
		echo '</div>';

		if ( $rows ) {
			echo '<table class="widefat estat-table"><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Kind of deal', 'estat-os' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'How many', 'estat-os' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Average price', 'estat-os' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( (array) $rows as $row ) {
				echo '<tr>';
				echo '<th scope="row" data-label="' . esc_attr__( 'Kind of deal', 'estat-os' ) . '">' . esc_html( Vocabulary::label( 'offers', (string) $row['offer'] ) ) . '</th>';
				echo '<td data-label="' . esc_attr__( 'How many', 'estat-os' ) . '">' . esc_html( (string) (int) $row['total'] ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Average price', 'estat-os' ) . '">' . esc_html( Format::money( (float) $row['avg_price'] ) ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		Partials::card_close();
	}

	/**
	 * Busiest localities.
	 *
	 * @return void
	 */
	private static function localities(): void {
		$terms = get_terms(
			array(
				'taxonomy'   => \EstatOS\Data\Taxonomies::LOCALITY,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 10,
				'hide_empty' => true,
			)
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			return;
		}

		Partials::card_open( __( 'Your busiest areas', 'estat-os' ) );
		echo '<ul class="estat-bars">';
		$max = max( array_map( static fn( $term ) => (int) $term->count, $terms ) );
		foreach ( $terms as $term ) {
			$width = $max > 0 ? (int) round( ( (int) $term->count / $max ) * 100 ) : 0;
			echo '<li class="estat-bar-row">';
			echo '<span class="estat-bar-label">' . esc_html( (string) $term->name ) . '</span>';
			echo '<span class="estat-bar"><span class="estat-bar-fill" style="width:' . esc_attr( (string) $width ) . '%"></span></span>';
			echo '<span class="estat-bar-value">' . esc_html( (string) (int) $term->count ) . '</span>';
			echo '</li>';
		}
		echo '</ul>';
		Partials::card_close();
	}

	/**
	 * Where enquiries came from.
	 *
	 * @param string $since GMT datetime.
	 * @return void
	 */
	private static function sources( string $since ): void {
		global $wpdb;
		$leads = Schema::table( 'estat_leads' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT source, COUNT(*) AS total FROM {$leads} WHERE created_at >= %s AND erased = 0 GROUP BY source ORDER BY total DESC LIMIT 10", $since ), ARRAY_A );
		if ( ! $rows ) {
			return;
		}

		Partials::card_open( __( 'Where your enquiries came from', 'estat-os' ), __( 'Spend more time on whatever is bringing people in.', 'estat-os' ) );
		echo '<table class="widefat estat-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Source', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Enquiries', 'estat-os' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( (array) $rows as $row ) {
			$source = (string) $row['source'];
			echo '<tr>';
			echo '<th scope="row" data-label="' . esc_attr__( 'Source', 'estat-os' ) . '">' . esc_html( '' !== $source ? $source : __( 'Not recorded', 'estat-os' ) ) . '</th>';
			echo '<td data-label="' . esc_attr__( 'Enquiries', 'estat-os' ) . '">' . esc_html( (string) (int) $row['total'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		Partials::card_close();
	}
}
