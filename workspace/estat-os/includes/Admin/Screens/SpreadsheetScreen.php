<?php
/**
 * Import and export.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Csv\Spreadsheet;

defined( 'ABSPATH' ) || exit;

/**
 * Bring inventory in from a spreadsheet and take it back out again.
 */
final class SpreadsheetScreen {

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'estat_import_inventory' ) ) {
			wp_die( esc_html__( 'You do not have permission to import or export.', 'estat-os' ) );
		}

		Partials::card_open( __( 'Bring properties in from a spreadsheet', 'estat-os' ), __( 'Save your sheet as a CSV file first. Excel and Google Sheets can both do this from the File menu.', 'estat-os' ) );
		?>
		<form method="post" enctype="multipart/form-data" class="estat-form-admin" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-spreadsheet' ) ); ?>">
			<?php wp_nonce_field( 'estat_import_csv', 'estat_nonce' ); ?>
			<input type="hidden" name="estat_action" value="import_csv" />
			<div class="estat-field-row">
				<label class="estat-field-label" for="estat-csv"><?php esc_html_e( 'Choose your CSV file', 'estat-os' ); ?></label>
				<input type="file" id="estat-csv" name="estat_csv" accept=".csv,text/csv" required />
				<p class="description"><?php esc_html_e( 'Up to 20 MB. Large files are handled a few rows at a time so your website stays fast.', 'estat-os' ); ?></p>
			</div>
			<?php
			Partials::field(
				array(
					'name'           => 'update_existing',
					'type'           => 'checkbox',
					'label'          => __( 'Rows that already exist', 'estat-os' ),
					'checkbox_label' => __( 'Update them instead of skipping them', 'estat-os' ),
					'value'          => true,
					'help'           => __( 'We match on your own reference number, so nothing is duplicated.', 'estat-os' ),
				)
			);
			Partials::field(
				array(
					'name'           => 'publish_new',
					'type'           => 'checkbox',
					'label'          => __( 'New properties', 'estat-os' ),
					'checkbox_label' => __( 'Publish them straight away', 'estat-os' ),
					'value'          => false,
					'help'           => __( 'Leave this off to bring them in as drafts and check them first. This is the safer choice.', 'estat-os' ),
				)
			);
			?>
			<div class="estat-form-actions-admin">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Start the import', 'estat-os' ); ?></button>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=estat-spreadsheet&estat_action=sample_csv' ), 'estat_sample_csv', 'estat_nonce' ) ); ?>"><?php esc_html_e( 'Download a sample file', 'estat-os' ); ?></a>
			</div>
		</form>
		<?php
		Partials::card_close();

		Partials::card_open( __( 'What the columns mean', 'estat-os' ), __( 'Only the first column is required. Leave out any column you do not use.', 'estat-os' ) );
		echo '<table class="widefat estat-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Column heading', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'What to put in it', 'estat-os' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( Spreadsheet::columns( 'listings' ) as $column => $description ) {
			echo '<tr><th scope="row" data-label="' . esc_attr__( 'Column heading', 'estat-os' ) . '"><code>' . esc_html( (string) $column ) . '</code></th>';
			echo '<td data-label="' . esc_attr__( 'What to put in it', 'estat-os' ) . '">' . esc_html( (string) $description ) . '</td></tr>';
		}
		echo '</tbody></table>';
		Partials::card_close();

		Partials::card_open( __( 'Take your information out', 'estat-os' ), __( 'Everything is exported as a CSV file you can open in Excel or Google Sheets. Your data is always yours.', 'estat-os' ) );
		echo '<p class="estat-quick-actions">';
		foreach ( array(
			'listings' => __( 'Export properties', 'estat-os' ),
			'leads'    => __( 'Export enquiries', 'estat-os' ),
			'visits'   => __( 'Export site visits', 'estat-os' ),
			'projects' => __( 'Export societies', 'estat-os' ),
			'agents'   => __( 'Export the team', 'estat-os' ),
		) as $what => $label ) {
			$url = wp_nonce_url( admin_url( 'admin.php?page=estat-spreadsheet&estat_action=export_csv&what=' . $what ), 'estat_export_csv', 'estat_nonce' );
			echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html( (string) $label ) . '</a> ';
		}
		echo '</p>';
		Partials::card_close();

		$jobs = Spreadsheet::recent_jobs( 10 );
		if ( $jobs ) {
			Partials::card_open( __( 'Recent imports', 'estat-os' ) );
			echo '<table class="widefat estat-table"><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'File', 'estat-os' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Progress', 'estat-os' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Added', 'estat-os' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Updated', 'estat-os' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Skipped', 'estat-os' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $jobs as $job ) {
				$total = max( 1, (int) $job['total_rows'] );
				echo '<tr>';
				echo '<th scope="row" data-label="' . esc_attr__( 'File', 'estat-os' ) . '">' . esc_html( basename( (string) $job['file_path'] ) ) . '</th>';
				echo '<td data-label="' . esc_attr__( 'Progress', 'estat-os' ) . '">' . esc_html( sprintf( '%d%%', (int) round( ( (int) $job['processed_rows'] / $total ) * 100 ) ) ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Added', 'estat-os' ) . '">' . esc_html( (string) (int) $job['created_count'] ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Updated', 'estat-os' ) . '">' . esc_html( (string) (int) $job['updated_count'] ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Skipped', 'estat-os' ) . '">' . esc_html( (string) (int) $job['skipped_count'] ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			Partials::card_close();
		}
	}
}
