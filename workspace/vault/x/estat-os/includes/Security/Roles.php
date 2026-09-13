<?php
/**
 * Roles and granular capabilities.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Two office roles plus administrator compatibility. Capability names are a
 * public contract; new ones may be added but existing ones are never renamed.
 */
final class Roles {

	/**
	 * Office owner role key.
	 */
	public const OWNER = 'estat_office_owner';

	/**
	 * Office agent role key.
	 */
	public const AGENT = 'estat_office_agent';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 10, 4 );
	}

	/**
	 * All plugin capabilities with human descriptions.
	 *
	 * @return array<string,string>
	 */
	public static function all(): array {
		return array(
			'estat_manage_settings'     => __( 'Change office settings', 'estat-os' ),
			'estat_manage_listings'     => __( 'Add and edit listings', 'estat-os' ),
			'estat_publish_listings'    => __( 'Publish listings to the website', 'estat-os' ),
			'estat_delete_listings'     => __( 'Delete listings', 'estat-os' ),
			'estat_verify_listings'     => __( 'Mark listings as office verified', 'estat-os' ),
			'estat_manage_projects'     => __( 'Manage societies and projects', 'estat-os' ),
			'estat_manage_team'         => __( 'Manage the team', 'estat-os' ),
			'estat_manage_insights'     => __( 'Write blogs, articles and insights', 'estat-os' ),
			'estat_manage_leads'        => __( 'Work with enquiries', 'estat-os' ),
			'estat_assign_leads'        => __( 'Assign enquiries to team members', 'estat-os' ),
			'estat_erase_leads'         => __( 'Erase personal information', 'estat-os' ),
			'estat_manage_visits'       => __( 'Manage site visits', 'estat-os' ),
			'estat_manage_forms'        => __( 'Build and edit forms', 'estat-os' ),
			'estat_view_submissions'    => __( 'See form submissions', 'estat-os' ),
			'estat_import_inventory'    => __( 'Import spreadsheets', 'estat-os' ),
			'estat_export_inventory'    => __( 'Export spreadsheets', 'estat-os' ),
			'estat_view_reports'        => __( 'See reports', 'estat-os' ),
			'estat_manage_integrations' => __( 'Manage connections and API', 'estat-os' ),
			'estat_view_audit'          => __( 'See the activity history', 'estat-os' ),
			'estat_run_maintenance'     => __( 'Run maintenance tools', 'estat-os' ),
		);
	}

	/**
	 * Capabilities granted to the office owner.
	 *
	 * @return string[]
	 */
	public static function owner_caps(): array {
		return array_keys( self::all() );
	}

	/**
	 * Capabilities granted to an office agent.
	 *
	 * @return string[]
	 */
	public static function agent_caps(): array {
		return array(
			'estat_manage_listings',
			'estat_manage_leads',
			'estat_manage_visits',
			'estat_manage_insights',
			'estat_view_submissions',
			'estat_export_inventory',
		);
	}

	/**
	 * Private per-post capabilities registered by the plugin's content types.
	 *
	 * These are never shown to the office. WordPress checks them internally
	 * when someone opens or deletes a single record, and they must exist on
	 * every role that is allowed to work with that record.
	 *
	 * @return string[]
	 */
	public static function record_caps(): array {
		$caps = array();

		foreach ( array( 'estat_listing', 'estat_project', 'estat_agent', 'estat_agency', 'estat_insight', 'estat_document' ) as $record ) {
			$caps[] = 'edit_' . $record;
			$caps[] = 'read_' . $record;
			$caps[] = 'delete_' . $record;
		}

		return $caps;
	}

	/**
	 * Create/refresh roles. Idempotent: safe to run on every activation.
	 *
	 * @return void
	 */
	public static function install(): void {
		$base = array( 'read' => true, 'upload_files' => true );

		// Per-post capabilities used internally by WordPress. They are separate
		// from the office capabilities above so that a plain permission check
		// with no post ID keeps working; see PostTypes::simple_caps().
		$base = array_merge( $base, array_fill_keys( self::record_caps(), true ) );

		$owner = array_merge(
			$base,
			array( 'edit_posts' => true, 'publish_posts' => true, 'edit_published_posts' => true, 'delete_posts' => true, 'edit_others_posts' => true )
		);
		foreach ( self::owner_caps() as $cap ) {
			$owner[ $cap ] = true;
		}

		$agent = array_merge( $base, array( 'edit_posts' => true, 'edit_published_posts' => true ) );
		foreach ( self::agent_caps() as $cap ) {
			$agent[ $cap ] = true;
		}

		self::ensure_role( self::OWNER, __( 'Office Owner', 'estat-os' ), $owner );
		self::ensure_role( self::AGENT, __( 'Office Agent', 'estat-os' ), $agent );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array_merge( self::owner_caps(), self::record_caps() ) as $cap ) {
				if ( ! $admin->has_cap( $cap ) ) {
					$admin->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Create a role or sync its capabilities without destroying custom ones.
	 *
	 * @param string              $key   Role key.
	 * @param string              $label Display name.
	 * @param array<string,bool>  $caps  Capabilities.
	 * @return void
	 */
	private static function ensure_role( string $key, string $label, array $caps ): void {
		$role = get_role( $key );
		if ( ! $role ) {
			add_role( $key, $label, $caps );
			return;
		}
		foreach ( $caps as $cap => $grant ) {
			if ( $grant && ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove plugin capabilities from roles (used only on explicit data delete).
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		remove_role( self::OWNER );
		remove_role( self::AGENT );
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array_merge( array_keys( self::all() ), self::record_caps() ) as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}

	/**
	 * Agents may only manage records they own or are assigned to.
	 *
	 * @param string[] $caps    Required primitive capabilities.
	 * @param string   $cap     Requested capability.
	 * @param int      $user_id User ID.
	 * @param array    $args    Extra arguments; args[0] is the object ID.
	 * @return string[]
	 */
	public static function map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
		if ( 'estat_edit_record' !== $cap ) {
			return $caps;
		}
		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return array( 'do_not_allow' );
		}
		if ( user_can( $user_id, 'estat_manage_settings' ) ) {
			return array( 'estat_manage_listings' );
		}
		$assigned = (int) get_post_meta( $post_id, '_estat_assigned_user', true );
		if ( (int) $post->post_author === $user_id || $assigned === $user_id ) {
			return array( 'estat_manage_listings' );
		}
		return array( 'do_not_allow' );
	}

	/**
	 * Current user check helper used throughout the plugin.
	 *
	 * @param string $cap Capability.
	 * @return bool
	 */
	public static function can( string $cap ): bool {
		return current_user_can( $cap );
	}
}
