<?php
/**
 * Handles every form submission and action link in the office screens.
 *
 * One entry point, one nonce per action, one capability check per action,
 * and always a redirect afterwards so a refresh never repeats the work.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin;

use EstatOS\Audit\AuditLog;
use EstatOS\Csv\Spreadsheet;
use EstatOS\Cron\Scheduler;
use EstatOS\Data\Agents;
use EstatOS\Data\Insights;
use EstatOS\Data\Listings;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Projects;
use EstatOS\Forms\Forms;
use EstatOS\Forms\Templates;
use EstatOS\Admin\Screens\SetupScreen;
use EstatOS\Install\Pages;
use EstatOS\Install\Schema;
use EstatOS\Leads\Leads;
use EstatOS\Leads\Visits;
use EstatOS\Search\SearchIndex;
use EstatOS\Settings\Settings;
use EstatOS\Support\PracticeMode;
use EstatOS\Support\Sanitize;
use WP_Error;
use EstatOS\Data\Undo;

defined( 'ABSPATH' ) || exit;

/**
 * Admin action router.
 */
final class Actions {

	/**
	 * Which capability each action needs.
	 *
	 * @var array<string,string>
	 */
	/**
	 * The most records one bulk action may touch.
	 */
	public const BULK_LIMIT = 100;

	private const CAPS = array(
		'add_home'         => 'estat_manage_listings',
		'save_insight'     => 'estat_manage_insights',
		'delete_file'      => 'estat_delete_listings',
		'save_listing'     => 'estat_manage_listings',
		'trash_listing'    => 'estat_delete_listings',
		// The real permission depends on what kind of record it is, so these
		// three are checked again, per kind, inside record_target().
		// Bulk acts on many records at once; each one is checked individually
		// against its own kind inside bulk_records().
		'bulk_records'     => 'read',
		// Undo is an edit, so it is checked per kind like the others.
		'undo_record'      => 'read',
		'trash_record'     => 'read',
		'restore_record'   => 'read',
		'delete_record'    => 'read',
		'update_lead'      => 'estat_manage_leads',
		'schedule_visit'   => 'estat_manage_visits',
		'update_visit'     => 'estat_manage_visits',
		'erase_lead'       => 'estat_erase_leads',
		'save_project'     => 'estat_manage_projects',
		'save_agent'       => 'estat_manage_team',
		'create_form'      => 'estat_manage_forms',
		'save_form'        => 'estat_manage_forms',
		'duplicate_form'   => 'estat_manage_forms',
		'delete_form'      => 'estat_manage_forms',
		'import_csv'       => 'estat_import_inventory',
		'export_csv'       => 'estat_export_inventory',
		'sample_csv'       => 'estat_import_inventory',
		'save_settings'    => 'estat_manage_settings',
		'run_setup'        => 'estat_manage_settings',
		'skip_setup'       => 'estat_manage_settings',
		'rebuild_index'    => 'estat_run_maintenance',
		'repair_tables'    => 'estat_run_maintenance',
		'clear_cache'      => 'estat_run_maintenance',
		'run_housekeeping' => 'estat_run_maintenance',
		'seed_practice'    => 'estat_run_maintenance',
		'clear_practice'   => 'estat_run_maintenance',
	);

	/**
	 * Module bootstrap. Registration happens in Admin.
	 *
	 * @return void
	 */
	public function register(): void {}

	/**
	 * Route one request.
	 *
	 * @return void
	 */
	public static function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['estat_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['estat_action'] ) ) : '';
		if ( '' === $action || ! isset( self::CAPS[ $action ] ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPS[ $action ] ) ) {
			self::redirect( self::back(), 'forbidden' );
		}

		switch ( $action ) {
			case 'save_insight':
				self::save_insight();
				break;

			case 'delete_file':
				self::delete_file();
				break;

			case 'run_setup':
				self::run_setup();
				break;
			case 'skip_setup':
				self::skip_setup();
				break;

			case 'undo_record':
				self::undo_record();
				break;
			case 'bulk_records':
				self::bulk_records();
				break;
			case 'trash_record':
				self::trash_record();
				break;
			case 'restore_record':
				self::restore_record();
				break;
			case 'delete_record':
				self::delete_record();
				break;

			case 'add_home':
				self::add_home();
				break;
			case 'save_listing':
				self::save_listing();
				break;
			case 'update_lead':
				self::update_lead();
				break;
			case 'schedule_visit':
				self::schedule_visit();
				break;
			case 'update_visit':
				self::update_visit();
				break;
			case 'erase_lead':
				self::erase_lead();
				break;
			case 'save_project':
				self::save_project();
				break;
			case 'save_agent':
				self::save_agent();
				break;
			case 'create_form':
			case 'save_form':
			case 'duplicate_form':
			case 'delete_form':
				self::form_action( $action );
				break;
			case 'import_csv':
				self::import_csv();
				break;
			case 'export_csv':
				self::export_csv();
				break;
			case 'sample_csv':
				self::sample_csv();
				break;
			case 'save_settings':
				self::save_settings();
				break;
			default:
				self::tool( $action );
		}
	}

	/* --------------------------------------------------------------------- *
	 * Listings
	 * --------------------------------------------------------------------- */

