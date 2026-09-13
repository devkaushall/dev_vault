<?php
/**
 * Insights: blogs, articles and market insights on one screen.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Data\Insights;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Three kinds of writing live together here, because to an office they are the
 * same job: put something useful on the website. The tabs across the top switch
 * between Blogs, Articles and Insights.
 */
final class InsightsScreen {

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'estat_manage_insights' ) ) {
			wp_die( esc_html__( 'You do not have permission to write insights.', 'estat-os' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$kind    = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : 'blog';
		$edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable

		$kinds = Taxonomies::insight_kinds();
		if ( ! isset( $kinds[ $kind ] ) ) {
			$kind = 'blog';
		}

		self::tabs( $kind, $kinds );

		if ( $edit_id > 0 || ( isset( $_GET['new'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::editor( $edit_id, $kind );
			return;
		}

		self::listing( $kind, $kinds[ $kind ], $search, $paged );
	}

	/**
	 * The Blogs / Articles / Insights switcher.
	 *
	 * @param string                $current Active kind.
	 * @param array<string,string>  $kinds   All kinds.
	 * @return void
	 */
	private static function tabs( string $current, array $kinds ): void {
		echo '<nav class="estat-tabs" aria-label="' . esc_attr__( 'Kind of writing', 'estat-os' ) . '">';
		foreach ( $kinds as $slug => $label ) {
			$url   = admin_url( 'admin.php?page=estat-insights&kind=' . rawurlencode( $slug ) );
			$count = Insights::count( $slug );
			printf(
				'<a class="estat-tab%1$s" href="%2$s"%3$s>%4$s <span class="estat-tab-count">%5$d</span></a>',
				$slug === $current ? ' is-active' : '',
				esc_url( $url ),
				$slug === $current ? ' aria-current="page"' : '',
				esc_html( $label ),
				(int) $count
			);
		}
		echo '</nav>';
	}

	/**
	 * The list of existing pieces for one kind.
	 *
	 * @param string $kind   Kind slug.
	 * @param string $label  Kind label.
	 * @param string $search Search term.
	 * @param int    $paged  Page number.
	 * @return void
	 */
	private static function listing( string $kind, string $label, string $search, int $paged ): void {
		$new_url = admin_url( 'admin.php?page=estat-insights&kind=' . rawurlencode( $kind ) . '&new=1' );

		Partials::card_open(
			$label,
			sprintf(
				/* translators: %s: the kind, for example "Blog". */
				__( 'Everything filed under %s.', 'estat-os' ),
				$label
			)
		);
		?>
		<?php
		Partials::toolbar(
			array(
				'page'         => 'estat-insights',
				'hidden'       => array( 'kind' => $kind ),
				'search'       => $search,
				'search_label' => __( 'Search by title', 'estat-os' ),
				'action'       => sprintf(
					/* translators: %s: the kind, for example "Blog". */
					__( 'Write a new %s', 'estat-os' ),
					$label
				),
				'action_url'   => $new_url,
			)
		);
		?>
		<?php

		$result = Insights::query(
			array(
				'kind'     => $kind,
				'search'   => $search,
				'paged'    => $paged,
				'per_page' => 20,
			)
		);

		if ( ! $result['items'] ) {
			Partials::empty_state(
				sprintf(
					/* translators: %s: the kind in lower case. */
					__( 'Nothing here yet. Writing your first %s takes a few minutes and helps your website show up in search.', 'estat-os' ),
					strtolower( $label )
				),
				$new_url,
				sprintf(
					/* translators: %s: the kind. */
					__( 'Write a new %s', 'estat-os' ),
					$label
				)
			);
			Partials::card_close();
			return;
		}
		?>
		<table class="widefat estat-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Title', 'estat-os' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Topics', 'estat-os' ); ?></th>
					<th scope="col"><?php esc_html_e( 'State', 'estat-os' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Written', 'estat-os' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'estat-os' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $result['items'] as $item ) : ?>
					<tr>
						<th scope="row" data-label="<?php esc_attr_e( 'Title', 'estat-os' ); ?>">
							<a class="row-title" href="<?php echo esc_url( admin_url( 'admin.php?page=estat-insights&kind=' . rawurlencode( $kind ) . '&edit=' . (int) $item['id'] ) ); ?>">
								<?php echo esc_html( $item['title'] ); ?>
							</a>
						</th>
						<td data-label="<?php esc_attr_e( 'Topics', 'estat-os' ); ?>"><?php echo esc_html( $item['topics'] ? implode( ', ', $item['topics'] ) : '—' ); ?></td>
						<td data-label="<?php esc_attr_e( 'State', 'estat-os' ); ?>">
							<?php if ( 'publish' === $item['status'] ) : ?>
								<span class="estat-pill estat-pill-live"><?php esc_html_e( 'On the website', 'estat-os' ); ?></span>
							<?php else : ?>
								<span class="estat-pill"><?php esc_html_e( 'Draft', 'estat-os' ); ?></span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Written', 'estat-os' ); ?>"><?php echo esc_html( $item['date'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Actions', 'estat-os' ); ?>">
							<?php Partials::record_actions( (int) $item['id'], (string) $item['status'] ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		if ( $result['pages'] > 1 ) {
			echo '<div class="estat-pagination">';
			echo wp_kses_post(
				(string) paginate_links(
					array(
						'total'   => (int) $result['pages'],
						'current' => $paged,
						'base'    => admin_url( 'admin.php?page=estat-insights&kind=' . rawurlencode( $kind ) . '&paged=%#%' ),
						'format'  => '',
					)
				)
			);
			echo '</div>';
		}

		Partials::card_close();
	}

	/**
	 * The write/edit form.
	 *
	 * @param int    $edit_id Post ID, or 0 for a new piece.
	 * @param string $kind    Kind slug.
	 * @return void
	 */
	private static function editor( int $edit_id, string $kind ): void {
		$post = $edit_id > 0 ? get_post( $edit_id ) : null;
		if ( $post && PostTypes::INSIGHT !== $post->post_type ) {
			$post    = null;
			$edit_id = 0;
		}

		$topics = $edit_id > 0
			? wp_get_object_terms( $edit_id, Taxonomies::INSIGHT_TOPIC, array( 'fields' => 'names' ) )
			: array();
		$topics = is_array( $topics ) ? $topics : array();

		$all_topics = get_terms(
			array(
				'taxonomy'   => Taxonomies::INSIGHT_TOPIC,
				'hide_empty' => false,
			)
		);
		$all_topics = is_array( $all_topics ) ? $all_topics : array();

		Partials::card_open(
			$post ? __( 'Edit this piece', 'estat-os' ) : __( 'Write something new', 'estat-os' ),
			__( 'A clear title and a few paragraphs are enough to start. You can save it as a draft and come back.', 'estat-os' )
		);
		?>
		<form method="post" class="estat-form-admin" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-insights' ) ); ?>">
			<?php wp_nonce_field( 'estat_save_insight_' . $edit_id, 'estat_nonce' ); ?>
			<input type="hidden" name="estat_action" value="save_insight" />
			<input type="hidden" name="insight_id" value="<?php echo esc_attr( (string) $edit_id ); ?>" />

			<?php
			Partials::field(
				array(
					'name'     => 'title',
					'label'    => __( 'Title', 'estat-os' ),
					'value'    => $post ? $post->post_title : '',
					'required' => true,
					'example'  => __( 'Why Dwarka Expressway is drawing buyers this year', 'estat-os' ),
				)
			);

			Partials::field(
				array(
					'name'    => 'kind',
					'type'    => 'select',
					'label'   => __( 'What kind is this?', 'estat-os' ),
					'value'   => $kind,
					'options' => Taxonomies::insight_kinds(),
					'help'    => __( 'Blogs are short and personal. Articles are longer and researched. Insights are market observations with numbers.', 'estat-os' ),
				)
			);

			Partials::field(
				array(
					'name'  => 'excerpt',
					'type'  => 'textarea',
					'rows'  => 3,
					'label' => __( 'Short summary', 'estat-os' ),
					'value' => $post ? $post->post_excerpt : '',
					'help'  => __( 'One or two lines. This is what people see in the list and on search engines.', 'estat-os' ),
				)
			);
			?>

			<div class="estat-field">
				<label for="estat-insight-body"><?php esc_html_e( 'The writing', 'estat-os' ); ?></label>
				<?php
				wp_editor(
					$post ? $post->post_content : '',
					'estat-insight-body',
					array(
						'textarea_name' => 'content',
						'textarea_rows' => 18,
						'media_buttons' => true,
						'teeny'         => false,
					)
				);
				?>
			</div>

			<div class="estat-field">
				<label for="estat-insight-topics"><?php esc_html_e( 'Topics', 'estat-os' ); ?></label>
				<input
					type="text"
					id="estat-insight-topics"
					name="topics"
					value="<?php echo esc_attr( implode( ', ', $topics ) ); ?>"
					list="estat-topic-list"
					placeholder="<?php esc_attr_e( 'Market Trends, Home Loans', 'estat-os' ); ?>"
				/>
				<datalist id="estat-topic-list">
					<?php foreach ( $all_topics as $term ) : ?>
						<option value="<?php echo esc_attr( $term->name ); ?>"></option>
					<?php endforeach; ?>
				</datalist>
				<p class="description"><?php esc_html_e( 'Separate topics with commas. New topics are created automatically.', 'estat-os' ); ?></p>
			</div>

			<?php
			Partials::field(
				array(
					'name'  => 'cover_id',
					'type'  => 'image',
					'label' => __( 'Cover picture', 'estat-os' ),
					'value' => $edit_id > 0 ? get_post_thumbnail_id( $edit_id ) : '',
				)
			);
			?>

			<div class="estat-form-actions-admin">
				<button type="submit" name="estat_save_mode" value="draft" class="button button-secondary button-hero"><?php esc_html_e( 'Save as draft', 'estat-os' ); ?></button>
				<button type="submit" name="estat_save_mode" value="publish" class="button button-primary button-hero"><?php esc_html_e( 'Put it on the website', 'estat-os' ); ?></button>
				<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=estat-insights&kind=' . rawurlencode( $kind ) ) ); ?>"><?php esc_html_e( 'Cancel', 'estat-os' ); ?></a>
			</div>
		</form>
		<?php
		Partials::card_close();
	}
}
