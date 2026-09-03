<?php
require '/wordpress/wp-load.php';

$bootstrap = \Mayfair\RealEstatePlatform\Core\Bootstrap::instance();
$services  = $bootstrap->services();
$services->get( 'content' )->initialize();
do_action( 'rest_api_init' );

function p7_request( string $method, string $route, array $params = array(), array $headers = array() ): \WP_REST_Response|\WP_Error {
	$request = new \WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	foreach ( $headers as $key => $value ) {
		$request->set_header( $key, $value );
	}
	$response = rest_do_request( $request );
	return $response instanceof \WP_Error ? $response : rest_ensure_response( $response );
}
function p7_ok( mixed $response ): bool {
	return $response instanceof \WP_REST_Response && $response->get_status() >= 200 && $response->get_status() < 300;
}
function p7_bad( mixed $response ): bool {
	if ( $response instanceof \WP_Error ) {
		return true;
	}
	return $response instanceof \WP_REST_Response && $response->get_status() >= 400 && $response->get_status() < 500;
}

$checks = array();
$admin  = get_current_user_id();
$user_a = wp_create_user( 'phase7-owner', 'phase7-pass', 'phase7-owner@example.test' );
$user_b = wp_create_user( 'phase7-other', 'phase7-pass', 'phase7-other@example.test' );
foreach ( array( $user_a ) as $user_id ) {
	$user = get_user_by( 'id', $user_id );
	foreach ( array( 'manage_leads', 'view_leads', 'edit_leads', 'assign_leads', 'manage_forms', 'manage_site_visits', 'view_site_visits', 'edit_agents', 'edit_agencies', 'edit_properties' ) as $cap ) {
		$user->add_cap( $cap );
	}
}
$property = wp_insert_post( array( 'post_type' => 'property', 'post_status' => 'publish', 'post_title' => 'Phase 7 Property' ) );
$project  = wp_insert_post( array( 'post_type' => 'project', 'post_status' => 'publish', 'post_title' => 'Phase 7 Project' ) );
$draft    = wp_insert_post( array( 'post_type' => 'property', 'post_status' => 'draft', 'post_title' => 'Not public' ) );

$leads        = $services->get( 'leads' );
$requests     = $services->get( 'requests' );
$visits       = $services->get( 'site_visits' );
$notifications = $services->get( 'lead_notifications' );

$checks['schema_004'] = get_option( 'realestate_platform_db_version' ) === '004';
global $wpdb;
foreach ( array( 'rep_leads', 'rep_lead_requests', 'rep_lead_status_history', 'rep_lead_assignment_history', 'rep_site_visits', 'rep_site_visit_history', 'rep_notification_events' ) as $table ) {
	$checks['table_' . $table] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $table ) ) === $wpdb->prefix . $table;
}

wp_set_current_user( 0 );
$checks['anonymous_private_lead_blocked'] = p7_bad( p7_request( 'GET', '/realestate-platform/v1/leads/1' ) );
$checks['anonymous_invalid_form_rejected'] = p7_bad( p7_request( 'POST', '/realestate-platform/v1/requests', array( 'name' => 'A', 'email' => 'bad', 'consent' => true ) ) );
$public = p7_request( 'POST', '/realestate-platform/v1/requests', array( 'name' => 'Public Buyer', 'email' => 'public-buyer@example.test', 'message' => 'Interested', 'property_id' => (string) $property, 'consent' => true, 'idempotency_key' => 'public-1' ) );
$checks['public_request_acknowledged'] = p7_ok( $public ) && 202 === $public->get_status() && array_keys( $public->get_data() ) === array( 'accepted', 'status' );
$checks['public_no_contact_serialization'] = p7_ok( $public ) && ! str_contains( wp_json_encode( $public->get_data() ), 'public-buyer@example.test' );
$repeat = p7_request( 'POST', '/realestate-platform/v1/requests', array( 'name' => 'Public Buyer', 'email' => 'public-buyer@example.test', 'message' => 'Interested', 'property_id' => (string) $property, 'consent' => true, 'idempotency_key' => 'public-1' ) );
$checks['public_replay_acknowledged'] = p7_ok( $repeat );
$checks['draft_context_rejected'] = p7_bad( p7_request( 'POST', '/realestate-platform/v1/requests', array( 'name' => 'Draft Buyer', 'email' => 'draft@example.test', 'property_id' => $draft, 'consent' => true ) ) );

