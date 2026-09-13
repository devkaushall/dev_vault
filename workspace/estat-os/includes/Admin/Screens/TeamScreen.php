<?php
/**
 * Team management.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Data\Agents;
use EstatOS\Data\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * People, their contact details and how much they are handling.
 */
final class TeamScreen {

	/**
	 * How many rows one page of the list holds.
	 */
	private const PER_PAGE = 25;

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'estat_manage_team' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the team.', 'estat-os' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
		$post    = $edit_id > 0 ? get_post( $edit_id ) : null;
		$meta    = static fn( string $key ) => $edit_id > 0 ? get_post_meta( $edit_id, $key, true ) : '';

		Partials::card_open( $post ? __( 'Edit this person', 'estat-os' ) : __( 'Add someone to the team', 'estat-os' ) );
		?>
		<form method="post" class="estat-form-admin" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-team' ) ); ?>">
			<?php wp_nonce_field( 'estat_save_agent', 'estat_nonce' ); ?>
			<input type="hidden" name="estat_action" value="save_agent" />
			<input type="hidden" name="agent_id" value="<?php echo esc_attr( (string) $edit_id ); ?>" />
			<?php
			Partials::field( array( 'name' => 'title', 'label' => __( 'Full name', 'estat-os' ), 'value' => $post ? $post->post_title : '', 'required' => true, 'example' => __( 'Priya Sharma', 'estat-os' ) ) );
			Partials::field( array( 'name' => 'role', 'label' => __( 'Role in the office', 'estat-os' ), 'value' => $meta( '_estat_role' ), 'example' => __( 'Sales Manager', 'estat-os' ) ) );
			Partials::field( array( 'name' => 'phone', 'type' => 'tel', 'label' => __( 'Phone number', 'estat-os' ), 'value' => $meta( '_estat_phone' ), 'example' => '+91 98765 43210' ) );
			Partials::field( array( 'name' => 'whatsapp', 'type' => 'tel', 'label' => __( 'WhatsApp number', 'estat-os' ), 'value' => $meta( '_estat_whatsapp' ), 'help' => __( 'Leave empty if it is the same as the phone number.', 'estat-os' ) ) );
			Partials::field( array( 'name' => 'email', 'type' => 'email', 'label' => __( 'Email address', 'estat-os' ), 'value' => $meta( '_estat_email' ), 'example' => 'name@example.com' ) );
			Partials::field( array( 'name' => 'bio', 'type' => 'textarea', 'label' => __( 'A few words about them', 'estat-os' ), 'value' => $post ? $post->post_content : '' ) );
			Partials::field( array( 'name' => 'photo_id', 'type' => 'image', 'label' => __( 'Photo', 'estat-os' ), 'value' => $edit_id > 0 ? get_post_thumbnail_id( $edit_id ) : 0 ) );
			Partials::field( array( 'name' => 'external_id', 'label' => __( 'Your own reference number', 'estat-os' ), 'value' => $meta( '_estat_external_id' ) ) );
			Partials::field( array( 'name' => 'user_id', 'type' => 'select', 'label' => __( 'Website login', 'estat-os' ), 'options' => self::user_options(), 'value' => $meta( '_estat_user_id' ), 'help' => __( 'Link this person to a login so they can sign in and see their own work.', 'estat-os' ) ) );
			?>
			<div class="estat-form-actions-admin">
				<button type="submit" class="button button-primary button-hero"><?php echo $post ? esc_html__( 'Save changes', 'estat-os' ) : esc_html__( 'Add them', 'estat-os' ); ?></button>
			</div>
		</form>
		<?php
		Partials::card_close();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable

		$args = array(
			'post_type'      => PostTypes::AGENT,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );

		Partials::card_open( __( 'Your team', 'estat-os' ) );

		Partials::toolbar(
			array(
				'page'         => 'estat-team',
				'search'       => $search,
				'search_label' => __( 'Search by name', 'estat-os' ),
				'count'        => (int) $query->found_posts,
				'count_label'  => __( 'people', 'estat-os' ),
			)
		);

		if ( ! $query->posts ) {
			Partials::empty_state(
				array(
					'icon'    => 'person',
					'title'   => '' !== $search ? __( 'Nobody matched that', 'estat-os' ) : __( 'Nobody added yet', 'estat-os' ),
					'message' => '' !== $search
						? __( 'Try part of the name instead of the whole thing.', 'estat-os' )
						: __( 'Add your colleagues using the form above. Once someone is here you can hand them an enquiry or put them in charge of a property.', 'estat-os' ),
				)
			);
			Partials::card_close();

			return;
		}

		echo '<table class="widefat estat-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Name', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Role', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Contact', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Listings', 'estat-os' ) . '</th>';
		echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'estat-os' ) . '</span></th>';
		echo '</tr></thead><tbody>';

		foreach ( $query->posts as $member ) {
			$agent = Agents::to_array( (int) $member->ID );
			echo '<tr>';
			echo '<th scope="row" data-label="' . esc_attr__( 'Name', 'estat-os' ) . '">' . esc_html( (string) $agent['name'] ) . '</th>';
			echo '<td data-label="' . esc_attr__( 'Role', 'estat-os' ) . '">' . esc_html( (string) $agent['role'] ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Contact', 'estat-os' ) . '">' . esc_html( trim( (string) $agent['phone'] . ' ' . (string) $agent['email'] ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Listings', 'estat-os' ) . '"><span class="estat-num">' . esc_html( (string) (int) $agent['listings'] ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Actions', 'estat-os' ) . '"><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=estat-team&edit=' . (int) $member->ID ) ) . '">' . esc_html__( 'Edit', 'estat-os' ) . '</a> ';
			Partials::record_actions( (int) $member->ID, (string) $member->post_status );
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		Partials::pagination( (int) $query->max_num_pages, $paged, __( 'Pages of team members', 'estat-os' ) );
		Partials::card_close();
	}

	/**
	 * Login account options.
	 *
	 * @return array<int|string,string>
	 */
	private static function user_options(): array {
		$options = array( '0' => __( 'No login', 'estat-os' ) );
		$users   = get_users( array( 'number' => 200, 'fields' => array( 'ID', 'display_name' ) ) );
		foreach ( $users as $user ) {
			$options[ (string) $user->ID ] = (string) $user->display_name;
		}
		return $options;
	}
}
