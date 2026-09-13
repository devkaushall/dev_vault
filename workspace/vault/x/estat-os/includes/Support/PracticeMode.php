<?php
/**
 * Practice mode: clearly marked demo records with one-click cleanup.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Support;

use EstatOS\Audit\AuditLog;
use EstatOS\Data\Listings;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Projects;
use EstatOS\Data\Agents;
use EstatOS\Install\Schema;
use EstatOS\Search\SearchIndex;
use EstatOS\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Practice records carry the _estat_practice flag and a visible PRACTICE badge,
 * so they can never be mistaken for real business data.
 */
final class PracticeMode {

	/**
	 * Marks a demo photograph so a second seed reuses the copy already in the
	 * media library instead of adding the same file again.
	 */
	public const PHOTO_KEY = '_estat_practice_photo';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'the_title', array( __CLASS__, 'badge_title' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/**
	 * Whether practice mode is on.
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return (bool) Settings::get( 'practice_mode', false );
	}

	/**
	 * Add a PRACTICE badge to practice record titles.
	 *
	 * @param string $title   Title.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public static function badge_title( $title, $post_id = 0 ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! in_array( get_post_type( $post_id ), PostTypes::all(), true ) ) {
			return $title;
		}
		if ( ! get_post_meta( $post_id, '_estat_practice', true ) ) {
			return $title;
		}
		return '[' . __( 'PRACTICE', 'estat-os' ) . '] ' . $title;
	}

	/**
	 * Persistent reminder while practice mode is on.
	 *
	 * @return void
	 */
	public static function notice(): void {
		if ( ! self::active() || ! current_user_can( 'estat_manage_listings' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false === strpos( (string) $screen->id, 'estat' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Practice mode is on.', 'estat-os' ) . '</strong> ';
		esc_html_e( 'Anything you add now is marked PRACTICE and can be removed in one click from Office Settings.', 'estat-os' );
		echo '</p></div>';
	}

	/**
	 * Create a small set of neutral practice records.
	 *
	 * @return array<string,int> Counts created.
	 */
	public static function seed(): array {
		$created = array( 'listings' => 0, 'projects' => 0, 'agents' => 0 );

		$agent_id = Agents::save(
			array(
				'title'  => __( 'Practice Agent', 'estat-os' ),
				'role'   => __( 'Sales', 'estat-os' ),
				'phone'  => '+10000000000',
				'email'  => 'agent@example.com',
			)
		);
		if ( ! is_wp_error( $agent_id ) ) {
			update_post_meta( (int) $agent_id, '_estat_practice', 1 );
			++$created['agents'];
		}

		$project_id = Projects::save(
			array(
				'title'          => __( 'Practice Residency', 'estat-os' ),
				'description'    => __( 'A sample society used for practice. Delete it whenever you like.', 'estat-os' ),
				'project_status' => 'ready',
				'total_units'    => 120,
				'available_units'=> 14,
			)
		);
		if ( ! is_wp_error( $project_id ) ) {
			update_post_meta( (int) $project_id, '_estat_practice', 1 );
			++$created['projects'];
		}

		$samples = array(
			array( 'title' => __( 'Practice: 2 BHK apartment with a park view', 'estat-os' ), 'offer' => 'sale', 'type' => 'apartment', 'price' => 6500000, 'area' => 950, 'beds' => 2, 'photo' => 'practice-apartment.jpg' ),
			array( 'title' => __( 'Practice: 3 BHK builder floor near the market', 'estat-os' ), 'offer' => 'rent', 'type' => 'builder_floor', 'rent' => 32000, 'area' => 1400, 'beds' => 3, 'photo' => 'practice-builderfloor.jpg' ),
			array( 'title' => __( 'Practice: corner shop on the main road', 'estat-os' ), 'offer' => 'lease', 'type' => 'commercial_shop', 'rent' => 55000, 'area' => 400, 'beds' => 0, 'photo' => 'practice-shop.jpg' ),
		);
		foreach ( $samples as $sample ) {
			$listing_id = Listings::save(
				array(
					'title'         => $sample['title'],
					'status'        => 'draft',
					'description'   => __( 'This is practice information so you can try the system safely. It is not a real property.', 'estat-os' ),
					'offer'         => $sample['offer'],
					'property_type' => $sample['type'],
					'price'         => $sample['price'] ?? 0,
					'rent'          => $sample['rent'] ?? 0,
					'area'          => $sample['area'],
					'area_unit'     => 'sqft',
					'bedrooms'      => $sample['beds'],
					'locality'      => array( __( 'Practice Locality', 'estat-os' ) ),
					'agent_id'      => is_wp_error( $agent_id ) ? 0 : (int) $agent_id,
					'project_id'    => is_wp_error( $project_id ) ? 0 : (int) $project_id,
				)
			);
			if ( ! is_wp_error( $listing_id ) ) {
				update_post_meta( (int) $listing_id, '_estat_practice', 1 );

				/*
				 * A property with no photograph looks broken rather than
				 * empty, and practice mode exists so somebody can see what a
				 * finished office looks like. These three photographs ship
				 * with the plugin for exactly that.
				 */
				if ( isset( $sample['photo'] ) ) {
					self::attach_photo( (int) $listing_id, (string) $sample['photo'] );
				}

				SearchIndex::index_listing( (int) $listing_id );
				++$created['listings'];
			}
		}

		AuditLog::record( 'practice.seeded', 'practice', 0, $created );
		return $created;
	}

	/**
	 * Remove every practice record. Real data is never touched.
	 *
	 * @return array<string,int> Counts removed.
	 */
	public static function clear(): array {
		global $wpdb;
		$removed = array( 'posts' => 0, 'photos' => 0, 'leads' => 0, 'visits' => 0, 'submissions' => 0 );

		$ids = get_posts(
			array(
				'post_type'      => PostTypes::all(),
				'post_status'    => 'any',
				'posts_per_page' => 1000,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_estat_practice', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
			++$removed['posts'];
		}

		/*
		 * The demo photographs are attachments, and attachments are not in
		 * PostTypes::all(), so the loop above never touched them. Clearing
		 * practice mode would have left three orphaned images in the media
		 * library and three files in uploads, every single time.
		 */
		$photos = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => self::PHOTO_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);

		$removed['photos'] = 0;

		foreach ( $photos as $photo_id ) {
			// true deletes the file from uploads as well as the record.
			if ( wp_delete_attachment( (int) $photo_id, true ) ) {
				++$removed['photos'];
			}
		}

		if ( Schema::healthy() ) {
			foreach ( array( 'estat_leads' => 'leads', 'estat_visits' => 'visits', 'estat_submissions' => 'submissions' ) as $table => $key ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$removed[ $key ] = (int) $wpdb->delete( Schema::table( $table ), array( 'practice' => 1 ), array( '%d' ) );
			}
		}

		AuditLog::record( 'practice.cleared', 'practice', 0, $removed );
		return $removed;
	}

	/**
	 * Put one of the shipped demo photographs onto a practice listing.
	 *
	 * The file is copied into the uploads folder rather than referenced where
	 * it sits, because the plugin folder is replaced on every update and a
	 * cover image pointing into it would vanish. A copy in uploads behaves
	 * like any other photograph the office added themselves: it can be
	 * replaced, deleted, or kept.
	 *
	 * It fails quietly. A practice listing without a photograph is still a
	 * perfectly good practice listing, and this must never be the reason
	 * seeding a demo office breaks.
	 *
	 * @param int    $listing_id Listing to attach it to.
	 * @param string $file_name  File name inside assets/demo/.
	 * @return int Attachment ID, or 0.
	 */
	private static function attach_photo( int $listing_id, string $file_name ): int {
		// Only a plain file name from our own folder, never a path.
		$file_name = basename( $file_name );
		$source    = ESTAT_DIR . 'assets/demo/' . $file_name;

		if ( ! is_readable( $source ) ) {
			return 0;
		}

		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return 0;
		}

		/*
		 * 'path' is the month folder and 'basedir' the root of uploads. Core
		 * fills both, but a site with an unusual uploads configuration - or a
		 * filter on upload_dir - can leave 'path' empty, and copying to an
		 * empty path would try to write to the filesystem root.
		 */
		$folder = '';

		if ( ! empty( $uploads['path'] ) ) {
			$folder = (string) $uploads['path'];
		} elseif ( ! empty( $uploads['basedir'] ) ) {
			$folder = (string) $uploads['basedir'];
		}

		if ( '' === $folder || ! wp_mkdir_p( $folder ) || ! is_writable( $folder ) ) {
			return 0;
		}

		// Reuse the copy if this has been seeded before, so running practice
		// mode twice does not fill the media library with duplicates.
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				// An attachment's status is 'inherit', and get_posts()
				// defaults to 'publish'. Without this the lookup never
				// matched and every seed copied the files again.
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::PHOTO_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $file_name, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'  => true,
			)
		);

		if ( $existing ) {
			set_post_thumbnail( $listing_id, (int) $existing[0] );

			return (int) $existing[0];
		}

		$target = trailingslashit( $folder ) . 'estat-' . $file_name;

		if ( ! copy( $source, $target ) ) {
			return 0;
		}

		$type = wp_check_filetype( $target, null );

		if ( empty( $type['type'] ) || 0 !== strpos( (string) $type['type'], 'image/' ) ) {
			wp_delete_file( $target );

			return 0;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $type['type'],
				'post_title'     => __( 'Practice property photograph', 'estat-os' ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$target,
			$listing_id
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			wp_delete_file( $target );

			return 0;
		}

		$attachment_id = (int) $attachment_id;

		// Mark it so clearing practice mode can find it again, and so a
		// second seed reuses this copy.
		update_post_meta( $attachment_id, self::PHOTO_KEY, $file_name );
		update_post_meta( $attachment_id, '_estat_practice', 1 );

		/*
		 * Generate the resized versions, if the environment can.
		 *
		 * wp_generate_attachment_metadata() lives in an admin-only file, so it
		 * has to be loaded first - but require_once on a missing file is a
		 * fatal error, not a warning. That would turn "the thumbnails did not
		 * generate" into "seeding a demo office killed the request", which is
		 * a far worse outcome for something entirely optional.
		 */
		$image_helpers = ABSPATH . 'wp-admin/includes/image.php';

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) && is_readable( $image_helpers ) ) {
			require_once $image_helpers;
		}

		if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
			$meta = wp_generate_attachment_metadata( $attachment_id, $target );

			if ( is_array( $meta ) && function_exists( 'wp_update_attachment_metadata' ) ) {
				wp_update_attachment_metadata( $attachment_id, $meta );
			}
		}

		set_post_thumbnail( $listing_id, $attachment_id );

		return $attachment_id;
	}

}
