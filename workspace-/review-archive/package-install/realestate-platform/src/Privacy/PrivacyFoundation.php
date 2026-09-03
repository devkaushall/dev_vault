<?php
/** WordPress privacy integration for user and workflow data. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Privacy;

use Mayfair\RealEstatePlatform\Leads\LeadService;
use Mayfair\RealEstatePlatform\SiteVisits\SiteVisitService;

final class PrivacyFoundation {
	public function __construct( private ?LeadService $leads = null, private ?SiteVisitService $visits = null ) {}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'policy' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function policy(): void {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				__( 'RealEstate Platform', 'realestate-platform' ),
				'<p>' . esc_html__( 'The platform stores user-owned favorites, saved searches, alert preferences, and private contact or site-visit requests. Users can delete saved state, opt out of alerts, request export, or request erasure.', 'realestate-platform' ) . '</p>'
			);
		}
	}

	/** @param array<string,mixed> $exporters @return array<string,mixed> */
	public function exporters( array $exporters ): array {
		$exporters['realestate-platform-user-state'] = array(
			'exporter_friendly_name' => __( 'Real-estate user and workflow data', 'realestate-platform' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/** @param array<string,mixed> $erasers @return array<string,mixed> */
	public function erasers( array $erasers ): array {
		$erasers['realestate-platform-user-state'] = array(
			'eraser_friendly_name' => __( 'Real-estate user and workflow data', 'realestate-platform' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/** @return array{data:list<array<string,mixed>>,done:bool} */
	public function export( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );
		if ( $page > 1 ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}
		$user_id = $user instanceof \WP_User ? $user->ID : 0;
		global $wpdb;
		$favorites    = $user_id > 0 ? $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}rep_favorites WHERE user_id=%d ORDER BY post_id", $user_id ) ) : array();
		$searches     = $user_id > 0 ? $wpdb->get_results( $wpdb->prepare( "SELECT title,criteria_json,enabled,created_at FROM {$wpdb->prefix}rep_saved_searches WHERE user_id=%d ORDER BY id", $user_id ), ARRAY_A ) : array();
		$profiles     = $user_id > 0 ? get_posts(
			array(
				'author'         => $user_id,
				'post_type'      => array( 'agent', 'agency' ),
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
			)
		) : array();
		$profile_data = array();
		foreach ( $profiles as $profile_id ) {
			$profile_data[] = array(
				'id'            => $profile_id,
				'type'          => get_post_type( $profile_id ),
				'title'         => get_the_title( $profile_id ),
				'public_email'  => get_post_meta( $profile_id, 'rep_public_email', true ),
				'public_phone'  => get_post_meta( $profile_id, 'rep_public_phone', true ),
				'private_notes' => get_post_meta( $profile_id, 'rep_private_notes', true ),
			);
		}
		$workflow = array();
		if ( $this->leads instanceof LeadService ) {
			$workflow['leads'] = $this->leads->exportForEmail( $email, $user_id );
		}
		if ( $this->visits instanceof SiteVisitService ) {
			$workflow['site_visits'] = $this->visits->exportForEmail( $email, $user_id );
		}
		return array(
			'data' => array(
				array(
					'group_id'    => 'realestate-platform-user-state',
					'group_label' => 'Real-estate user and workflow data',
					'item_id'     => 'user-' . $user_id,
					'data'        => array(
						array(
							'name'  => 'Favorite Property IDs',
							'value' => implode( ',', array_map( 'intval', is_array( $favorites ) ? $favorites : array() ) ),
						),
						array(
							'name'  => 'Saved searches and alert preferences',
							'value' => (string) wp_json_encode( is_array( $searches ) ? $searches : array() ),
						),
						array(
							'name'  => 'Agent and agency profiles',
							'value' => (string) wp_json_encode( $profile_data ),
						),
						array(
							'name'  => 'Private leads, requests, and site visits',
							'value' => (string) wp_json_encode( $workflow ),
						),
					),
				),
			),
			'done' => true,
		);
	}

	/** @return array{items_removed:bool,items_retained:bool,messages:list<string>,done:bool} */
	public function erase( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );
		if ( $page > 1 ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}
		$user_id = $user instanceof \WP_User ? $user->ID : 0;
		global $wpdb;
		$alerts    = $user_id > 0 ? $wpdb->delete( $wpdb->prefix . 'rep_search_alerts', array( 'user_id' => $user_id ), array( '%d' ) ) : 0;
		$searches  = $user_id > 0 ? $wpdb->delete( $wpdb->prefix . 'rep_saved_searches', array( 'user_id' => $user_id ), array( '%d' ) ) : 0;
		$favorites = $user_id > 0 ? $wpdb->delete( $wpdb->prefix . 'rep_favorites', array( 'user_id' => $user_id ), array( '%d' ) ) : 0;
		$profiles  = $user_id > 0 ? get_posts(
			array(
				'author'         => $user_id,
				'post_type'      => array( 'agent', 'agency' ),
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
			)
		) : array();
		foreach ( $profiles as $profile_id ) {
			delete_post_meta( $profile_id, 'rep_public_email' );
			delete_post_meta( $profile_id, 'rep_public_phone' );
			delete_post_meta( $profile_id, 'rep_private_notes' );
		}
		$visits = $this->visits instanceof SiteVisitService ? $this->visits->eraseForEmail( $email, $user_id ) : 0;
		$leads  = $this->leads instanceof LeadService ? $this->leads->eraseForEmail( $email, $user_id ) : 0;
		return array(
			'items_removed'  => (bool) ( (int) $alerts + (int) $searches + (int) $favorites + count( $profiles ) + $visits + $leads ),
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
