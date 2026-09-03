<?php
/** Read-only Phase 7 workflow health check. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Diagnostics;

use Mayfair\RealEstatePlatform\Contracts\DiagnosticCheckInterface;
use Mayfair\RealEstatePlatform\Leads\LeadService;
use Mayfair\RealEstatePlatform\SiteVisits\SiteVisitService;

final class LeadWorkflowCheck implements DiagnosticCheckInterface {
	public function name(): string {
		return 'Lead workflows';
	}

	public function run(): DiagnosticResult {
		global $wpdb;
		$table_names = array( 'rep_leads', 'rep_lead_requests', 'rep_lead_status_history', 'rep_lead_assignment_history', 'rep_site_visits', 'rep_site_visit_history', 'rep_notification_events' );
		$tables      = array();
		foreach ( $table_names as $name ) {
			$table           = $wpdb->prefix . $name;
			$tables[ $name ] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
		}
		if ( in_array( false, $tables, true ) ) {
			return new DiagnosticResult( $this->name(), DiagnosticResult::FAIL, 'Phase-7 workflow tables are missing.', array( 'tables' => $tables ), 'Run activation migrations.' );
		}
		$orphan_requests = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_lead_requests r LEFT JOIN ' . $wpdb->prefix . 'rep_leads l ON l.id=r.lead_id WHERE l.id IS NULL' );
		$orphan_visits   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_site_visits v LEFT JOIN ' . $wpdb->prefix . 'rep_leads l ON l.id=v.lead_id WHERE l.id IS NULL' );
		$invalid_leads   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}rep_leads WHERE status NOT IN ('new','contacted','qualified','converted','lost','spam')" );
		$invalid_visits  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}rep_site_visits WHERE status NOT IN ('requested','scheduled','confirmed','reschedule_requested','completed','cancelled')" );
		$failed_events   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}rep_notification_events WHERE status='failed'" );
		$scheduler       = wp_next_scheduled( 'realestate_platform_lead_notifications' );
		$details         = array(
			'tables'          => $tables,
			'schema_current'  => (string) get_option( 'realestate_platform_db_version', '0' ) === REALESTATE_PLATFORM_DB_VERSION,
			'orphan_requests' => $orphan_requests,
			'orphan_visits'   => $orphan_visits,
			'invalid_leads'   => $invalid_leads,
			'invalid_visits'  => $invalid_visits,
			'failed_events'   => $failed_events,
			'scheduler'       => $scheduler ? $scheduler : null,
			'lead_statuses'   => LeadService::statuses(),
			'visit_statuses'  => SiteVisitService::statuses(),
		);
		$status          = ( ! $details['schema_current'] || $orphan_requests > 0 || $orphan_visits > 0 || $invalid_leads > 0 || $invalid_visits > 0 ) ? DiagnosticResult::FAIL : ( $failed_events > 0 || ! $scheduler ? DiagnosticResult::WARN : DiagnosticResult::PASS );
		return new DiagnosticResult( $this->name(), $status, 'Lead and site-visit workflow health.', $details, 'Review failures; diagnostics does not repair operational data.' );
	}
}