wp_set_current_user( $user_a );
$owner_input = array( 'name' => 'Owner Buyer', 'email' => 'owner-buyer@example.test', 'phone' => '+91 99999 11111', 'message' => 'Owner inquiry', 'property_id' => $property, 'project_id' => $project, 'consent' => true, 'idempotency_key' => 'owner-1', 'source' => 'rest' );
$created = $requests->submit( $owner_input, $user_a, 'rest' );
$checks['service_create'] = is_array( $created ) && true === $created['accepted'];
$lead_id = is_array( $created ) ? (int) $created['lead_id'] : 0;
$checks['lead_request_linked'] = $lead_id > 0 && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_lead_requests WHERE lead_id=%d', $lead_id ) ) === 1;
$checks['lead_initial_status'] = $lead_id > 0 && 'new' === $leads->get( $lead_id, $user_a )['status'];
$csrf_status = p7_request( 'POST', '/realestate-platform/v1/leads/' . $lead_id . '/status', array( 'status' => 'contacted' ) );
$csrf_data = $csrf_status instanceof \WP_REST_Response ? $csrf_status->get_data() : array();
$checks['authenticated_mutation_csrf'] = p7_bad( $csrf_status ) && is_array( $csrf_data ) && 'csrf_required' === ( $csrf_data['code'] ?? '' ) && 'new' === $leads->get( $lead_id, $user_a )['status'];
$checks['lead_invalid_context_no_mutation'] = p7_bad( $requests->submit( array( 'name' => 'Draft', 'email' => 'draft2@example.test', 'property_id' => $draft, 'consent' => true, 'idempotency_key' => 'draft-2' ), $user_a ) );
$checks['lead_invalid_transition_blocked'] = is_wp_error( $leads->transition( $lead_id, 'converted', $user_a ) );
$transition = $leads->transition( $lead_id, 'contacted', $user_a, 'Called.' );
$checks['lead_transition_and_history'] = is_array( $transition ) && 'contacted' === $transition['status'] && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_lead_status_history WHERE lead_id=%d', $lead_id ) ) === 2;
wp_set_current_user( $user_b );
$checks['lead_idor_read_blocked'] = is_wp_error( $leads->get( $lead_id, $user_b ) );
$checks['lead_idor_status_blocked'] = is_wp_error( $leads->transition( $lead_id, 'qualified', $user_b ) );
wp_set_current_user( $user_a );
$checks['owner_read_allowed'] = is_array( $leads->get( $lead_id, $user_a ) );

