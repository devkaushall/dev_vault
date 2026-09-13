<?php
/**
 * Gallery: every photo, brochure and document the office keeps.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Data\Media;
use EstatOS\Data\PostTypes;
use EstatOS\Support\Icon;

defined( 'ABSPATH' ) || exit;

/**
 * The office's own file screen. It replaces the raw WordPress media library,
 * which shows every file on the site, uses language an estate office does not
 * need, and loads a heavy uploader some browsers treat with suspicion.
 */
final class GalleryScreen {

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'estat_manage_listings' ) ) {
			wp_die( esc_html__( 'You do not have permission to open the gallery.', 'estat-os' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$kind   = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : 'photo';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$linked = isset( $_GET['linked'] ) ? sanitize_key( wp_unslash( $_GET['linked'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable

		$kinds = Media::kinds();
		if ( ! isset( $kinds[ $kind ] ) ) {
			$kind = 'photo';
		}

		self::tabs( $kind, $kinds );
		self::uploader( $kind );

		$result = Media::query(
			array(
				'kind'       => $kind,
				'search'     => $search,
				'unattached' => 'no' === $linked,
				'paged'      => $paged,
				'per_page'   => 40,
			)
		);

		self::toolbar( $kind, $search, $linked, (int) $result['total'] );

		if ( ! $result['items'] ) {
			Partials::card_open();
			Partials::empty_state(
				'' !== $search
					? array(
						'icon'    => 'search',
						'title'   => __( 'Nothing matched', 'estat-os' ),
						'message' => __( 'No file here has that name. Try a shorter word.', 'estat-os' ),
						'action'  => admin_url( 'admin.php?page=estat-gallery&kind=' . $kind ),
						'label'   => __( 'Show all files', 'estat-os' ),
					)
					: array(
						'icon'       => 'image',
						'title'      => __( 'No files here yet', 'estat-os' ),
						'message'    => __( 'Drag photos straight onto the box above, or use the Choose files button. Everything you upload stays in one place and can be attached to any property.', 'estat-os' ),
						'reassuring' => true,
					)
			);
			Partials::card_close();
			return;
		}

		'photo' === $kind || 'floorplan' === $kind
			? self::grid( $result['items'] )
			: self::file_list( $result['items'] );

		self::pagination( $result, $kind, $search, $linked );
	}

	/**
	 * Photos / Floor plans / Brochures / Documents.
	 *
	 * @param string               $current Active kind.
	 * @param array<string,string> $kinds   All kinds.
	 * @return void
	 */
	private static function tabs( string $current, array $kinds ): void {
		$counts = Media::counts();

		echo '<nav class="estat-tabs" aria-label="' . esc_attr__( 'Kind of file', 'estat-os' ) . '">';
		foreach ( $kinds as $slug => $label ) {
			printf(
				'<a class="estat-tab%1$s" href="%2$s"%3$s>%4$s <span class="estat-tab-count">%5$d</span></a>',
				$slug === $current ? ' is-active' : '',
				esc_url( admin_url( 'admin.php?page=estat-gallery&kind=' . rawurlencode( $slug ) ) ),
				$slug === $current ? ' aria-current="page"' : '',
				esc_html( $label ),
				(int) ( $counts[ $slug ] ?? 0 )
			);
		}
		echo '</nav>';
	}

	/**
	 * The drop area.
	 *
	 * @param string $kind Active kind.
	 * @return void
	 */
	private static function uploader( string $kind ): void {
		$accept = 'photo' === $kind || 'floorplan' === $kind
			? 'image/*'
			: '.pdf,.doc,.docx,.xls,.xlsx';
		?>
		<div class="estat-dropzone" data-kind="<?php echo esc_attr( $kind ); ?>">
			<input
				type="file"
				class="estat-dropzone-input"
				id="estat-file-input"
				multiple
				accept="<?php echo esc_attr( $accept ); ?>"
				aria-label="<?php esc_attr_e( 'Choose files to upload', 'estat-os' ); ?>"
			/>
			<div class="estat-dropzone-inner">
				<span class="estat-dropzone-icon"><?php echo Icon::render( 'upload', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<p class="estat-dropzone-title"><?php esc_html_e( 'Drag files here to add them', 'estat-os' ); ?></p>
				<p class="estat-dropzone-hint">
					<?php
					if ( 'photo' === $kind || 'floorplan' === $kind ) {
						esc_html_e( 'JPG, PNG or WebP. You can drop many at once.', 'estat-os' );
					} else {
						esc_html_e( 'PDF, Word or Excel files.', 'estat-os' );
					}
					?>
				</p>
				<label class="button button-primary" for="estat-file-input"><?php esc_html_e( 'Choose files', 'estat-os' ); ?></label>
			</div>
			<div class="estat-dropzone-progress" hidden>
				<div class="estat-dropzone-bar"><span></span></div>
				<p class="estat-dropzone-status" role="status" aria-live="polite"></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Search and filter row.
	 *
	 * @param string $kind   Active kind.
	 * @param string $search Search term.
	 * @param string $linked Link filter.
	 * @param int    $total  How many matched.
	 * @return void
	 */
	private static function toolbar( string $kind, string $search, string $linked, int $total ): void {
		Partials::toolbar(
			array(
				'page'         => 'estat-gallery',
				'hidden'       => array( 'kind' => $kind ),
				'search'       => $search,
				'search_label' => __( 'Search by file name', 'estat-os' ),
				'count'        => $total,
				'count_label'  => sprintf(
					/* translators: %s: number of files. */
					_n( '%s file', '%s files', $total, 'estat-os' ),
					number_format_i18n( $total )
				),
				'filters'      => array(
					array(
						'name'    => 'linked',
						'label'   => __( 'Show', 'estat-os' ),
						'value'   => $linked,
						'options' => array(
							''   => __( 'All files', 'estat-os' ),
							'no' => __( 'Not attached to anything', 'estat-os' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Picture grid.
	 *
	 * @param array<int,array<string,mixed>> $items Files.
	 * @return void
	 */
	private static function grid( array $items ): void {
		echo '<ul class="estat-gallery-grid">';

		foreach ( $items as $item ) {
			?>
			<li class="estat-tile" data-id="<?php echo esc_attr( (string) $item['id'] ); ?>">
				<a
					class="estat-tile-image"
					href="<?php echo esc_url( $item['url'] ); ?>"
					target="_blank"
					rel="noopener"
					aria-label="<?php echo esc_attr( sprintf( /* translators: %s: file name. */ __( 'Open %s in a new tab', 'estat-os' ), $item['title'] ) ); ?>"
				>
					<?php if ( $item['thumb'] ) : ?>
						<img
							src="<?php echo esc_url( $item['thumb'] ); ?>"
							alt="<?php echo esc_attr( $item['alt'] ? $item['alt'] : $item['title'] ); ?>"
							loading="lazy"
							decoding="async"
						/>
					<?php else : ?>
						<span class="estat-tile-placeholder"><?php echo Icon::render( 'image', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php endif; ?>
				</a>

				<div class="estat-tile-body">
					<p class="estat-tile-title" title="<?php echo esc_attr( $item['title'] ); ?>"><?php echo esc_html( $item['title'] ); ?></p>
					<p class="estat-tile-meta">
						<?php echo esc_html( $item['filesize'] ); ?>
						<?php if ( $item['linked_name'] ) : ?>
							<span class="estat-pill is-good"><?php echo esc_html( $item['linked_name'] ); ?></span>
						<?php endif; ?>
					</p>
				</div>

				<div class="estat-tile-actions">
					<a class="button button-small" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open', 'estat-os' ); ?></a>
					<?php self::delete_button( (int) $item['id'] ); ?>
				</div>
			</li>
			<?php
		}

		echo '</ul>';
	}

	/**
	 * Plain list for brochures and documents.
	 *
	 * @param array<int,array<string,mixed>> $items Files.
	 * @return void
	 */
	private static function file_list( array $items ): void {
		?>
		<table class="widefat estat-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'File', 'estat-os' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Belongs to', 'estat-os' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Size', 'estat-os' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Added', 'estat-os' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'estat-os' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<th scope="row" data-label="<?php esc_attr_e( 'File', 'estat-os' ); ?>">
							<a class="row-title" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener">
								<?php echo esc_html( $item['title'] ); ?>
							</a>
						</th>
						<td data-label="<?php esc_attr_e( 'Belongs to', 'estat-os' ); ?>"><?php echo $item['linked_name'] ? esc_html( $item['linked_name'] ) : '—'; ?></td>
						<td data-label="<?php esc_attr_e( 'Size', 'estat-os' ); ?>"><?php echo esc_html( $item['filesize'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Added', 'estat-os' ); ?>"><?php echo esc_html( $item['date'] ); ?></td>
						<td class="estat-cell-actions" data-label="<?php esc_attr_e( 'Actions', 'estat-os' ); ?>"><?php self::delete_button( (int) $item['id'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * A delete button that cannot fire by accident.
	 *
	 * @param int $id Attachment ID.
	 * @return void
	 */
	private static function delete_button( int $id ): void {
		if ( ! current_user_can( 'estat_delete_listings' ) ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?page=estat-gallery&estat_action=delete_file&file_id=' . $id ),
			'estat_delete_file_' . $id,
			'estat_nonce'
		);
		?>
		<a
			class="button button-small estat-button-danger"
			href="<?php echo esc_url( $url ); ?>"
			data-estat-confirm="<?php esc_attr_e( 'Remove this file? It will be gone from any property using it.', 'estat-os' ); ?>"
		><?php esc_html_e( 'Remove', 'estat-os' ); ?></a>
		<?php
	}

	/**
	 * Page links.
	 *
	 * @param array<string,mixed> $result Query result.
	 * @param string              $kind   Active kind.
	 * @param string              $search Search term.
	 * @param string              $linked Link filter.
	 * @return void
	 */
	private static function pagination( array $result, string $kind, string $search, string $linked ): void {
		if ( (int) $result['pages'] < 2 ) {
			return;
		}

		$base = add_query_arg(
			array(
				'page'   => 'estat-gallery',
				'kind'   => $kind,
				's'      => $search,
				'linked' => $linked,
				'paged'  => '%#%',
			),
			admin_url( 'admin.php' )
		);

		echo '<div class="estat-pagination">';
		echo wp_kses_post(
			(string) paginate_links(
				array(
					'total'   => (int) $result['pages'],
					'current' => (int) $result['page'],
					'base'    => $base,
					'format'  => '',
				)
			)
		);
		echo '</div>';
	}
}
