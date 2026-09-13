<?php
/**
 * Change requests.
 *
 * An agent who walks into a flat that has just been sold should be able to say
 * so from their phone, even though the listing belongs to a colleague. They
 * propose the change; the office approves it. Until then the website keeps
 * showing what the office last confirmed.
 *
 * This screen is the office's half of that. The agent's half is on the
 * property itself — they are told plainly that they are proposing, never that
 * they are locked out — and it is rendered by ListingsScreen.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Field\Requests;
use EstatOS\Support\Icon;

defined( 'ABSPATH' ) || exit;

/**
 * The queue of changes proposed from the field.
 */
final class RequestsScreen {

	/**
	 * Where this screen lives.
	 */
	public const SLUG = 'estat-requests';

	/**
	 * Requests on one page.
	 */
	private const PER_PAGE = 20;

	/**
	 * Render the queue.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'estat_review_listings' ) ) {
			wp_die( esc_html__( 'You do not have permission to review change requests.', 'estat-os' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : 'pending';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( (string) $_GET['paged'] ) ) ) : 1;
		// phpcs:enable

		$status = isset( Requests::statuses()[ $status ] ) ? $status : 'pending';

		$results = Requests::query(
			array(
				'status'   => $status,
				'page'     => $paged,
				'per_page' => self::PER_PAGE,
			)
		);

		Partials::toolbar(
			array(
				'page'        => self::SLUG,
				'show_search' => false,
				'count'       => (int) $results['total'],
				'filters'     => array(
					array(
						'name'    => 'status',
						'label'   => __( 'Show', 'estat-os' ),
						'value'   => $status,
						'options' => array_merge(
							array( '' => __( 'Everything waiting and dealt with', 'estat-os' ) ),
							self::status_labels()
						),
					),
				),
			)
		);

		if ( ! $results['items'] ) {
			$waiting = 'pending' === $status;

			Partials::empty_state(
				array(
					'icon'       => 'check-circle',
					'title'      => $waiting
						? __( 'Nothing is waiting for you', 'estat-os' )
						: __( 'Nothing filed under that yet', 'estat-os' ),
					'message'    => $waiting
						? __( 'The website is showing exactly what your office has confirmed. When somebody on the ground proposes a change, it arrives here for you to say yes or no.', 'estat-os' )
						: __( 'Once requests start being dealt with, the decided ones will be listed here.', 'estat-os' ),
					'reassuring' => $waiting,
					'action'     => $waiting ? '' : admin_url( 'admin.php?page=' . self::SLUG ),
					'label'      => $waiting ? '' : __( 'Back to what is waiting', 'estat-os' ),
				)
			);
			return;
		}

		echo '<div class="estat-request-list">';
		foreach ( $results['items'] as $request ) {
			self::item( (array) $request );
		}
		echo '</div>';

		Partials::pagination( (int) $results['pages'], $paged, __( 'Pages of change requests', 'estat-os' ) );
	}

	/**
	 * The proposal, what the record says today, and the two buttons that
	 * settle it.
	 *
	 * The "today" side is not decoration. "Change the price to 8500000" cannot
	 * be judged without the number it replaces, and a reviewer should not have
	 * to open the property, remember a figure, and come back.
	 *
	 * @param array<string,mixed> $request The request.
	 * @return void
	 */
	private static function item( array $request ): void {
		$id        = (int) $request['id'];
		$record_id = (int) $request['record_id'];
		$status    = (string) $request['status'];
		$agent     = get_userdata( (int) $request['requested_by'] );
		$agent_name = is_object( $agent ) && isset( $agent->display_name ) ? (string) $agent->display_name : '';
		$changes   = (array) $request['changes'];
		$note      = (string) ( $changes['note'] ?? '' );
		$title     = (string) get_the_title( $record_id );

		echo '<article class="estat-request">';

		echo '<header class="estat-request-head">';
		echo '<span class="estat-request-icon">' . Icon::render( 'inbox', 20 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="estat-request-head-text">';
		printf(
			'<p class="estat-request-title">%1$s</p>',
			esc_html(
				sprintf(
					/* translators: %s: property name. */
					__( 'Change to “%s”', 'estat-os' ),
					'' !== trim( $title ) ? $title : __( 'a property that has since gone', 'estat-os' )
				)
			)
		);
		printf(
			'<p class="estat-request-meta">%1$s · %2$s</p>',
			esc_html( '' !== $agent_name ? $agent_name : __( 'Someone on your team', 'estat-os' ) ),
			esc_html( get_date_from_gmt( (string) $request['created_at'], (string) get_option( 'date_format', 'Y-m-d' ) ) )
		);
		echo '</div>';
		if ( 'pending' !== $status ) {
			printf(
				'<span class="estat-pill%1$s">%2$s</span>',
				esc_attr( 'approved' === $status ? ' is-good' : '' ),
				esc_html( self::status_labels()[ $status ] ?? $status )
			);
		}
		echo '</header>';

		echo '<ul class="estat-request-changes">';
		foreach ( Requests::editable_fields() as $field => $meta ) {
			if ( 'note' === $field || ! isset( $changes[ $field ] ) ) {
				continue;
			}

			printf(
				'<li><span class="estat-request-field">%1$s</span><span class="estat-request-from">%2$s</span><span class="estat-request-arrow"></span><span class="estat-request-to">%3$s</span></li>',
				esc_html( (string) $meta['label'] ),
				esc_html( Requests::describe( (string) $field, Requests::current_value( $record_id, (string) $field ) ) ),
				esc_html( Requests::describe( (string) $field, (string) $changes[ $field ] ) )
			);
		}
		echo '</ul>';

		if ( '' !== $note ) {
			printf(
				'<p class="estat-request-note"><span class="screen-reader-text">%1$s</span>%2$s</p>',
				esc_html__( 'They wrote:', 'estat-os' ),
				esc_html( $note )
			);
		}

		if ( '' !== (string) ( $request['reason'] ?? '' ) ) {
			printf(
				'<p class="estat-request-reason"><span class="screen-reader-text">%1$s</span>%2$s</p>',
				esc_html__( 'You answered:', 'estat-os' ),
				esc_html( (string) $request['reason'] )
			);
		}

		if ( 'pending' === $status ) {
			$url = admin_url( 'admin.php?page=' . self::SLUG );

			echo '<footer class="estat-request-actions">';

			echo '<form method="post" action="' . esc_url( $url ) . '">';
			wp_nonce_field( 'estat_approve_request_' . $id, 'estat_nonce' );
			echo '<input type="hidden" name="estat_action" value="approve_request" />';
			echo '<input type="hidden" name="request_id" value="' . esc_attr( (string) $id ) . '" />';
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Yes, change it', 'estat-os' ) . '</button>';
			echo '</form>';

			echo '<form method="post" action="' . esc_url( $url ) . '" class="estat-request-decline">';
			wp_nonce_field( 'estat_decline_request_' . $id, 'estat_nonce' );
			echo '<input type="hidden" name="estat_action" value="decline_request" />';
			echo '<input type="hidden" name="request_id" value="' . esc_attr( (string) $id ) . '" />';
			echo '<label class="screen-reader-text" for="estat-decline-' . esc_attr( (string) $id ) . '">' . esc_html__( 'Tell them why, in a few words (optional)', 'estat-os' ) . '</label>';
			echo '<input type="text" id="estat-decline-' . esc_attr( (string) $id ) . '" name="reason" maxlength="200" placeholder="' . esc_attr__( 'Why not, in a few words…', 'estat-os' ) . '" />';
			echo '<button type="submit" class="button">' . esc_html__( 'Not now', 'estat-os' ) . '</button>';
			echo '</form>';

			echo '</footer>';
		}

		printf(
			'<a class="estat-request-open" href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=estat-listings&edit=' . $record_id ) ),
			esc_html__( 'Open the property', 'estat-os' )
		);

		echo '</article>';
	}

	/**
	 * The slices a reviewer can ask for.
	 *
	 * @return array<string,string>
	 */
	private static function status_labels(): array {
		$labels = Requests::statuses();

		// "Waiting for the office" reads oddly inside a filter the office is
		// already holding, so the queue says what it is in their own words.
		$labels['pending'] = __( 'Waiting for a decision', 'estat-os' );

		return $labels;
	}
}
