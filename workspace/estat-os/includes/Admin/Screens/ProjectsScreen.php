<?php
/**
 * Societies and projects.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Data\PostTypes;
use EstatOS\Data\Projects;
use EstatOS\Data\Taxonomies;
use EstatOS\Support\Vocabulary;

defined( 'ABSPATH' ) || exit;

/**
 * Add a society in a few fields, then see how many properties belong to it.
 */
final class ProjectsScreen {

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
		if ( ! current_user_can( 'estat_manage_projects' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage societies and projects.', 'estat-os' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
		$post    = $edit_id > 0 ? get_post( $edit_id ) : null;
		$meta    = static fn( string $key ) => $edit_id > 0 ? get_post_meta( $edit_id, $key, true ) : '';
		$terms   = $edit_id > 0 ? wp_get_object_terms( $edit_id, Taxonomies::LOCALITY, array( 'fields' => 'names' ) ) : array();

		Partials::card_open( $post ? __( 'Edit this society or project', 'estat-os' ) : __( 'Add a society or project', 'estat-os' ) );
		?>
		<form method="post" class="estat-form-admin" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-projects' ) ); ?>">
			<?php wp_nonce_field( 'estat_save_project', 'estat_nonce' ); ?>
			<input type="hidden" name="estat_action" value="save_project" />
			<input type="hidden" name="project_id" value="<?php echo esc_attr( (string) $edit_id ); ?>" />
			<?php
			Partials::field( array( 'name' => 'title', 'label' => __( 'Name', 'estat-os' ), 'value' => $post ? $post->post_title : '', 'required' => true, 'example' => __( 'Green Meadows Residency', 'estat-os' ) ) );
			Partials::field( array( 'name' => 'description', 'type' => 'textarea', 'label' => __( 'About it', 'estat-os' ), 'value' => $post ? $post->post_content : '' ) );
			Partials::field( array( 'name' => 'locality', 'label' => __( 'Locality', 'estat-os' ), 'value' => is_wp_error( $terms ) ? '' : implode( ', ', (array) $terms ) ) );
			Partials::field( array( 'name' => 'developer', 'label' => __( 'Builder or developer', 'estat-os' ), 'value' => $meta( '_estat_developer' ) ) );
			Partials::field( array( 'name' => 'project_status', 'type' => 'select', 'label' => __( 'Stage', 'estat-os' ), 'options' => Vocabulary::project_statuses(), 'value' => $meta( '_estat_project_status' ) ) );
			Partials::field( array( 'name' => 'possession_date', 'type' => 'date', 'label' => __( 'Possession date', 'estat-os' ), 'value' => $meta( '_estat_possession_date' ) ) );
			Partials::field( array( 'name' => 'price_min', 'type' => 'number', 'label' => __( 'Lowest price', 'estat-os' ), 'value' => $meta( '_estat_price_min' ), 'min' => 0 ) );
			Partials::field( array( 'name' => 'price_max', 'type' => 'number', 'label' => __( 'Highest price', 'estat-os' ), 'value' => $meta( '_estat_price_max' ), 'min' => 0 ) );
			Partials::field( array( 'name' => 'total_units', 'type' => 'number', 'label' => __( 'Total units', 'estat-os' ), 'value' => $meta( '_estat_total_units' ), 'min' => 0 ) );
			Partials::field( array( 'name' => 'available_units', 'type' => 'number', 'label' => __( 'Units still available', 'estat-os' ), 'value' => $meta( '_estat_available_units' ), 'min' => 0 ) );
			Partials::field( array( 'name' => 'unit_types', 'label' => __( 'Unit types', 'estat-os' ), 'value' => $meta( '_estat_unit_types' ), 'example' => '2 BHK, 3 BHK' ) );
			Partials::field( array( 'name' => 'highlights', 'type' => 'textarea', 'label' => __( 'Highlights', 'estat-os' ), 'value' => $meta( '_estat_highlights' ), 'help' => __( 'One point per line.', 'estat-os' ) ) );
			Partials::field( array( 'name' => 'regulatory_id', 'label' => __( 'Registration number', 'estat-os' ), 'value' => $meta( '_estat_regulatory_id' ) ) );
			Partials::field( array( 'name' => 'external_id', 'label' => __( 'Your own reference number', 'estat-os' ), 'value' => $meta( '_estat_external_id' ), 'help' => __( 'Used to avoid duplicates when importing spreadsheets.', 'estat-os' ) ) );
			Partials::field( array( 'name' => 'cover_id', 'type' => 'image', 'label' => __( 'Main photo', 'estat-os' ), 'value' => $edit_id > 0 ? get_post_thumbnail_id( $edit_id ) : 0 ) );
			?>
			<div class="estat-form-actions-admin">
				<button type="submit" class="button button-primary button-hero"><?php echo $post ? esc_html__( 'Save changes', 'estat-os' ) : esc_html__( 'Add it', 'estat-os' ); ?></button>
				<?php if ( $post ) : ?>
					<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=estat-projects' ) ); ?>"><?php esc_html_e( 'Add another instead', 'estat-os' ); ?></a>
				<?php endif; ?>
			</div>
		</form>
		<?php
		Partials::card_close();

		/*
		 * The list used to fetch a flat fifty with no search and no page
		 * links, so an office with more than fifty projects simply could not
		 * reach the rest.
		 */
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$stage  = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( $_GET['stage'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable

		$args = array(
			'post_type'      => PostTypes::PROJECT,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( '' !== $stage ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_estat_project_status',
					'value' => $stage,
				),
			);
		}

		$query = new \WP_Query( $args );
		$total = (int) $query->found_posts;

		Partials::card_open( __( 'Your societies and projects', 'estat-os' ) );

		Partials::toolbar(
			array(
				'page'         => 'estat-projects',
				'search'       => $search,
				'search_label' => __( 'Search by name', 'estat-os' ),
				'count'        => $total,
				'count_label'  => __( 'societies and projects', 'estat-os' ),
				'filters'      => array(
					array(
						'name'    => 'stage',
						'label'   => __( 'Stage', 'estat-os' ),
						'value'   => $stage,
						'options' => array( '' => __( 'Any stage', 'estat-os' ) ) + Vocabulary::project_statuses(),
					),
				),
			)
		);

		if ( ! $query->posts ) {
			Partials::empty_state(
				array(
					'icon'    => 'columns',
					'title'   => ( '' !== $search || '' !== $stage )
						? __( 'Nothing matched that', 'estat-os' )
						: __( 'No societies or projects yet', 'estat-os' ),
					'message' => ( '' !== $search || '' !== $stage )
						? __( 'Try a shorter word, or clear the stage filter.', 'estat-os' )
						: __( 'Add one using the form above. Once a society exists you can attach properties to it, and the office can see how many are still available.', 'estat-os' ),
				)
			);
			Partials::card_close();

			return;
		}

		/*
		 * Counting listings per project used to run one query per row. Ask
		 * once for all of them instead.
		 */
		$counts = Projects::listing_counts( wp_list_pluck( $query->posts, 'ID' ) );

		echo '<table class="widefat estat-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Name', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Stage', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Properties listed', 'estat-os' ) . '</th>';
		echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'estat-os' ) . '</span></th>';
		echo '</tr></thead><tbody>';

		foreach ( $query->posts as $project ) {
			$project_id = (int) $project->ID;

			echo '<tr>';
			echo '<th scope="row" data-label="' . esc_attr__( 'Name', 'estat-os' ) . '">' . esc_html( $project->post_title ) . '</th>';
			echo '<td data-label="' . esc_attr__( 'Stage', 'estat-os' ) . '">' . esc_html( Vocabulary::label( 'project_statuses', (string) get_post_meta( $project_id, '_estat_project_status', true ) ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Properties listed', 'estat-os' ) . '"><span class="estat-num">' . esc_html( (string) ( $counts[ $project_id ] ?? 0 ) ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Actions', 'estat-os' ) . '"><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=estat-projects&edit=' . $project_id ) ) . '">' . esc_html__( 'Edit', 'estat-os' ) . '</a> ';
			Partials::record_actions( $project_id, (string) $project->post_status );
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		Partials::pagination( (int) $query->max_num_pages, $paged, __( 'Pages of societies and projects', 'estat-os' ) );
		Partials::card_close();
	}
}