$agent  = wp_insert_post( array( 'post_type' => 'agent', 'post_status' => 'publish', 'post_title' => 'Phase 7 Agent', 'post_author' => $user_a ) );
$agency = wp_insert_post( array( 'post_type' => 'agency', 'post_status' => 'publish', 'post_title' => 'Phase 7 Agency', 'post_author' => $user_a ) );
update_post_meta( $agent, 'rep_agency_id', $agency );
$assigned = $leads->assign( $lead_id, $agent, $agency, $user_a );
$checks['assignment_relationship_validated'] = is_array( $assigned ) && (int) $assigned['agent_id'] === $agent && (int) $assigned['agency_id'] === $agency;
$checks['assignment_history'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_lead_assignment_history WHERE lead_id=%d', $lead_id ) ) === 1;
$checks['assignment_bad_relationship_blocked'] = is_wp_error( $leads->assign( $lead_id, $agent, $property, $user_a ) );
wp_delete_post( $agent, true );
$after_profile_delete = $leads->get( $lead_id, $user_a );
$checks['profile_delete_unassigns_workflow'] = is_array( $after_profile_delete ) && null === $after_profile_delete['agent_id'] && null === $after_profile_delete['agency_id'];

$owner_visit_input = array( 'name' => 'Owner Visit', 'email' => 'owner-buyer@example.test', 'message' => 'Owner visit.', 'lead_id' => $lead_id, 'property_id' => $property, 'consent' => true, 'requested_start_at' => gmdate( 'Y-m-d H:i:s', time() + 108000 ), 'requested_end_at' => gmdate( 'Y-m-d H:i:s', time() + 111600 ), 'idempotency_key' => 'owner-visit-1', 'source' => 'rest' );
$owner_visit = $visits->createFromArray( $owner_visit_input, $user_a );
$owner_visit_id = is_array( $owner_visit ) ? (int) $owner_visit['id'] : 0;

$future_start = gmdate( 'Y-m-d H:i:s', time() + 86400 );
$future_end   = gmdate( 'Y-m-d H:i:s', time() + 90000 );
$visit_input  = array( 'name' => 'Visit Buyer', 'email' => 'visit-buyer@example.test', 'message' => 'Please arrange a visit.', 'property_id' => $property, 'consent' => true, 'requested_start_at' => $future_start, 'requested_end_at' => $future_end, 'idempotency_key' => 'visit-1', 'source' => 'rest' );
$visit_created = $visits->createFromArray( $visit_input, $user_a );
$visit_id = is_array( $visit_created ) ? (int) $visit_created['id'] : 0;
$visit_repeat = $visits->createFromArray( $visit_input, $user_a );
$visit_dedupe = hash( 'sha256', 'visit-buyer@example.test|visit|' . $property . '|' . $future_start . '|' . $future_end . '|visit-1' );
$checks['site_visit_replay_deduped'] = is_array( $visit_repeat ) && (int) $visit_repeat['id'] === $visit_id && $visit_id > 0 && 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_site_visits WHERE dedupe_key=%s', $visit_dedupe ) );
$checks['site_visit_create'] = $visit_id > 0 && 'requested' === $visits->get( $visit_id, $user_a )['status'];
$checks['site_visit_history'] = $visit_id > 0 && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_site_visit_history WHERE visit_id=%d', $visit_id ) ) === 1;
$checks['site_visit_invalid_transition'] = is_wp_error( $visits->transition( $visit_id, 'completed', $user_a ) );
$scheduled = $visits->transition( $visit_id, 'scheduled', $user_a );
$direct_completed = $visits->transition( $visit_id, 'completed', $user_a );
$after_direct = $visits->get( $visit_id, $user_a );
$confirmed = $visits->transition( $visit_id, 'confirmed', $user_a );
$checks['site_visit_scheduled_completed_blocked'] = is_wp_error( $direct_completed ) && is_array( $scheduled ) && is_array( $after_direct ) && 'scheduled' === $after_direct['status'];
$checks['site_visit_state_machine'] = is_array( $scheduled ) && is_array( $confirmed ) && 'confirmed' === $confirmed['status'];
$reschedule_requested = $visits->transition( $visit_id, 'reschedule_requested', $user_a );
$rescheduled = $visits->reschedule( $visit_id, gmdate( 'Y-m-d H:i:s', time() + 172800 ), gmdate( 'Y-m-d H:i:s', time() + 176400 ), $user_a );
$checks['site_visit_reschedule'] = is_array( $reschedule_requested ) && 'reschedule_requested' === $reschedule_requested['status'] && is_array( $rescheduled ) && 'confirmed' === $rescheduled['status'];
$cancelled = $visits->transition( $visit_id, 'cancelled', $user_a, 'Buyer cancelled.' );
$checks['site_visit_cancelled'] = is_array( $cancelled ) && 'cancelled' === $cancelled['status'];
wp_set_current_user( 0 );
$public_visit = p7_request( 'POST', '/realestate-platform/v1/site-visits', array( 'name' => 'REST Visit', 'email' => 'rest-visit@example.test', 'property_id' => $property, 'consent' => true, 'requested_start_at' => gmdate( 'Y-m-d H:i:s', time() + 259200 ), 'requested_end_at' => gmdate( 'Y-m-d H:i:s', time() + 262800 ), 'idempotency_key' => 'rest-visit-1' ) );
$checks['public_visit_acknowledged'] = p7_ok( $public_visit ) && 202 === $public_visit->get_status();
$checks['anonymous_visit_read_blocked'] = p7_bad( p7_request( 'GET', '/realestate-platform/v1/site-visits/' . $visit_id ) );
wp_set_current_user( $user_a );
$wpdb->query( "UPDATE {$wpdb->prefix}rep_notification_events SET status='sent',next_attempt_at=NULL WHERE status='pending'" );

$fake = new class() implements \Mayfair\RealEstatePlatform\Leads\LeadNotificationProviderInterface {
	public bool $success = false;
	public function send( array $event ): bool { return $this->success; }
};
$fake_notifications = new \Mayfair\RealEstatePlatform\Leads\LeadNotificationService( $fake );
$fake_event = $fake_notifications->enqueue( 'test.event', 'lead', $lead_id, array( 'safe' => true ), 0, 'delivery@example.test', 'phase7-fake-event' );
$failed_dispatch = $fake_notifications->dispatch( 1 );
$fake->success = true;
$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'rep_notification_events SET next_attempt_at=NULL WHERE id=%d', (int) $fake_event ) );
$sent_dispatch = $fake_notifications->dispatch( 1 );
$event_status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . $wpdb->prefix . 'rep_notification_events WHERE id=%d', (int) $fake_event ) );
$checks['notification_failure_isolated'] = is_array( $failed_dispatch ) && 1 === $failed_dispatch['failed'] && 'contacted' === $leads->get( $lead_id, $user_a )['status'];
$checks['notification_retry_provider_success'] = is_array( $sent_dispatch ) && 1 === $sent_dispatch['sent'] && 'sent' === $event_status;