	/**
	 * The nine-question quick add.
	 *
	 * @return void
	 */
	private static function delete_file(): void {
		$file_id = isset( $_GET['file_id'] ) ? absint( wp_unslash( $_GET['file_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		self::verify( 'estat_delete_file_' . $file_id, 'GET' );

		$back = admin_url( 'admin.php?page=estat-gallery' );

		// Only ever remove a media file, never a property or any other record.
		if ( $file_id <= 0 || 'attachment' !== get_post_type( $file_id ) ) {
			self::redirect( $back, 'error', __( 'That file could not be found.', 'estat-os' ) );
		}

		$title = get_the_title( $file_id );

		if ( ! wp_delete_attachment( $file_id, true ) ) {
			self::redirect( $back, 'error', __( 'The file could not be removed. Please try again.', 'estat-os' ) );
		}

		AuditLog::record( 'media.deleted', 'attachment', $file_id, array( 'title' => $title ) );

		self::redirect( $back, 'deleted' );
	}

	/**
	 * First-run setup: save the few answers, then build the office's pages.
	 *
	 * @return void
	 */
	private static function run_setup(): void {
		self::verify( 'estat_run_setup' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$cities = isset( $_POST['cities'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['cities'] ) ) : '';
		$values = array(
			'office_name'       => isset( $_POST['office_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['office_name'] ) ) : '',
			'phone'             => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['phone'] ) ) : '',
			'email'             => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '',
			'currency_symbol'   => isset( $_POST['currency_symbol'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['currency_symbol'] ) ) : '',
			'default_area_unit' => isset( $_POST['default_area_unit'] ) ? sanitize_key( wp_unslash( (string) $_POST['default_area_unit'] ) ) : 'sqft',
			'setup_done'        => true,
		);
		if ( '' !== $cities ) {
			$values['cities'] = array_values( array_filter( array_map( 'trim', explode( ',', $cities ) ) ) );
		}

		$wanted = isset( $_POST['pages'] ) && is_array( $_POST['pages'] )
			? array_map( 'sanitize_key', array_map( 'strval', wp_unslash( $_POST['pages'] ) ) )
			: array();
		$style  = isset( $_POST['page_style'] ) ? sanitize_key( wp_unslash( (string) $_POST['page_style'] ) ) : 'ready';
		// phpcs:enable

		$office = trim( (string) $values['office_name'] );
		if ( '' === $office ) {
			self::redirect(
				admin_url( 'admin.php?page=' . SetupScreen::SLUG ),
				'error',
				__( 'Please tell us your office name so we can put it on your website.', 'estat-os' )
			);
		}

		Settings::update( $values );

		$built = array();
		if ( array() !== $wanted ) {
			$built = Pages::create( $wanted, 'blank' !== $style );
		}

		AuditLog::record(
			'office.setup',
			'settings',
			0,
			array(
				'pages' => array_keys( $built ),
				'style' => $style,
			)
		);

		self::redirect( admin_url( 'admin.php?page=' . SetupScreen::SLUG ), 'setup_done' );
	}

	/**
	 * The office would rather set things up themselves.
	 *
	 * @return void
	 */
	private static function skip_setup(): void {
		self::verify( 'estat_skip_setup', 'GET' );
		Settings::update( array( 'setup_done' => true ) );
		self::redirect( admin_url( 'admin.php?page=estat-today' ), 'saved' );
	}

	/**
	 * Which capability lets someone remove a given kind of record, and where
	 * the screen for that kind lives.
	 *
	 * Removing something is treated as part of managing it, except for
	 * properties, which keep their own stricter "delete listings" permission.
	 *
	 * @return array<string,array{cap:string,page:string,label:string}>
	 */
	private static function record_kinds(): array {
		return array(
			PostTypes::LISTING  => array(
				'cap'   => 'estat_delete_listings',
				'page'  => 'estat-listings',
				'label' => __( 'property', 'estat-os' ),
			),
			PostTypes::PROJECT  => array(
				'cap'   => 'estat_manage_projects',
				'page'  => 'estat-projects',
				'label' => __( 'society or project', 'estat-os' ),
			),
			PostTypes::AGENT    => array(
				'cap'   => 'estat_manage_team',
				'page'  => 'estat-team',
				'label' => __( 'team member', 'estat-os' ),
			),
			PostTypes::AGENCY   => array(
				'cap'   => 'estat_manage_team',
				'page'  => 'estat-team',
				'label' => __( 'agency', 'estat-os' ),
			),
			PostTypes::INSIGHT  => array(
				'cap'   => 'estat_manage_insights',
				'page'  => 'estat-insights',
				'label' => __( 'article', 'estat-os' ),
			),
			PostTypes::DOCUMENT => array(
				'cap'   => 'estat_manage_listings',
				'page'  => 'estat-gallery',
				'label' => __( 'document', 'estat-os' ),
			),
		);
	}

	/**
	 * Put a record back to how it was before the last save.
	 *
	 * Deleting has always been recoverable; editing was not. Change a price by
	 * a digit and the old one was gone.
	 *
	 * @return void
	 */
	private static function undo_record(): void {
		$target = self::record_target( 'estat_undo_record_' );

		if ( ! Undo::available( $target['id'] ) ) {
			self::redirect( $target['back'], 'error', __( 'There is nothing to undo on this one.', 'estat-os' ) );
		}

		if ( ! Undo::restore( $target['id'] ) ) {
			self::redirect( $target['back'], 'error', __( 'That could not be undone. Nothing was changed.', 'estat-os' ) );
		}

		AuditLog::record(
			'record.undone',
			$target['post']->post_type,
			$target['id'],
			array( 'title' => $target['post']->post_title )
		);

		self::redirect( $target['back'], 'undone' );
	}

	/**
	 * Act on several records at once.
	 *
	 * Doing twenty listings one at a time meant twenty page loads. This does
	 * them in one, but it is deliberately not a shortcut around permission:
	 * every record is looked up, matched to its kind, and checked against that
	 * kind's own capability. A user who may bin listings but not team members
	 * gets exactly the listings binned and is told the rest were skipped.
	 *
	 * Permanent deletion is not offered here. Removing many records forever
	 * from a checkbox column is too easy to do by accident, so bulk can only
	 * move things to the bin, bring them back, publish, or unpublish.
	 *
	 * @return void
	 */
	private static function bulk_records(): void {
		self::verify( 'estat_bulk_records' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$operation = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$raw_ids   = isset( $_POST['record_ids'] ) && is_array( $_POST['record_ids'] ) ? wp_unslash( $_POST['record_ids'] ) : array();
		$back_page = isset( $_POST['back_page'] ) ? sanitize_key( wp_unslash( $_POST['back_page'] ) ) : 'estat-listings';
		// phpcs:enable

		$back = admin_url( 'admin.php?page=' . $back_page );

		if ( ! in_array( $operation, array_keys( self::bulk_operations() ), true ) ) {
			self::redirect( $back, 'error', __( 'Please choose what to do with the ones you ticked.', 'estat-os' ) );
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) );

		if ( ! $ids ) {
			self::redirect( $back, 'error', __( 'Nothing was ticked, so nothing was changed.', 'estat-os' ) );
		}

		// A sane ceiling. Beyond this the request is likely to time out
		// halfway through, which would leave the office unsure what happened.
		if ( count( $ids ) > self::BULK_LIMIT ) {
			self::redirect(
				$back,
				'error',
				sprintf(
					/* translators: %d: the most records that can be changed at once. */
					__( 'That is too many at once. Please do up to %d at a time.', 'estat-os' ),
					self::BULK_LIMIT
				)
			);
		}

		$kinds   = self::record_kinds();
		$done    = 0;
		$skipped = 0;

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( ! $post || ! isset( $kinds[ $post->post_type ] ) ) {
				++$skipped;
				continue;
			}

			if ( ! current_user_can( $kinds[ $post->post_type ]['cap'] ) ) {
				++$skipped;
				continue;
			}

			if ( self::apply_bulk( $operation, $post ) ) {
				++$done;
			} else {
				++$skipped;
			}
		}

		AuditLog::record(
			'records.bulk',
			'bulk',
			0,
			array(
				'operation' => $operation,
				'changed'   => $done,
				'skipped'   => $skipped,
			)
		);

		if ( 0 === $done ) {
			self::redirect( $back, 'error', __( 'Nothing was changed. You may not have permission for the ones you ticked.', 'estat-os' ) );
		}

		$message = sprintf(
			/* translators: 1: number changed, 2: what was done. */
			_n( '%1$d record %2$s.', '%1$d records %2$s.', $done, 'estat-os' ),
			$done,
			self::bulk_operations()[ $operation ]['done']
		);

		if ( $skipped > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number skipped. */
				_n( '%d was skipped because you do not have permission for it.', '%d were skipped because you do not have permission for them.', $skipped, 'estat-os' ),
				$skipped
			);
		}

		self::redirect( $back, 'saved', $message );
	}

	/**
	 * Carry out one bulk operation on one record.
	 *
	 * @param string   $operation Operation key.
	 * @param \WP_Post $post      Record.
	 * @return bool Whether anything changed.
	 */
	private static function apply_bulk( string $operation, \WP_Post $post ): bool {
		$id = (int) $post->ID;

		switch ( $operation ) {
			case 'trash':
				if ( 'trash' === $post->post_status ) {
					return false;
				}

				return (bool) wp_trash_post( $id );

			case 'restore':
				if ( 'trash' !== $post->post_status ) {
					return false;
				}

				if ( ! wp_untrash_post( $id ) ) {
					return false;
				}

				// Core restores to 'draft' in most cases, but be explicit:
				// a record must never come back from the bin already live.
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'draft',
					)
				);

				return true;

			case 'publish':
				if ( 'publish' === $post->post_status || 'trash' === $post->post_status ) {
					return false;
				}

				return (bool) wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'publish',
					)
				);

