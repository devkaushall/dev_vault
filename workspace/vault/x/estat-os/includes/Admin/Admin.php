<?php
/**
 * Office admin: menu, assets and screen routing.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin;

use EstatOS\Data\PostTypes;
use EstatOS\Data\Taxonomies;
use EstatOS\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One top-level "Office" menu written in plain language. WordPress's own
 * post-type screens stay available for advanced users but are kept out of the
 * way so the menu does not fill up with technical entries.
 */
final class Admin {

	/**
	 * Top level menu slug.
	 */
	public const MENU = 'estat-today';

	/**
	 * Screen handlers keyed by page slug.
	 *
	 * @var array<string,callable>
	 */
	private array $screens = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->screens = array(
			'estat-today'       => array( Screens\Today::class, 'render' ),
			'estat-add-home'    => array( Screens\AddHome::class, 'render' ),
			'estat-listings'    => array( Screens\ListingsScreen::class, 'render' ),
			'estat-enquiries'   => array( Screens\Enquiries::class, 'render' ),
			'estat-visits'      => array( Screens\VisitsScreen::class, 'render' ),
			'estat-projects'    => array( Screens\ProjectsScreen::class, 'render' ),
			'estat-team'        => array( Screens\TeamScreen::class, 'render' ),
			'estat-forms'       => array( Screens\FormsScreen::class, 'render' ),
			'estat-spreadsheet' => array( Screens\SpreadsheetScreen::class, 'render' ),
			'estat-reports'     => array( Screens\Reports::class, 'render' ),
			'estat-gallery'     => array( Screens\GalleryScreen::class, 'render' ),
			'estat-insights'    => array( Screens\InsightsScreen::class, 'render' ),
			'estat-settings'    => array( Screens\SettingsScreen::class, 'render' ),
		);

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_init', array( Actions::class, 'handle' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_head', array( $this, 'hide_clutter' ) );
		add_action( 'wp_ajax_estat_upload_file', array( __CLASS__, 'ajax_upload' ) );
		add_action( 'admin_notices', array( $this, 'setup_nudge' ) );
		add_action( 'admin_init', array( $this, 'maybe_greet' ), 5 );
	}

	/**
	 * Send a freshly installed office to the welcome screen, once.
	 *
	 * The flag is cleared before redirecting, so a redirect loop is impossible
	 * even if something later goes wrong.
	 *
	 * @return void
	 */
	public function maybe_greet(): void {
		if ( '1' !== (string) get_option( 'estat_show_setup', '' ) ) {
			return;
		}
		delete_option( 'estat_show_setup' );

		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		if ( ! current_user_can( 'estat_manage_settings' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . Screens\SetupScreen::SLUG ) );
		exit;
	}

	/**
	 * Point a brand-new office at the setup screen.
	 *
	 * Shown once, on our own screens only, and it disappears for good as soon
	 * as setup is finished or skipped.
	 *
	 * @return void
	 */
	public function setup_nudge(): void {
		if ( ! current_user_can( 'estat_manage_settings' ) || ! Screens\SetupScreen::needed() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, 'estat-' ) || Screens\SetupScreen::SLUG === $page ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p><strong>%1$s</strong> %2$s <a class="button button-primary" href="%3$s">%4$s</a></p></div>',
			esc_html__( 'Your website is not showing your properties yet.', 'estat-os' ),
			esc_html__( 'Answer four short questions and we will build the pages for you.', 'estat-os' ),
			esc_url( admin_url( 'admin.php?page=' . Screens\SetupScreen::SLUG ) ),
			esc_html__( 'Finish setup', 'estat-os' )
		);
	}

	/**
	 * Build the office menu.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$cap = 'estat_manage_listings';

		add_menu_page(
			__( 'Office', 'estat-os' ),
			__( 'Office', 'estat-os' ),
			$cap,
			self::MENU,
			array( $this, 'route' ),
			'dashicons-building',
			3
		);

		$pages = array(
			array( 'estat-today', __( 'Today', 'estat-os' ), $cap ),
			array( 'estat-add-home', __( 'Add a Home', 'estat-os' ), $cap ),
			array( 'estat-listings', __( 'Listings', 'estat-os' ), $cap ),
			array( 'estat-enquiries', __( 'Enquiries', 'estat-os' ), 'estat_manage_leads' ),
			array( 'estat-visits', __( 'Site Visits', 'estat-os' ), 'estat_manage_visits' ),
			array( 'estat-projects', __( 'Societies & Projects', 'estat-os' ), 'estat_manage_projects' ),
			array( 'estat-team', __( 'Team', 'estat-os' ), 'estat_manage_team' ),
			array( 'estat-forms', __( 'Forms', 'estat-os' ), 'estat_manage_forms' ),
			array( 'estat-spreadsheet', __( 'Spreadsheet', 'estat-os' ), 'estat_import_inventory' ),
			array( 'estat-reports', __( 'Reports', 'estat-os' ), 'estat_view_reports' ),
			array( 'estat-settings', __( 'Office Settings', 'estat-os' ), 'estat_manage_settings' ),
		);

		foreach ( $pages as $page ) {
			list( $slug, $title, $capability ) = $page;
			add_submenu_page(
				self::MENU,
				$title,
				$title,
				$capability,
				$slug,
				array( $this, 'route' )
			);
		}

		// Replace the duplicated first entry with a friendly name.
		global $submenu;
		if ( isset( $submenu[ self::MENU ][0][0] ) ) {
			unset( $submenu[ self::MENU ][0] );
		}

		// The office's own file screen, in place of the raw media library.
		add_submenu_page(
			self::MENU,
			__( 'Gallery & Media', 'estat-os' ),
			__( 'Gallery & Media', 'estat-os' ),
			$cap,
			'estat-gallery',
			array( Screens\GalleryScreen::class, 'render' )
		);

		// Only shown while the office still needs it, so the menu does not carry
		// a permanent reminder of a job already done.
		if ( Screens\SetupScreen::needed() ) {
			add_submenu_page(
				self::MENU,
				__( 'Finish setup', 'estat-os' ),
				__( 'Finish setup', 'estat-os' ),
				'estat_manage_settings',
				Screens\SetupScreen::SLUG,
				array( Screens\SetupScreen::class, 'render' )
			);
		} else {
			// Reachable by link once dismissed, but not advertised.
			add_submenu_page(
				'',
				__( 'Office setup', 'estat-os' ),
				'',
				'estat_manage_settings',
				Screens\SetupScreen::SLUG,
				array( Screens\SetupScreen::class, 'render' )
			);
		}

		// Blogs, articles and market insights, all on one screen.
		add_submenu_page(
			self::MENU,
			__( 'Insights', 'estat-os' ),
			__( 'Insights', 'estat-os' ),
			'estat_manage_insights',
			'estat-insights',
			array( Screens\InsightsScreen::class, 'render' )
		);
	}

	/**
	 * Render the requested screen.
	 *
	 * @return void
	 */
	public function route(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::MENU;
		$handler = $this->screens[ $page ] ?? $this->screens['estat-today'];

		/*
		 * The form studio runs three working columns side by side, so it is
		 * allowed the full window. Every other screen keeps the comfortable
		 * reading width.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$editing_form = 'estat-forms' === $page && ! empty( $_GET['edit'] );
		$wrap_class   = $editing_form ? 'wrap estat-admin estat-wrap-wide' : 'wrap estat-admin';

		echo '<div class="' . esc_attr( $wrap_class ) . '">';
		Screens\Partials::header( $page, $editing_form );
		if ( is_callable( $handler ) ) {
			call_user_func( $handler );
		}
		echo '</div>';
	}

	/**
	 * Load admin assets only on office screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function assets( string $hook ): void {
		if ( false === strpos( $hook, 'estat-' ) && 'toplevel_page_' . self::MENU !== $hook ) {
			return;
		}

		wp_enqueue_style( 'estat-admin', ESTAT_URL . 'assets/css/admin.css', array(), ESTAT_VERSION );
		wp_enqueue_script( 'estat-admin', ESTAT_URL . 'assets/js/admin.js', array( 'wp-i18n' ), ESTAT_VERSION, true );
		wp_enqueue_media();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'estat-forms' === $page ) {
			wp_enqueue_style( 'estat-forms', ESTAT_URL . 'assets/css/forms.css', array(), ESTAT_VERSION );
			wp_enqueue_script( 'estat-form-builder', ESTAT_URL . 'assets/js/form-builder.js', array( 'estat-admin' ), ESTAT_VERSION, true );
			wp_localize_script(
				'estat-form-builder',
				'estatBuilder',
				array(
					'fieldTypes' => \EstatOS\Forms\FieldTypes::all(),
					'i18n'       => array(
						'newField'     => __( 'New field', 'estat-os' ),
						'confirmDelete'=> __( 'Remove this field?', 'estat-os' ),
						'label'        => __( 'Label', 'estat-os' ),
						'placeholder'  => __( 'Example text inside the box', 'estat-os' ),
						'help'         => __( 'Short explanation', 'estat-os' ),
						'required'     => __( 'This information is required', 'estat-os' ),
						'width'        => __( 'Width on a computer', 'estat-os' ),
						'widthTablet'  => __( 'Width on a tablet', 'estat-os' ),
						'widthMobile'  => __( 'Width on a phone', 'estat-os' ),
						'options'      => __( 'Choices (one per line)', 'estat-os' ),
						'remove'       => __( 'Remove', 'estat-os' ),
						'addRow'       => __( 'Add a row', 'estat-os' ),
						'addColumn'    => __( 'Add a column', 'estat-os' ),
						'dragHint'     => __( 'Drag to reorder', 'estat-os' ),
						'moveUp'       => __( 'Move up', 'estat-os' ),
						'moveDown'     => __( 'Move down', 'estat-os' ),

						// Row layout.
						'layout'       => __( 'Columns', 'estat-os' ),
						'gap'          => __( 'Spacing', 'estat-os' ),
						'align'        => __( 'Align', 'estat-os' ),
						'alignStretch' => __( 'Same height', 'estat-os' ),
						'alignStart'   => __( 'Top', 'estat-os' ),
						'alignCenter'  => __( 'Middle', 'estat-os' ),
						'alignEnd'     => __( 'Bottom', 'estat-os' ),
						'rowHeading'   => __( 'Optional heading for this row', 'estat-os' ),
						'columnWidth'  => __( 'Width', 'estat-os' ),
						'removeColumn' => __( 'Remove this column', 'estat-os' ),
						'confirmDeleteRow' => __( 'Remove this row and everything in it?', 'estat-os' ),

						// Field size.
						'boxHeight'    => __( 'Box height', 'estat-os' ),
						'boxWidth'     => __( 'Box width', 'estat-os' ),
						'sizeSM'       => __( 'Small', 'estat-os' ),
						'sizeMD'       => __( 'Medium', 'estat-os' ),
						'sizeLG'       => __( 'Large', 'estat-os' ),
						'lines'        => __( 'Lines of space to type in', 'estat-os' ),
						'exactWidths'  => __( 'Exact widths', 'estat-os' ),

						// Device switch.
						'deviceBar'    => __( 'Show the layout as it appears on', 'estat-os' ),
						'deviceDesktop'=> __( 'Computer', 'estat-os' ),
						'deviceTablet' => __( 'Tablet', 'estat-os' ),
						'deviceMobile' => __( 'Phone', 'estat-os' ),
						'deviceHintDesktop' => __( 'Editing computer widths.', 'estat-os' ),
						'deviceHintTablet'  => __( 'Editing tablet widths only.', 'estat-os' ),
						'deviceHintMobile'  => __( 'Editing phone widths only.', 'estat-os' ),

						// Conditions.
						'conditionOn'  => __( 'Only shown sometimes', 'estat-os' ),
						'conditionOff' => __( 'Always shown', 'estat-os' ),
						'conditionWhen'=> __( 'Only show this box when', 'estat-os' ),
						'conditionIs'  => __( 'is', 'estat-os' ),
						'conditionAlways' => __( '— always show it —', 'estat-os' ),
						'conditionValue'  => __( 'has this answer', 'estat-os' ),

						// Live preview.
						'previewEmpty' => __( 'Add a field and it will appear here.', 'estat-os' ),
						'previewSend'  => __( 'Send', 'estat-os' ),
						'previewChoices' => __( 'Choices', 'estat-os' ),
						'previewHidden'  => __( 'Hidden on this device', 'estat-os' ),
					),
				)
			);
		}

		wp_localize_script(
			'estat-admin',
			'estatAdmin',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'estat/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'uploadNonce' => wp_create_nonce( 'estat_upload' ),
				'urls'          => array(
					'today'      => admin_url( 'admin.php?page=estat-today' ),
					'listings'   => admin_url( 'admin.php?page=estat-listings' ),
					'enquiries'  => admin_url( 'admin.php?page=estat-enquiries' ),
					'addHome'    => admin_url( 'admin.php?page=estat-add-home' ),
				),
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'i18n'     => array(
					'confirmDelete' => __( 'Are you sure? This cannot be undone.', 'estat-os' ),
					'typeDelete'    => __( 'Type DELETE in capital letters to confirm.', 'estat-os' ),
					'chooseImage'   => __( 'Choose an image', 'estat-os' ),
					'useImage'      => __( 'Use this image', 'estat-os' ),
					'tapToChoose'   => __( 'Tap to choose a photo', 'estat-os' ),

					// Acting on several records at once.
					'bulkOne'       => __( '1 ticked', 'estat-os' ),
					/* translators: %d: number of records ticked. */
					'bulkMany'      => __( '%d ticked', 'estat-os' ),
					/* translators: %d: number of records. */
					'bulkConfirmTrash' => __( 'Move %d records to the bin? You can bring them back afterwards.', 'estat-os' ),

					// Keyboard shortcuts.
					'shortcutsTitle'   => __( 'Keyboard shortcuts', 'estat-os' ),
					'shortcutSearch'   => __( 'Jump to the search box', 'estat-os' ),
					'shortcutNew'      => __( 'Add a Home', 'estat-os' ),
					'shortcutToday'    => __( 'Go to Today', 'estat-os' ),
					'shortcutListings' => __( 'Go to Listings', 'estat-os' ),
					'shortcutEnquiries' => __( 'Go to Enquiries', 'estat-os' ),
					'shortcutSave'     => __( 'Save what you are editing', 'estat-os' ),
					'shortcutHelp'     => __( 'Show this list', 'estat-os' ),
					'shortcutClose'    => __( 'Close this list', 'estat-os' ),
					'shortcutCloseButton' => __( 'Close', 'estat-os' ),
					'saving'        => __( 'Saving…', 'estat-os' ),
					'copied'        => __( 'Copied.', 'estat-os' ),
					'uploading'     => __( 'Adding your files…', 'estat-os' ),
					'uploadDone'    => __( 'Done. Refreshing…', 'estat-os' ),
					'uploadFailed'  => __( 'Could not add this file:', 'estat-os' ),
					'tooBig'        => __( 'This file is too large to upload.', 'estat-os' ),
				),
			)
		);
	}

	/**
	 * Receive one file from the gallery drop area.
	 *
	 * Kept deliberately small: check who is asking, check the request is
	 * genuine, then hand the file to WordPress's own uploader so all the usual
	 * file-type and permission rules still apply.
	 *
	 * @return void
	 */
	public static function ajax_upload(): void {
		check_ajax_referer( 'estat_upload', 'nonce' );

		if ( ! current_user_can( 'estat_manage_listings' ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to add files.', 'estat-os' ) ), 403 );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file arrived. Please try again.', 'estat-os' ) ), 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload( 'file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ), 400 );
		}

		$attachment_id = (int) $attachment_id;

		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		if ( '' !== $kind ) {
			\EstatOS\Data\Media::set_kind( $attachment_id, $kind );
		}

		\EstatOS\Audit\AuditLog::record( 'media.uploaded', 'attachment', $attachment_id, array( 'kind' => $kind ) );

		wp_send_json_success( \EstatOS\Data\Media::to_array( $attachment_id ) );
	}

	/**
	 * Add a body class so admin styles can scope themselves.
	 *
	 * @param string $classes Existing classes.
	 * @return string
	 */
	public function body_class( string $classes ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 === strpos( $page, 'estat-' ) ) {
			$classes .= ' estat-screen';

			// Light, dark, or follow the computer's own setting.
			$theme = (string) Settings::get( 'admin_theme' );
			if ( ! in_array( $theme, array( 'light', 'dark', 'auto' ), true ) ) {
				$theme = 'light';
			}
			$classes .= ' estat-theme-' . $theme;

			if ( Settings::get( 'practice_mode' ) ) {
				$classes .= ' estat-practice';
			}
		}
		return $classes;
	}

	/**
	 * Keep raw taxonomy screens out of the way of ordinary office users.
	 *
	 * @return void
	 */
	public function hide_clutter(): void {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		remove_submenu_page( 'edit.php?post_type=' . PostTypes::LISTING, 'edit-tags.php?taxonomy=' . Taxonomies::FEATURE . '&amp;post_type=' . PostTypes::LISTING );
	}
}
