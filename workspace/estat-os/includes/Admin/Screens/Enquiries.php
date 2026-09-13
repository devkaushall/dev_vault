<?php
/**
 * The enquiry inbox.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Data\PostTypes;
use EstatOS\Leads\Leads;
use EstatOS\Support\Vocabulary;
use EstatOS\Data\Picker;

defined( 'ABSPATH' ) || exit;

/**
 * One row per enquiry with everything needed to act: call, email, WhatsApp,
 * assign, set a status and book a visit.
 */
final class Enquiries {

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'estat_manage_leads' ) ) {
			wp_die( esc_html__( 'You do not have permission to see enquiries.', 'estat-os' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable

		$results = Leads::query(
			array(
				'status'       => $status,
				'search'       => $search,
				'page'         => $paged,
				'per_page'     => 20,
				'due_followup' => 'followup' === $filter,
			)
		);

		$agents = self::agent_options();
		?>
		<?php
		Partials::toolbar(
			array(
				'page'         => 'estat-enquiries',
				'search'       => $search,
				'search_label' => __( 'Search by name, phone or email', 'estat-os' ),
				'count'        => (int) $results['total'],
				'action'       => __( 'Follow-ups due', 'estat-os' ),
				'action_url'   => admin_url( 'admin.php?page=estat-enquiries&filter=followup' ),
				'filters'      => array(
					array(
						'name'    => 'status',
						'label'   => __( 'Filter by status', 'estat-os' ),
						'value'   => $status,
						'options' => array( '' => __( 'All enquiries', 'estat-os' ) ) + Vocabulary::lead_statuses(),
					),
				),
			)
		);
		?>
		<?php

		if ( ! $results['items'] ) {
			Partials::empty_state(
				array(
					'icon'       => 'inbox',
					'title'      => __( 'No enquiries yet', 'estat-os' ),
					'message'    => __( 'When someone fills in a form on your website, they appear here straight away — with their phone number, what they asked about, and space for your notes.', 'estat-os' ),
					'reassuring' => true,
					'steps'      => array(
						__( 'Put a property on the website so people have something to enquire about.', 'estat-os' ),
						__( 'Place an enquiry form on that page.', 'estat-os' ),
						__( 'Every message that comes in lands on this screen.', 'estat-os' ),
					),
					'action'     => admin_url( 'admin.php?page=estat-forms' ),
					'label'      => __( 'Set up a form', 'estat-os' ),
					'hint'       => __( 'Nothing is broken — this screen is simply waiting for your first enquiry.', 'estat-os' ),
				)
			);
			return;
		}

		foreach ( $results['items'] as $lead ) {
			self::lead_card( $lead, $agents );
		}

		$links = paginate_links(
			array(
				'total'   => (int) $results['pages'],
				'current' => (int) $results['page'],
				'type'    => 'array',
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
			)
		);
		if ( is_array( $links ) ) {
			echo '<nav class="estat-pagination" aria-label="' . esc_attr__( 'Enquiry pages', 'estat-os' ) . '"><ul>';
			foreach ( $links as $link ) {
				echo '<li>' . wp_kses_post( $link ) . '</li>';
			}
			echo '</ul></nav>';
		}
	}

	/**
	 * One enquiry as an actionable card.
	 *
	 * @param array<string,mixed>       $lead   Lead row.
	 * @param array<int|string,string>  $agents Agent options.
	 * @return void
	 */
	private static function lead_card( array $lead, array $agents ): void {
		$lead_id  = (int) $lead['id'];
		$phone    = (string) $lead['phone'];
		$email    = (string) $lead['email'];
		$digits   = preg_replace( '/\D/', '', $phone );
		$listing  = (int) $lead['listing_id'];
		$erased   = (bool) $lead['erased'];
		?>
		<article class="estat-lead-card" id="lead-<?php echo esc_attr( (string) $lead_id ); ?>">
			<header class="estat-lead-head">
				<div>
					<h2><?php echo esc_html( (string) $lead['name'] ); ?></h2>
					<p class="estat-muted">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: human readable time difference */
								__( 'Arrived %s ago', 'estat-os' ),
								human_time_diff( (int) strtotime( (string) $lead['created_at'] . ' UTC' ), time() )
							)
						);
						?>
						<?php if ( $listing > 0 ) : ?>
							· <a href="<?php echo esc_url( admin_url( 'admin.php?page=estat-listings&edit=' . $listing ) ); ?>"><?php echo esc_html( (string) get_the_title( $listing ) ); ?></a>
						<?php endif; ?>
					</p>
				</div>
				<span class="estat-pill"><?php echo esc_html( Vocabulary::label( 'lead_statuses', (string) $lead['status'] ) ); ?></span>
			</header>

			<?php if ( ! $erased ) : ?>
				<p class="estat-lead-contact">
					<?php if ( '' !== $phone ) : ?>
						<a class="button button-small" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: phone number */ __( 'Call %s', 'estat-os' ), $phone ) ); ?></a>
						<?php if ( '' !== (string) $digits ) : ?>
							<a class="button button-small" target="_blank" rel="noopener nofollow" href="https://wa.me/<?php echo esc_attr( (string) $digits ); ?>"><?php esc_html_e( 'WhatsApp', 'estat-os' ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( '' !== $email ) : ?>
						<a class="button button-small" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
					<?php endif; ?>
				</p>
			<?php else : ?>
				<p class="estat-muted"><?php esc_html_e( 'The personal information for this enquiry has been erased.', 'estat-os' ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== (string) $lead['message'] ) : ?>
				<blockquote class="estat-lead-message"><?php echo esc_html( (string) $lead['message'] ); ?></blockquote>
			<?php endif; ?>

			<form method="post" class="estat-lead-form" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-enquiries' ) ); ?>">
				<?php wp_nonce_field( 'estat_update_lead_' . $lead_id, 'estat_nonce' ); ?>
				<input type="hidden" name="estat_action" value="update_lead" />
				<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $lead_id ); ?>" />

				<div class="estat-lead-fields">
					<label>
						<span><?php esc_html_e( 'Status', 'estat-os' ); ?></span>
						<select name="status">
							<?php foreach ( Vocabulary::lead_statuses() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) $lead['status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<?php if ( current_user_can( 'estat_assign_leads' ) ) : ?>
						<label>
							<span><?php esc_html_e( 'Who is handling it', 'estat-os' ); ?></span>
							<select name="agent_id">
								<?php foreach ( $agents as $value => $label ) : ?>
									<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) $lead['agent_id'], (string) $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endif; ?>
					<label>
						<span><?php esc_html_e( 'Next follow-up', 'estat-os' ); ?></span>
						<input type="datetime-local" name="followup_at" value="<?php echo esc_attr( $lead['followup_at'] ? gmdate( 'Y-m-d\TH:i', (int) strtotime( (string) $lead['followup_at'] . ' UTC' ) ) : '' ); ?>" />
					</label>
				</div>

				<label class="estat-lead-note">
					<span><?php esc_html_e( 'Add a note', 'estat-os' ); ?></span>
					<textarea name="note" rows="2" placeholder="<?php esc_attr_e( 'For example: called, will visit on Sunday.', 'estat-os' ); ?>"></textarea>
				</label>

				<?php if ( '' !== (string) $lead['notes'] ) : ?>
					<details class="estat-lead-history">
						<summary><?php esc_html_e( 'Earlier notes', 'estat-os' ); ?></summary>
						<pre><?php echo esc_html( (string) $lead['notes'] ); ?></pre>
					</details>
				<?php endif; ?>

				<div class="estat-lead-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'estat-os' ); ?></button>
				</div>
			</form>

			<?php if ( current_user_can( 'estat_manage_visits' ) ) : ?>
				<form method="post" class="estat-visit-form" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-enquiries' ) ); ?>">
					<?php wp_nonce_field( 'estat_schedule_visit_' . $lead_id, 'estat_nonce' ); ?>
					<input type="hidden" name="estat_action" value="schedule_visit" />
					<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $lead_id ); ?>" />
					<input type="hidden" name="listing_id" value="<?php echo esc_attr( (string) $listing ); ?>" />
					<label>
						<span><?php esc_html_e( 'Book a site visit', 'estat-os' ); ?></span>
						<input type="datetime-local" name="scheduled_at" required />
					</label>
					<button type="submit" class="button"><?php esc_html_e( 'Add visit', 'estat-os' ); ?></button>
				</form>
			<?php endif; ?>

			<?php if ( current_user_can( 'estat_erase_leads' ) && ! $erased ) : ?>
				<form method="post" class="estat-erase-form" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-enquiries' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'This permanently erases the name, phone and email. The record of the enquiry stays. Continue?', 'estat-os' ) ); ?>');">
					<?php wp_nonce_field( 'estat_erase_lead_' . $lead_id, 'estat_nonce' ); ?>
					<input type="hidden" name="estat_action" value="erase_lead" />
					<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $lead_id ); ?>" />
					<button type="submit" class="button-link estat-danger-link"><?php esc_html_e( 'Erase personal information', 'estat-os' ); ?></button>
				</form>
			<?php endif; ?>
		</article>
		<?php
	}

	/**
	 * Team member options.
	 *
	 * @return array<int|string,string>
	 */
	private static function agent_options(): array {
		$agents = Picker::options( PostTypes::AGENT, __( 'Nobody yet', 'estat-os' ) );

		return $agents['options'];
	}
}
