<?php
/**
 * Site visits.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Leads\Leads;
use EstatOS\Leads\Visits;
use EstatOS\Support\Vocabulary;

defined( 'ABSPATH' ) || exit;

/**
 * A simple diary of visits with an outcome for each one.
 */
final class VisitsScreen {

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'estat_manage_visits' ) ) {
			wp_die( esc_html__( 'You do not have permission to see site visits.', 'estat-os' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$outcome = isset( $_GET['outcome'] ) ? sanitize_key( wp_unslash( $_GET['outcome'] ) ) : '';
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable

		$results = Visits::query( array( 'outcome' => $outcome, 'page' => $paged, 'per_page' => 20 ) );
		?>
		<?php
		Partials::toolbar(
			array(
				'page'        => 'estat-visits',
				'show_search' => false,
				'count'       => (int) $results['total'],
				'filters'     => array(
					array(
						'name'    => 'outcome',
						'label'   => __( 'Filter by outcome', 'estat-os' ),
						'value'   => $outcome,
						'options' => array( '' => __( 'All visits', 'estat-os' ) ) + Vocabulary::visit_outcomes(),
					),
				),
			)
		);
		?>
		<?php

		if ( ! $results['items'] ) {
			Partials::empty_state(
				array(
					'icon'       => 'car',
					'title'      => __( 'No site visits booked', 'estat-os' ),
					'message'    => __( 'Site visits are arranged from an enquiry. Open any enquiry, pick a date, and it will show up here and on your Today screen on the day.', 'estat-os' ),
					'reassuring' => true,
					'action'     => admin_url( 'admin.php?page=estat-enquiries' ),
					'label'      => __( 'Open enquiries', 'estat-os' ),
				)
			);
			return;
		}

		echo '<table class="widefat estat-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'When', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Who', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Property', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'How did it go?', 'estat-os' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $results['items'] as $visit ) {
			$visit_id = (int) $visit['id'];
			$lead     = (int) $visit['lead_id'] > 0 ? Leads::get( (int) $visit['lead_id'] ) : null;
			?>
			<tr>
				<th scope="row" data-label="<?php esc_attr_e( 'When', 'estat-os' ); ?>">
					<?php echo esc_html( get_date_from_gmt( (string) $visit['scheduled_at'], (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) ) ); ?>
				</th>
				<td data-label="<?php esc_attr_e( 'Who', 'estat-os' ); ?>">
					<?php if ( $lead ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=estat-enquiries#lead-' . (int) $lead['id'] ) ); ?>"><?php echo esc_html( (string) $lead['name'] ); ?></a>
					<?php else : ?>
						<span class="estat-muted"><?php esc_html_e( 'Not linked to an enquiry', 'estat-os' ); ?></span>
					<?php endif; ?>
				</td>
				<td data-label="<?php esc_attr_e( 'Property', 'estat-os' ); ?>">
					<?php if ( (int) $visit['listing_id'] > 0 ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=estat-listings&edit=' . (int) $visit['listing_id'] ) ); ?>"><?php echo esc_html( (string) get_the_title( (int) $visit['listing_id'] ) ); ?></a>
					<?php else : ?>
						<span class="estat-muted">—</span>
					<?php endif; ?>
				</td>
				<td data-label="<?php esc_attr_e( 'How did it go?', 'estat-os' ); ?>">
					<form method="post" class="estat-inline-form" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-visits' ) ); ?>">
						<?php wp_nonce_field( 'estat_update_visit_' . $visit_id, 'estat_nonce' ); ?>
						<input type="hidden" name="estat_action" value="update_visit" />
						<input type="hidden" name="visit_id" value="<?php echo esc_attr( (string) $visit_id ); ?>" />
						<label class="screen-reader-text" for="estat-outcome-<?php echo esc_attr( (string) $visit_id ); ?>"><?php esc_html_e( 'Outcome', 'estat-os' ); ?></label>
						<select id="estat-outcome-<?php echo esc_attr( (string) $visit_id ); ?>" name="outcome">
							<?php foreach ( Vocabulary::visit_outcomes() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) $visit['outcome'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="button button-small"><?php esc_html_e( 'Update', 'estat-os' ); ?></button>
					</form>
				</td>
			</tr>
			<?php
		}
		echo '</tbody></table>';

		// Visits::query() already pages; the links were simply never drawn, so
		// page two existed and could not be reached.
		Partials::pagination( (int) $results['pages'], $paged, __( 'Pages of site visits', 'estat-os' ) );
	}
}