$privacy = new \Mayfair\RealEstatePlatform\Privacy\PrivacyFoundation( $leads, $visits );
$export = $privacy->export( 'owner-buyer@example.test' );
$checks['privacy_export_workflow'] = str_contains( wp_json_encode( $export ), 'Owner Buyer' ) && str_contains( wp_json_encode( $export ), 'Owner inquiry' );
$erase = $privacy->erase( 'owner-buyer@example.test' );
$erased = $leads->get( $lead_id, $user_a );
$owner_visit_dedupe = $owner_visit_id > 0 ? $wpdb->get_var( $wpdb->prepare( 'SELECT dedupe_key FROM ' . $wpdb->prefix . 'rep_site_visits WHERE id=%d', $owner_visit_id ) ) : 'missing';
$owner_visit_event = $owner_visit_id > 0 ? $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . $wpdb->prefix . 'rep_notification_events WHERE aggregate_type=%s AND aggregate_id=%d ORDER BY id DESC LIMIT 1', 'site_visit', $owner_visit_id ) ) : 'missing';
$checks['privacy_erase_workflow'] = $erase['items_removed'] && is_array( $erased ) && '[erased]' === $erased['name'] && '' === $erased['email'] && null === $owner_visit_dedupe && 'cancelled' === $owner_visit_event;

$diagnostic = new \Mayfair\RealEstatePlatform\Diagnostics\LeadWorkflowCheck();
$diagnostic_result = $diagnostic->run();
$checks['diagnostics_healthy'] = 'PASS' === $diagnostic_result->status;

$flat = array();
foreach ( $checks as $key => $value ) {
	$flat[ $key ] = (bool) $value;
}
echo wp_json_encode( array( 'status' => in_array( false, $flat, true ) ? 'FAIL' : 'PASS', 'checks' => $flat, 'diagnostic' => $diagnostic_result, 'environment' => array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'database' => 'SQLite' ) ), JSON_PRETTY_PRINT );