			case 'draft':
				if ( 'draft' === $post->post_status || 'trash' === $post->post_status ) {
					return false;
				}

				return (bool) wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'draft',
					)
				);
		}

		return false;
	}

	/**
	 * The things bulk can do, in plain words.
	 *
	 * @return array<string,array{label:string,done:string}>
	 */
	public static function bulk_operations(): array {
		return array(
			'publish' => array(
				'label' => __( 'Put on the website', 'estat-os' ),
				'done'  => __( 'put on the website', 'estat-os' ),
			),
			'draft'   => array(
				'label' => __( 'Take off the website', 'estat-os' ),
				'done'  => __( 'taken off the website', 'estat-os' ),
			),
			'trash'   => array(
				'label' => __( 'Move to the bin', 'estat-os' ),
				'done'  => __( 'moved to the bin', 'estat-os' ),
			),
			'restore' => array(
				'label' => __( 'Bring back from the bin', 'estat-os' ),
				'done'  => __( 'brought back as drafts', 'estat-os' ),
			),
		);
	}

	/**
	 * Resolve and authorise the record named in the request.
	 *
	 * Redirects and stops if the record is missing, is not one of ours, or the
	 * signed-in person is not allowed to remove that kind of thing.
	 *
	 * @param string $nonce_prefix Nonce action prefix.
	 * @return array{id:int,post:\WP_Post,kind:array{cap:string,page:string,label:string},back:string}
	 */
	private static function record_target( string $nonce_prefix ): array {
		$id = isset( $_GET['record_id'] ) ? absint( wp_unslash( $_GET['record_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		self::verify( $nonce_prefix . $id, 'GET' );

		$fallback = admin_url( 'admin.php?page=estat-listings' );
		$post     = $id > 0 ? get_post( $id ) : null;
		$kinds    = self::record_kinds();

		if ( ! $post || ! isset( $kinds[ $post->post_type ] ) ) {
			self::redirect( $fallback, 'error', __( 'That record could not be found. It may already have been removed.', 'estat-os' ) );
		}

		$kind = $kinds[ $post->post_type ];
		if ( ! current_user_can( $kind['cap'] ) ) {
			self::redirect( admin_url( 'admin.php?page=' . $kind['page'] ), 'forbidden' );
		}

		return array(
			'id'   => $id,
			'post' => $post,
			'kind' => $kind,
			'back' => admin_url( 'admin.php?page=' . $kind['page'] ),
		);
	}

	/**
	 * Move a record to the bin. Nothing is destroyed; it can be put back.
	 *
	 * @return void
	 */
	private static function trash_record(): void {
		$target = self::record_target( 'estat_trash_record_' );

		if ( 'trash' === $target['post']->post_status ) {
			self::redirect( $target['back'], 'trashed' );
		}
		if ( ! wp_trash_post( $target['id'] ) ) {
			self::redirect( $target['back'], 'error', __( 'That could not be moved to the bin. Please try again.', 'estat-os' ) );
		}

		AuditLog::record(
			'record.trashed',
			$target['post']->post_type,
			$target['id'],
			array( 'title' => $target['post']->post_title )
		);

		self::redirect( $target['back'], 'trashed' );
	}

	/**
	 * Put a record back from the bin, exactly as it was.
	 *
	 * @return void
	 */
	private static function restore_record(): void {
		$target = self::record_target( 'estat_restore_record_' );

		if ( 'trash' !== $target['post']->post_status ) {
			self::redirect( $target['back'], 'restored' );
		}
		if ( ! wp_untrash_post( $target['id'] ) ) {
			self::redirect( $target['back'], 'error', __( 'That could not be put back. Please try again.', 'estat-os' ) );
		}

		// WordPress may restore to "draft"; never let a restore publish something
		// to the website on its own.
		$restored = get_post( $target['id'] );
		if ( $restored && 'publish' === $restored->post_status ) {
			wp_update_post(
				array(
					'ID'          => $target['id'],
					'post_status' => 'draft',
				)
			);
		}

		AuditLog::record(
			'record.restored',
			$target['post']->post_type,
			$target['id'],
			array( 'title' => $target['post']->post_title )
		);

		self::redirect( $target['back'], 'restored' );
	}

	/**
	 * Delete a record for good. Only ever from the bin, and only after the
	 * office has typed DELETE to confirm.
	 *
	 * @return void
	 */
	private static function delete_record(): void {
		$target = self::record_target( 'estat_delete_record_' );

		$confirm = isset( $_GET['confirm'] ) ? sanitize_text_field( wp_unslash( $_GET['confirm'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'DELETE' !== $confirm ) {
			self::redirect( $target['back'], 'error', __( 'Nothing was deleted. Type DELETE in the box to confirm you really mean it.', 'estat-os' ) );
		}

		// Force the office to bin it first, so a single stray click can never
		// destroy live business data.
		if ( 'trash' !== $target['post']->post_status ) {
			self::redirect( $target['back'], 'error', __( 'Move this to the bin first. Only things in the bin can be deleted for good.', 'estat-os' ) );
		}

		$title = $target['post']->post_title;
		if ( ! wp_delete_post( $target['id'], true ) ) {
			self::redirect( $target['back'], 'error', __( 'That could not be deleted. Please try again.', 'estat-os' ) );
		}

		AuditLog::record(
			'record.deleted',
			$target['post']->post_type,
			$target['id'],
			array( 'title' => $title )
		);

		self::redirect( $target['back'], 'deleted' );
	}

	/**
	 * Save a blog, article or insight.
	 *
	 * @return void
	 */
	private static function save_insight(): void {
		$insight_id = self::int_post( 'insight_id' );
		self::verify( 'estat_save_insight_' . $insight_id );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$mode  = isset( $_POST['estat_save_mode'] ) ? sanitize_key( wp_unslash( $_POST['estat_save_mode'] ) ) : 'draft';
		$kind  = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'blog';
		$input = array(
			'id'       => $insight_id,
			'title'    => isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '',
			'excerpt'  => isset( $_POST['excerpt'] ) ? wp_unslash( $_POST['excerpt'] ) : '',
			'content'  => isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '',
			'topics'   => isset( $_POST['topics'] ) ? wp_unslash( $_POST['topics'] ) : '',
			'cover_id' => isset( $_POST['cover_id'] ) ? wp_unslash( $_POST['cover_id'] ) : 0,
			'kind'     => $kind,
			'status'   => 'publish' === $mode ? 'publish' : 'draft',
		);
		// phpcs:enable

		$result = Insights::save( $input );

		$back = admin_url( 'admin.php?page=estat-insights&kind=' . rawurlencode( $kind ) );

		if ( is_wp_error( $result ) ) {
			self::redirect( $back . '&edit=' . $insight_id, 'error', self::first_message( $result ) );
		}

		self::redirect( $back, 'publish' === $input['status'] ? 'published' : 'draft' );
	}

	/**
	 * The quick "Add a Home" form.
	 *
	 * @return void
	 */
	private static function add_home(): void {
		self::verify( 'estat_add_home' );

		$mode   = isset( $_POST['estat_save_mode'] ) ? sanitize_key( wp_unslash( $_POST['estat_save_mode'] ) ) : 'draft';
		$status = ( 'publish' === $mode && current_user_can( 'estat_publish_listings' ) ) ? 'publish' : 'draft';

		$input = self::post_fields(
			array( 'title', 'offer', 'property_type', 'locality', 'price', 'area', 'area_unit', 'bedrooms', 'cover_id' )
		);
		$input['status'] = $status;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify() ran above.
		$input['highlights'] = isset( $_POST['highlights'] ) ? (array) wp_unslash( $_POST['highlights'] ) : array();

		if ( ! empty( $_POST['price_on_request'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$input['price_type'] = 'on_request';
			$input['price']      = 0;
		} else {
			$input['price_type'] = 'fixed';
		}

		$result = Listings::save( $input );
		if ( is_wp_error( $result ) ) {
			self::redirect( admin_url( 'admin.php?page=estat-add-home' ), 'error', self::first_message( $result ) );
		}

		self::redirect(
			admin_url( 'admin.php?page=estat-listings&edit=' . (int) $result ),
			'publish' === $status ? 'published' : 'draft'
		);
	}

	/**
	 * The full listing editor.
	 *
	 * @return void
	 */
	private static function save_listing(): void {
		$listing_id = self::int_post( 'listing_id' );
		self::verify( 'estat_save_listing_' . $listing_id );
		if ( $listing_id <= 0 ) {
			self::redirect( admin_url( 'admin.php?page=estat-listings' ), 'error', __( 'That property could not be found.', 'estat-os' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$raw   = wp_unslash( $_POST );
		$input = array();
		foreach ( $raw as $key => $value ) {
			if ( in_array( $key, array( 'estat_action', 'estat_nonce', '_wp_http_referer', 'listing_id', 'estat_save_mode' ), true ) ) {
				continue;
			}
			$input[ (string) $key ] = $value;
		}
		// phpcs:enable

		$mode = isset( $_POST['estat_save_mode'] ) ? sanitize_key( wp_unslash( $_POST['estat_save_mode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( 'publish' === $mode ) {
			$input['status'] = 'publish';
		} elseif ( 'draft' === $mode ) {
			$input['status'] = 'draft';
		} else {
			$input['status'] = (string) get_post_status( $listing_id );
		}

		$result = Listings::save( $input, $listing_id );
		if ( is_wp_error( $result ) ) {
			self::redirect( admin_url( 'admin.php?page=estat-listings&edit=' . $listing_id ), 'error', self::first_message( $result ) );
		}

		$notice = 'publish' === $input['status'] ? 'published' : 'saved';
		self::redirect( admin_url( 'admin.php?page=estat-listings&edit=' . $listing_id ), $notice );
	}

	/* --------------------------------------------------------------------- *
	 * Enquiries and visits
	 * --------------------------------------------------------------------- */

	/**
	 * Update one enquiry.
	 *
	 * @return void
	 */
	private static function update_lead(): void {
		$lead_id = self::int_post( 'lead_id' );
		self::verify( 'estat_update_lead_' . $lead_id );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$input = array(
			'status'      => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : null,
			'followup_at' => isset( $_POST['followup_at'] ) ? Sanitize::text( wp_unslash( $_POST['followup_at'] ) ) : null,
		);
		if ( isset( $_POST['agent_id'] ) && current_user_can( 'estat_assign_leads' ) ) {
			$input['agent_id'] = absint( wp_unslash( $_POST['agent_id'] ) );
		}
		$note = isset( $_POST['note'] ) ? Sanitize::textarea( wp_unslash( $_POST['note'] ) ) : '';
		// phpcs:enable

		$input  = array_filter( $input, static fn( $value ) => null !== $value );
		$result = Leads::update( $lead_id, $input );
		if ( is_wp_error( $result ) ) {
			self::redirect( admin_url( 'admin.php?page=estat-enquiries' ), 'error', self::first_message( $result ) );
		}
		if ( '' !== $note ) {
			Leads::append_note( $lead_id, $note );
		}

		self::redirect( admin_url( 'admin.php?page=estat-enquiries' ), isset( $input['agent_id'] ) ? 'assigned' : 'saved' );
	}

	/**
	 * Book a site visit from an enquiry.
	 *
	 * @return void
	 */
	private static function schedule_visit(): void {
		$lead_id = self::int_post( 'lead_id' );
		self::verify( 'estat_schedule_visit_' . $lead_id );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$result = Visits::schedule(
			array(
				'lead_id'      => $lead_id,
				'listing_id'   => isset( $_POST['listing_id'] ) ? absint( wp_unslash( $_POST['listing_id'] ) ) : 0,
				'agent_id'     => isset( $_POST['agent_id'] ) ? absint( wp_unslash( $_POST['agent_id'] ) ) : 0,
				'scheduled_at' => isset( $_POST['scheduled_at'] ) ? Sanitize::text( wp_unslash( $_POST['scheduled_at'] ) ) : '',
			)
		);
		// phpcs:enable

		if ( is_wp_error( $result ) ) {
			self::redirect( admin_url( 'admin.php?page=estat-enquiries' ), 'error', self::first_message( $result ) );
		}
		self::redirect( admin_url( 'admin.php?page=estat-enquiries' ), 'visit_scheduled' );
	}

	/**
	 * Record how a visit went.
	 *
	 * @return void
	 */
	private static function update_visit(): void {
		$visit_id = self::int_post( 'visit_id' );
		self::verify( 'estat_update_visit_' . $visit_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$outcome = isset( $_POST['outcome'] ) ? sanitize_key( wp_unslash( $_POST['outcome'] ) ) : '';
		$result  = Visits::update( $visit_id, array( 'outcome' => $outcome ) );
		if ( is_wp_error( $result ) ) {
			self::redirect( admin_url( 'admin.php?page=estat-visits' ), 'error', self::first_message( $result ) );
		}
		self::redirect( admin_url( 'admin.php?page=estat-visits' ), 'saved' );
	}

	/**
	 * Permanently remove someone's personal details.
	 *
	 * @return void
	 */
	private static function erase_lead(): void {
		$lead_id = self::int_post( 'lead_id' );
		self::verify( 'estat_erase_lead_' . $lead_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$confirm = isset( $_POST['confirm'] ) ? trim( (string) wp_unslash( $_POST['confirm'] ) ) : '';
		if ( 'DELETE' !== $confirm ) {
			self::redirect( admin_url( 'admin.php?page=estat-enquiries' ), 'error', __( 'Nothing was erased. Type DELETE in capital letters to confirm.', 'estat-os' ) );
		}

		Leads::erase( $lead_id );
		self::redirect( admin_url( 'admin.php?page=estat-enquiries' ), 'erased' );
	}

	/* --------------------------------------------------------------------- *
	 * Projects, team
	 * --------------------------------------------------------------------- */

	/**
	 * Save a society or project.
	 *
	 * @return void
	 */
	private static function save_project(): void {
		self::verify( 'estat_save_project' );
		$project_id = self::int_post( 'project_id' );

		$input  = self::post_fields(
			array(
				'title', 'description', 'locality', 'developer', 'project_status', 'possession_date',
				'price_min', 'price_max', 'total_units', 'available_units', 'unit_types',
				'highlights', 'regulatory_id', 'external_id', 'cover_id',
			)
		);
		$result = Projects::save( $input, $project_id );
		if ( is_wp_error( $result ) ) {
			self::redirect( admin_url( 'admin.php?page=estat-projects' ), 'error', self::first_message( $result ) );
		}
		self::redirect( admin_url( 'admin.php?page=estat-projects&edit=' . (int) $result ), 'saved' );
	}

	/**
	 * Save a team member.
	 *
	 * @return void
	 */
	private static function save_agent(): void {
		self::verify( 'estat_save_agent' );
		$agent_id = self::int_post( 'agent_id' );

		$input  = self::post_fields( array( 'title', 'role', 'phone', 'whatsapp', 'email', 'bio', 'photo_id', 'external_id', 'user_id' ) );
		$result = Agents::save( $input, $agent_id );
		if ( is_wp_error( $result ) ) {
			self::redirect( admin_url( 'admin.php?page=estat-team' ), 'error', self::first_message( $result ) );
		}
		self::redirect( admin_url( 'admin.php?page=estat-team&edit=' . (int) $result ), 'saved' );
	}

	/* --------------------------------------------------------------------- *
	 * Forms
	 * --------------------------------------------------------------------- */

	/**
	 * Create, save, duplicate or delete a form.
	 *
	 * @param string $action Action key.
	 * @return void
	 */
	private static function form_action( string $action ): void {
		$forms_url = admin_url( 'admin.php?page=estat-forms' );
		$form_id   = self::int_post( 'form_id' );

		if ( 'create_form' === $action ) {
			self::verify( 'estat_create_form' );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$name = isset( $_POST['name'] ) ? Sanitize::text( wp_unslash( $_POST['name'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$template_key = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '';
			$template     = '' !== $template_key ? Templates::get( $template_key ) : null;

			// An unknown template must not silently produce an empty form.
			if ( '' !== $template_key && ! $template ) {
				self::redirect( $forms_url, 'error', __( 'That ready-made form is not available. Please pick another.', 'estat-os' ) );
			}

			if ( $template ) {
				// The office may not have typed a name; the template has one.
				if ( '' === $name ) {
					$name = (string) $template['label'];
				}
				$definition = $template['definition'];
				$settings   = array_merge( Forms::default_settings(), (array) $template['settings'] );
			} else {
				$definition = Forms::starter_definition();
				$settings   = Forms::default_settings();
			}

			$result = Forms::save(
				array(
					'name'       => $name,
					'definition' => $definition,
					'settings'   => $settings,
				)
			);
			if ( is_wp_error( $result ) ) {
				self::redirect( $forms_url, 'error', self::first_message( $result ) );
			}
			self::redirect( admin_url( 'admin.php?page=estat-forms&edit=' . (int) $result ), 'saved' );
		}

		if ( 'duplicate_form' === $action ) {
			self::verify( 'estat_duplicate_form_' . $form_id );
			$result = Forms::duplicate( $form_id );
			if ( is_wp_error( $result ) ) {
				self::redirect( $forms_url, 'error', self::first_message( $result ) );
			}
			self::redirect( admin_url( 'admin.php?page=estat-forms&edit=' . (int) $result ), 'saved' );
		}

		if ( 'delete_form' === $action ) {
			self::verify( 'estat_delete_form_' . $form_id );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$confirm = isset( $_POST['confirm'] ) ? trim( (string) wp_unslash( $_POST['confirm'] ) ) : '';
			if ( 'DELETE' !== $confirm ) {
				self::redirect( admin_url( 'admin.php?page=estat-forms&edit=' . $form_id ), 'error', __( 'Nothing was deleted. Type DELETE in capital letters to confirm.', 'estat-os' ) );
			}
			Forms::delete( $form_id );
			self::redirect( $forms_url, 'deleted' );
		}

		// save_form.
		self::verify( 'estat_save_form_' . $form_id );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$definition = isset( $_POST['definition'] ) ? (string) wp_unslash( $_POST['definition'] ) : '[]';
		$decoded    = json_decode( $definition, true );
		$settings   = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : array();
		$name       = isset( $_POST['name'] ) ? Sanitize::text( wp_unslash( $_POST['name'] ) ) : '';
		// phpcs:enable

		if ( ! is_array( $decoded ) ) {
			self::redirect( admin_url( 'admin.php?page=estat-forms&edit=' . $form_id ), 'error', __( 'The form layout could not be read, so nothing was changed. Please try again.', 'estat-os' ) );
		}

		// Unticked boxes never reach the server, so make the intent explicit.
		foreach ( array( 'create_lead', 'store_submission', 'notify', 'require_consent' ) as $flag ) {
			$settings[ $flag ] = ! empty( $settings[ $flag ] );
		}

		$result = Forms::save(
			array(
				'name'       => $name,
				'definition' => $decoded,
				'settings'   => $settings,
			),
			$form_id
		);
		if ( is_wp_error( $result ) ) {
			self::redirect( admin_url( 'admin.php?page=estat-forms&edit=' . $form_id ), 'error', self::first_message( $result ) );
		}
		self::redirect( admin_url( 'admin.php?page=estat-forms&edit=' . (int) $result ), 'saved' );
	}

	/* --------------------------------------------------------------------- *
	 * Spreadsheets
	 * --------------------------------------------------------------------- */

	/**
	 * Upload and start an import.
	 *
	 * @return void
	 */
	private static function import_csv(): void {
		self::verify( 'estat_import_csv' );
		$url = admin_url( 'admin.php?page=estat-spreadsheet' );

		if ( empty( $_FILES['estat_csv'] ) || ! is_array( $_FILES['estat_csv'] ) ) {
			self::redirect( $url, 'error', __( 'Please choose a CSV file to upload.', 'estat-os' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$stored = Spreadsheet::store_upload( (array) $_FILES['estat_csv'] );
		if ( is_wp_error( $stored ) ) {
			self::redirect( $url, 'error', self::first_message( $stored ) );
		}

		$preview = Spreadsheet::preview( (string) $stored, 1 );
		if ( is_wp_error( $preview ) ) {
			self::redirect( $url, 'error', self::first_message( $preview ) );
		}

		$mapping = Spreadsheet::guess_mapping( (array) ( $preview['headers'] ?? array() ), 'listings' );
		$job_id  = Spreadsheet::create_job( (string) $stored, 'listings', $mapping );
		if ( is_wp_error( $job_id ) ) {
			self::redirect( $url, 'error', self::first_message( $job_id ) );
		}

		// Run the first batch straight away so the office sees progress immediately;
		// the daily and hourly jobs finish anything left over.
		$progress = Spreadsheet::process_batch( (int) $job_id );
		$detail   = is_wp_error( $progress )
			? self::first_message( $progress )
			: sprintf(
				/* translators: 1: rows added, 2: rows updated */
				__( '%1$d added, %2$d updated so far.', 'estat-os' ),
				(int) ( $progress['created'] ?? 0 ),
				(int) ( $progress['updated'] ?? 0 )
			);

		self::redirect( $url, is_wp_error( $progress ) ? 'error' : 'imported', $detail );
	}

	/**
	 * Stream an export.
	 *
	 * @return void
	 */
	private static function export_csv(): void {
		self::verify( 'estat_export_csv', 'GET' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$what = isset( $_GET['what'] ) ? sanitize_key( wp_unslash( $_GET['what'] ) ) : 'listings';
		$csv  = Spreadsheet::export( $what );
		AuditLog::record( 'export.created', 'export', 0, array( 'kind' => $what ) );
		self::send_csv( 'estat-' . $what . '-' . gmdate( 'Y-m-d' ) . '.csv', $csv );
	}

	/**
	 * Send a header-only template file.
	 *
	 * @return void
	 */
	private static function sample_csv(): void {
		self::verify( 'estat_sample_csv', 'GET' );
		$columns = array_keys( Spreadsheet::columns( 'listings' ) );
		$csv     = implode( ',', $columns ) . "\n";
		$csv    .= '"REF-001","3 BHK apartment near the metro","sale","apartment","Green Park","8500000","1450","sqft","3","2"' . "\n";
		self::send_csv( 'estat-sample-listings.csv', $csv );
	}

	/* --------------------------------------------------------------------- *
	 * Settings and tools
	 * --------------------------------------------------------------------- */

	/**
	 * Save one tab of settings without disturbing the others.
	 *
	 * @return void
	 */
	private static function save_settings(): void {
		self::verify( 'estat_save_settings' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$tab   = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'office';
		$input = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : array();
		// phpcs:enable

		// Checkboxes that belong to this tab but were left unticked.
		foreach ( self::tab_flags( $tab ) as $flag ) {
			$input[ $flag ] = ! empty( $input[ $flag ] );
		}
		if ( 'advanced' === $tab && ! isset( $input['webhook_events'] ) ) {
			$input['webhook_events'] = array();
		}

		Settings::update( $input );
		self::redirect( admin_url( 'admin.php?page=estat-settings&tab=' . $tab ), 'saved' );
	}

	/**
	 * Checkbox keys per settings tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string[]
	 */
	private static function tab_flags( string $tab ): array {
		switch ( $tab ) {
			case 'office':
				return array( 'show_regulatory' );
			case 'website':
				return array( 'enable_favorites', 'enable_compare', 'enable_schema' );
			case 'language':
				return array( 'allow_visitor_lang' );
			case 'emails':
				return array( 'notify_new_lead', 'notify_new_visit', 'notify_followup', 'notify_expiry', 'notify_import', 'notify_webhook_fail' );
			case 'advanced':
				return array( 'webhook_enabled' );
			case 'tools':
				return array( 'delete_data_on_uninstall' );
			default:
				return array();
		}
	}

	/**
	 * Maintenance and practice-mode buttons.
	 *
	 * @param string $action Action key.
	 * @return void
	 */
	private static function tool( string $action ): void {
		self::verify( 'estat_tool_' . $action );
		$url = admin_url( 'admin.php?page=estat-settings&tab=tools' );

		switch ( $action ) {
			case 'rebuild_index':
				$done  = 0;
				$total = 0;
				// Work in bounded batches so shared hosting never times out.
				for ( $i = 0; $i < 25; $i++ ) {
					$count = SearchIndex::rebuild_batch( 200, $done );
					$done += 200;
					$total += $count;
					if ( $count < 200 ) {
						break;
					}
				}
				AuditLog::record( 'maintenance.reindex', 'index', 0, array( 'listings' => $total ) );
				self::redirect(
					$url,
					'index_rebuilt',
					/* translators: %d: number of properties */
					sprintf( __( '%d properties are ready for searching.', 'estat-os' ), $total )
				);
				break;

			case 'repair_tables':
				Schema::install();
				AuditLog::record( 'maintenance.repair', 'schema', 0, array() );
				self::redirect( $url, 'saved', Schema::healthy() ? __( 'The office database looks healthy.', 'estat-os' ) : __( 'Some tables are still missing. Ask your host to check the database user has permission to create tables.', 'estat-os' ) );
				break;

			case 'clear_cache':
				wp_cache_flush();
				delete_transient( 'estat_stats' );
				delete_transient( 'estat_search_counts' );
				self::redirect( $url, 'saved', __( 'Temporary data cleared.', 'estat-os' ) );
				break;

			case 'run_housekeeping':
				Scheduler::run_daily();
				self::redirect( $url, 'saved', __( 'The daily jobs have been run.', 'estat-os' ) );
				break;

			case 'seed_practice':
				$created = PracticeMode::seed();
				Settings::update( array( 'practice_mode' => true ) );
				self::redirect(
					$url,
					'practice_seeded',
					/* translators: %d: number of sample properties */
					sprintf( __( '%d sample properties were added. They are all clearly marked.', 'estat-os' ), (int) ( $created['listings'] ?? 0 ) )
				);
				break;

			case 'clear_practice':
				$removed = PracticeMode::clear();
				Settings::update( array( 'practice_mode' => false ) );
				self::redirect(
					$url,
					'practice_cleared',
					/* translators: %d: number of sample records removed */
					sprintf( __( '%d sample records were removed. Your real data was not touched.', 'estat-os' ), (int) array_sum( array_map( 'intval', $removed ) ) )
				);
				break;
		}
	}

	/* --------------------------------------------------------------------- *
	 * Helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Check the nonce for an action, or stop.
	 *
	 * @param string $nonce_action Nonce action name.
	 * @param string $method       POST or GET.
	 * @return void
	 */
	private static function verify( string $nonce_action, string $method = 'POST' ): void {
		$source = 'GET' === $method ? $_GET : $_POST; // phpcs:ignore WordPress.Security.NonceVerification
		$nonce  = isset( $source['estat_nonce'] ) ? sanitize_text_field( wp_unslash( $source['estat_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_die(
				esc_html__( 'That link or form has expired. Please go back, refresh the page and try once more. Nothing was changed.', 'estat-os' ),
				esc_html__( 'Please try again', 'estat-os' ),
				array( 'response' => 403, 'back_link' => true )
			);
		}
	}

	/**
	 * Collect a known set of POST fields without sanitising them here;
	 * the data layer sanitises every value against its own schema.
	 *
	 * @param string[] $keys Field names.
	 * @return array<string,mixed>
	 */
	private static function post_fields( array $keys ): array {
		$out = array();
		foreach ( $keys as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
			if ( isset( $_POST[ $key ] ) ) {
				$out[ $key ] = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			}
		}
		return $out;
	}

	/**
	 * One positive integer from POST.
	 *
	 * @param string $key Field name.
	 * @return int
	 */
	private static function int_post( string $key ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset( $_POST[ $key ] ) ? absint( wp_unslash( $_POST[ $key ] ) ) : 0;
	}

	/**
	 * The first friendly message from an error object.
	 *
	 * @param WP_Error $error Error.
	 * @return string
	 */
	private static function first_message( WP_Error $error ): string {
		$message = $error->get_error_message();
		return '' !== $message ? $message : __( 'Something went wrong and nothing was saved.', 'estat-os' );
	}

	/**
	 * Where to send someone back to when we cannot tell.
	 *
	 * @return string
	 */
	private static function back(): string {
		$referer = wp_get_referer();
		return $referer ? $referer : admin_url( 'admin.php?page=estat-today' );
	}

	/**
	 * Redirect with a notice and stop.
	 *
	 * @param string $url    Destination.
	 * @param string $notice Notice key.
	 * @param string $detail Optional detail line.
	 * @return void
	 */
	private static function redirect( string $url, string $notice, string $detail = '' ): void {
		$args = array( 'estat_notice' => $notice );
		if ( '' !== $detail ) {
			$args['estat_detail'] = rawurlencode( $detail );
		}
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	/**
	 * Send a CSV download and stop.
	 *
	 * @param string $filename File name.
	 * @param string $csv      File body.
	 * @return void
	 */
	private static function send_csv( string $filename, string $csv ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $filename ) );
		header( 'Content-Length: ' . strlen( $csv ) );
		// Excel opens UTF-8 correctly only with a byte order mark.
		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
}
