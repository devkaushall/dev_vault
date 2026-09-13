<?php
/**
 * Privacy: WordPress exporter/eraser integration and retention.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Privacy;

use EstatOS\Install\Schema;
use EstatOS\Leads\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Lead information is personal data. The office can erase it at any time and
 * WordPress's own privacy tools are wired up so requests are honoured.
 */
final class Privacy {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_policy_text' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array<string,mixed> $exporters Exporters.
	 * @return array<string,mixed>
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['estat-os'] = array(
			'exporter_friendly_name' => __( 'Real estate enquiries', 'estat-os' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array<string,mixed> $erasers Erasers.
	 * @return array<string,mixed>
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['estat-os'] = array(
			'eraser_friendly_name' => __( 'Real estate enquiries', 'estat-os' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export enquiries for an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array{data:array<int,array<string,mixed>>,done:bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		global $wpdb;
		$data = array();
		if ( ! Schema::healthy() || ! is_email( $email ) ) {
			return array( 'data' => $data, 'done' => true );
		}
		$table = Schema::table( 'estat_leads' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 100", $email ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'estat_leads',
				'group_label' => __( 'Enquiries', 'estat-os' ),
				'item_id'     => 'estat-lead-' . (int) $row['id'],
				'data'        => array(
					array( 'name' => __( 'Name', 'estat-os' ), 'value' => $row['name'] ),
					array( 'name' => __( 'Phone', 'estat-os' ), 'value' => $row['phone'] ),
					array( 'name' => __( 'Email', 'estat-os' ), 'value' => $row['email'] ),
					array( 'name' => __( 'Message', 'estat-os' ), 'value' => $row['message'] ),
					array( 'name' => __( 'Received', 'estat-os' ), 'value' => $row['created_at'] ),
				),
			);
		}
		return array( 'data' => $data, 'done' => true );
	}

	/**
	 * Erase enquiries for an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array{items_removed:bool,items_retained:bool,messages:array<int,string>,done:bool}
	 */
	public static function erase( string $email, int $page = 1 ): array {
		global $wpdb;
		$removed = false;
		if ( Schema::healthy() && is_email( $email ) ) {
			$table = Schema::table( 'estat_leads' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s LIMIT 100", $email ) );
			foreach ( $ids as $id ) {
				Leads::erase( (int) $id );
				$removed = true;
			}
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Suggest privacy policy text.
	 *
	 * @return void
	 */
	public static function add_policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'When you send an enquiry about a property, we store your name, phone number, email address and message so that our team can reply to you. You can ask us to erase this information at any time.', 'estat-os' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Real estate enquiries', 'estat-os' ), wp_kses_post( $content ) );
	}
}
