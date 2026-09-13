<?php
/**
 * Activity history.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Audit;

use EstatOS\Install\Schema;
use EstatOS\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Records who did what. Never stores passwords, secrets or full personal data.
 */
final class AuditLog {

	/**
	 * Context keys that must never be written to the log.
	 *
	 * @var string[]
	 */
	private const FORBIDDEN = array( 'password', 'pass', 'secret', 'token', 'api_key', 'webhook_secret', 'authorization', 'phone', 'email' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'update_option_' . \EstatOS\Settings\Settings::OPTION, array( __CLASS__, 'on_settings_changed' ), 10, 0 );
	}

	/**
	 * Write an entry.
	 *
	 * @param string              $action      Action key, e.g. lead.created.
	 * @param string              $object_type Object type.
	 * @param int                 $object_id   Object ID.
	 * @param array<string,mixed> $context     Extra non-sensitive context.
	 * @return void
	 */
	public static function record( string $action, string $object_type = '', int $object_id = 0, array $context = array() ): void {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return;
		}
		$clean = array();
		foreach ( $context as $key => $value ) {
			$key = Sanitize::text( (string) $key );
			if ( in_array( strtolower( $key ), self::FORBIDDEN, true ) ) {
				continue;
			}
			$clean[ $key ] = is_scalar( $value ) ? Sanitize::text( (string) $value ) : wp_json_encode( Sanitize::text_array( (array) $value ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			Schema::table( 'estat_audit' ),
			array(
				'created_at'  => current_time( 'mysql', true ),
				'user_id'     => get_current_user_id(),
				'action'      => Sanitize::text( $action ),
				'object_type' => Sanitize::text( $object_type ),
				'object_id'   => $object_id,
				'context'     => (string) wp_json_encode( $clean ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Read recent entries.
	 *
	 * @param array<string,mixed> $args Query args: per_page, page, action, object_type.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;
		$table    = Schema::table( 'estat_audit' );
		$per_page = min( 200, max( 1, (int) ( $args['per_page'] ?? 25 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['action'] ) ) {
			$where[]  = 'action = %s';
			$params[] = Sanitize::text( $args['action'] );
		}
		if ( ! empty( $args['object_type'] ) ) {
			$where[]  = 'object_type = %s';
			$params[] = Sanitize::text( $args['object_type'] );
		}
		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$items  = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );
		// phpcs:enable

		return array( 'items' => is_array( $items ) ? $items : array(), 'total' => $total );
	}

	/**
	 * Log a settings change without recording the values themselves.
	 *
	 * @return void
	 */
	public static function on_settings_changed(): void {
		self::record( 'settings.changed', 'settings', 0, array() );
	}

	/**
	 * Delete entries older than N days.
	 *
	 * @param int $days Retention days.
	 * @return int Rows removed.
	 */
	public static function purge_older_than( int $days ): int {
		if ( $days <= 0 ) {
			return 0;
		}
		global $wpdb;
		$table  = Schema::table( 'estat_audit' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}

	/**
	 * Human sentence for an action key.
	 *
	 * @param string $action Action.
	 * @return string
	 */
	public static function describe( string $action ): string {
		$map = array(
			'listing.created'   => __( 'A listing was added', 'estat-os' ),
			'listing.updated'   => __( 'A listing was changed', 'estat-os' ),
			'listing.published' => __( 'A listing went live on the website', 'estat-os' ),
			'listing.deleted'   => __( 'A listing was deleted', 'estat-os' ),
			'lead.created'      => __( 'A new enquiry arrived', 'estat-os' ),
			'lead.updated'      => __( 'An enquiry was updated', 'estat-os' ),
			'lead.assigned'     => __( 'An enquiry was given to a team member', 'estat-os' ),
			'lead.erased'       => __( 'Personal information was erased', 'estat-os' ),
			'visit.scheduled'   => __( 'A site visit was scheduled', 'estat-os' ),
			'visit.updated'     => __( 'A site visit was updated', 'estat-os' ),
			'settings.changed'  => __( 'Office settings were changed', 'estat-os' ),
			'import.started'    => __( 'A spreadsheet import started', 'estat-os' ),
			'import.completed'  => __( 'A spreadsheet import finished', 'estat-os' ),
			'webhook.attempt'   => __( 'A connection was notified', 'estat-os' ),
			'maintenance.run'   => __( 'Maintenance was run', 'estat-os' ),
			'practice.cleared'  => __( 'Practice records were removed', 'estat-os' ),
			'form.saved'        => __( 'A form was saved', 'estat-os' ),
			'form.deleted'      => __( 'A form was deleted', 'estat-os' ),
		);
		return $map[ $action ] ?? $action;
	}
}
