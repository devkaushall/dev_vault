<?php
require '/wordpress/wp-load.php';

$bootstrap = \Mayfair\RealEstatePlatform\Core\Bootstrap::instance();
$services  = $bootstrap->services();
update_option( 'realestate_platform_settings_general', array( 'operating_mode' => 'standalone' ), false );
$services->get( 'content' )->initialize();
do_action( 'rest_api_init' );

$checks = array();
global $wpdb;

$elementor_service = $services->get( 'elementor' );
$initial_availability = \Mayfair\RealEstatePlatform\Elementor\ElementorIntegration::availability();
$checks['core_without_elementor'] = ! $initial_availability['elementor'] && is_object( $elementor_service ) && ! $elementor_service->isBooted();
$checks['acf_optional']            = ! $initial_availability['acf'] || is_object( $elementor_service );
$checks['core_services_booted']    = true;
foreach ( array( 'content', 'search_request', 'profiles', 'leads', 'requests', 'site_visits', 'favorites', 'saved_searches', 'alerts', 'diagnostics', 'elementor' ) as $service_name ) {
	$checks['service_' . $service_name] = is_object( $services->get( $service_name ) );
}

$owner_id = wp_create_user( 'phase8-owner', 'phase8-pass', 'phase8-owner@example.test' );
if ( is_wp_error( $owner_id ) ) {
	$owner_id = 0;
}
$owner = $owner_id > 0 ? get_user_by( 'id', $owner_id ) : false;
if ( $owner instanceof \WP_User ) {
	foreach ( array( 'view_realestate_diagnostics', 'edit_agents', 'edit_agencies', 'publish_agents', 'publish_agencies', 'edit_properties', 'manage_leads', 'view_leads', 'edit_leads', 'assign_leads', 'manage_site_visits', 'view_site_visits', 'publish_posts' ) as $capability ) {
		$owner->add_cap( $capability );
	}
}
wp_set_current_user( $owner_id );

$property = wp_insert_post( array( 'post_type' => 'property', 'post_status' => 'publish', 'post_title' => 'Phase 8 Property Delhi' ), true );
$project  = wp_insert_post( array( 'post_type' => 'project', 'post_status' => 'publish', 'post_title' => 'Phase 8 Project' ), true );
$draft    = wp_insert_post( array( 'post_type' => 'property', 'post_status' => 'draft', 'post_title' => 'Phase 8 Draft' ), true );
$insight  = wp_insert_post( array( 'post_type' => 'insight', 'post_status' => 'publish', 'post_title' => 'Phase 8 Insight' ), true );
$property = is_wp_error( $property ) ? 0 : (int) $property;
$project  = is_wp_error( $project ) ? 0 : (int) $project;
$draft    = is_wp_error( $draft ) ? 0 : (int) $draft;
$insight  = is_wp_error( $insight ) ? 0 : (int) $insight;

update_post_meta( $property, 'rep_price', 2500000 );
update_post_meta( $property, 'rep_currency', 'INR' );
update_post_meta( $property, 'rep_area', 1450 );
update_post_meta( $property, 'rep_area_unit', 'sqft' );
update_post_meta( $property, 'rep_address', 'Secure public address' );
update_post_meta( $property, 'rep_city', 'Delhi' );
update_post_meta( $property, 'rep_latitude', 28.6139 );
update_post_meta( $property, 'rep_longitude', 77.2090 );
update_post_meta( $property, 'rep_coordinate_privacy', 'rounded' );
update_post_meta( $property, 'rep_private_notes', 'PRIVATE PROPERTY NOTE MUST NOT LEAK' );
update_post_meta( $property, '_elementor_data', '{"id":"phase8-document","widgets":[{"id":"widget-1"}]}' );
update_post_meta( $property, '_elementor_page_settings', '{"template":"default"}' );
$elementor_document_before = array( get_post_meta( $property, '_elementor_data', true ), get_post_meta( $property, '_elementor_page_settings', true ) );
update_post_meta( $project, 'rep_price', 10000000 );
update_post_meta( $project, 'rep_developer', 'Phase 8 Developer' );
update_post_meta( $insight, 'rep_subtitle', 'Public insight subtitle' );
update_post_meta( $insight, 'rep_private_notes', 'PRIVATE INSIGHT NOTE MUST NOT LEAK' );

$p_type = wp_insert_term( 'Apartment', 'property_type' );
$p_loc  = wp_insert_term( 'Delhi', 'location' );
$pr_type = wp_insert_term( 'Residential', 'project_type' );
$i_topic = wp_insert_term( 'Market', 'insight_topic' );
$p_type  = is_array( $p_type ) ? (int) $p_type['term_id'] : (int) ( is_array( term_exists( 'Apartment', 'property_type' ) ) ? term_exists( 'Apartment', 'property_type' )['term_id'] : 0 );
$p_loc   = is_array( $p_loc ) ? (int) $p_loc['term_id'] : (int) ( is_array( term_exists( 'Delhi', 'location' ) ) ? term_exists( 'Delhi', 'location' )['term_id'] : 0 );
$pr_type = is_array( $pr_type ) ? (int) $pr_type['term_id'] : (int) ( is_array( term_exists( 'Residential', 'project_type' ) ) ? term_exists( 'Residential', 'project_type' )['term_id'] : 0 );
$i_topic = is_array( $i_topic ) ? (int) $i_topic['term_id'] : (int) ( is_array( term_exists( 'Market', 'insight_topic' ) ) ? term_exists( 'Market', 'insight_topic' )['term_id'] : 0 );
if ( $property > 0 && $p_type > 0 && $p_loc > 0 ) {
	wp_set_post_terms( $property, array( $p_type ), 'property_type' );
	wp_set_post_terms( $property, array( $p_loc ), 'location' );
}
if ( $project > 0 && $pr_type > 0 && $p_loc > 0 ) {
	wp_set_post_terms( $project, array( $pr_type ), 'project_type' );
	wp_set_post_terms( $project, array( $p_loc ), 'location' );
}
if ( $insight > 0 && $i_topic > 0 ) {
	wp_set_post_terms( $insight, array( $i_topic ), 'insight_topic' );
}

$profiles = $services->get( 'profiles' );
$agency  = $profiles->create( 'agency', array( 'title' => 'Phase 8 Agency', 'public_email' => 'agency8@example.test', 'public_phone' => '+91 11111 22222', 'website' => 'https://agency8.example.test', 'office_address' => 'Public Delhi office', 'private_notes' => 'PRIVATE AGENCY NOTE' ), $owner_id );
$agent   = $profiles->create( 'agent', array( 'title' => 'Phase 8 Agent', 'public_email' => 'agent8@example.test', 'public_phone' => '+91 33333 44444', 'website' => 'https://agent8.example.test', 'private_notes' => 'PRIVATE AGENT NOTE' ), $owner_id );
$agency_id = is_array( $agency ) ? (int) $agency['id'] : 0;
$agent_id  = is_array( $agent ) ? (int) $agent['id'] : 0;
if ( $agency_id > 0 ) {
	wp_update_post( array( 'ID' => $agency_id, 'post_status' => 'publish' ) );
}
if ( $agent_id > 0 ) {
	$profiles->assignAgency( $agent_id, $agency_id, $owner_id );
	wp_update_post( array( 'ID' => $agent_id, 'post_status' => 'publish' ) );
}
if ( $property > 0 && $agent_id > 0 && $agency_id > 0 ) {
	update_post_meta( $property, 'rep_agent_id', $agent_id );
	update_post_meta( $property, 'rep_agency_id', $agency_id );
}
$checks['core_profiles'] = $agency_id > 0 && $agent_id > 0 && is_array( $profiles->get( 'agent', $agent_id ) );

$rebuilder = $services->get( 'search_index_rebuilder' );
$rebuild   = $rebuilder->rebuild( 100 );
$search    = $services->get( 'search_request' );
$search_result = $search->execute( array( 'keyword' => 'Phase 8 Property Delhi', 'per_page' => 10 ) );
$search_ids = array();
foreach ( is_array( $search_result['results'] ?? null ) ? $search_result['results'] : array() as $result ) {
	if ( is_object( $result ) && method_exists( $result, 'jsonSerialize' ) ) {
		$result = $result->jsonSerialize();
	}
	if ( is_array( $result ) && isset( $result['id'] ) ) {
		$search_ids[] = (int) $result['id'];
	}
}
$checks['core_search'] = $rebuild['failed'] === 0 && in_array( $property, $search_ids, true );

$rest_status = rest_do_request( new \WP_REST_Request( 'GET', '/realestate-platform/v1/status' ) );
$checks['core_rest'] = $rest_status instanceof \WP_REST_Response && 200 === $rest_status->get_status();

$favorites = $services->get( 'favorites' );
$favorite_added = $owner_id > 0 && $property > 0 ? $favorites->add( $owner_id, $property ) : false;
$checks['core_user_features'] = true === $favorite_added && $favorites->contains( $owner_id, $property );

$requests = $services->get( 'requests' );
$core_lead = $requests->submit( array( 'name' => 'Core Buyer', 'email' => 'core-buyer@example.test', 'message' => 'Core request.', 'property_id' => $property, 'consent' => true, 'idempotency_key' => 'phase8-core-1' ), $owner_id, 'website' );
$core_lead_id = is_array( $core_lead ) ? (int) $core_lead['lead_id'] : 0;
$checks['core_leads'] = is_array( $core_lead ) && $core_lead['accepted'] && $core_lead_id > 0 && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_lead_requests WHERE lead_id=%d', $core_lead_id ) ) === 1;

$workflow_check = new \Mayfair\RealEstatePlatform\Diagnostics\LeadWorkflowCheck();
$workflow_result = $workflow_check->run();
$checks['core_diagnostics'] = 'PASS' === $workflow_result->status;

// Contract-level fake managers are used only because no real Elementor artifact is installed.
if ( ! class_exists( 'Elementor\Plugin' ) ) {
	eval( 'namespace Elementor; class Plugin {}' );
}
if ( ! class_exists( 'Elementor\Core\DynamicTags\Tag' ) ) {
	eval( 'namespace Elementor\Core\DynamicTags; abstract class Tag { abstract public function get_name(): string; abstract public function get_title(): string; abstract public function get_group(): array; abstract public function get_categories(): array; abstract public function render(): void; }' );
}
if ( ! class_exists( 'Elementor\Modules\DynamicTags\Module' ) ) {
	eval( 'namespace Elementor\Modules\DynamicTags; class Module { public const TEXT_CATEGORY = "text"; public const URL_CATEGORY = "url"; }' );
}
if ( ! class_exists( 'ElementorPro\Modules\Forms\Classes\Action_Base' ) ) {
	eval( 'namespace ElementorPro\Modules\Forms\Classes; abstract class Action_Base { abstract public function get_name(): string; abstract public function get_label(): string; abstract public function register_settings_section( $widget ): void; abstract public function run( $record, $ajax_handler ): void; public function on_export( $element ): array { return $element; } }' );
}
do_action( 'elementor/loaded' );
$checks['conditional_boot_with_contract'] = is_object( $elementor_service ) && $elementor_service->isBooted();

$tag_manager = new class() {
	/** @var list<object> */
	public array $tags = array();
	/** @var array<string,array<string,mixed>> */
	public array $groups = array();
	/** @param array<string,mixed> $args */
	public function register_group( string $id, array $args ): void { $this->groups[ $id ] = $args; }
	public function register( object $tag ): void { $this->tags[] = $tag; }
};
do_action( 'elementor/dynamic_tags/register', $tag_manager );
$tag_map = array();
foreach ( $tag_manager->tags as $tag ) {
	$tag_map[ $tag->get_name() ] = $tag;
}
$checks['dynamic_tags_registered'] = count( $tag_map ) === count( \Mayfair\RealEstatePlatform\Elementor\TagCatalog::definitions() ) && isset( $tag_manager->groups['realestate-platform'] );

$render_tag = static function ( mixed $tag ): string {
	if ( ! is_object( $tag ) ) {
		return '';
	}
	ob_start();
	$tag->render();
	return (string) ob_get_clean();
};
$GLOBALS['post'] = get_post( $property );
$property_price = $render_tag( $tag_map['rep_property_price'] ?? null );
$property_type  = $render_tag( $tag_map['rep_property_type'] ?? null );
$property_loc   = $render_tag( $tag_map['rep_property_location'] ?? null );
$property_agent = $render_tag( $tag_map['rep_property_agent'] ?? null );
$property_lat   = $render_tag( $tag_map['rep_property_latitude'] ?? null );
$checks['property_dynamic_context'] = '2500000.00' === str_replace( ',', '', $property_price ) && 'Apartment' === $property_type && 'Delhi' === $property_loc && 'Phase 8 Agent' === $property_agent && '28.614' === $property_lat;
$GLOBALS['post'] = get_post( $project );
$project_type   = $render_tag( $tag_map['rep_project_type'] ?? null );
$project_loc    = $render_tag( $tag_map['rep_project_location'] ?? null );
$checks['project_dynamic_context'] = 'Residential' === $project_type && 'Delhi' === $project_loc && 'Published' === $render_tag( $tag_map['rep_project_status'] ?? null );
$GLOBALS['post'] = get_post( $agent_id );
$agent_phone = $render_tag( $tag_map['rep_agent_public_phone'] ?? null );
$agent_agency = $render_tag( $tag_map['rep_agent_agency'] ?? null );
$agent_output = $render_tag( $tag_map['rep_agent_title'] ?? null ) . $agent_phone . $agent_agency;
$checks['agent_dynamic_context'] = str_contains( $agent_output, 'Phase 8 Agent' ) && str_contains( $agent_output, '+91 33333 44444' ) && str_contains( $agent_output, 'Phase 8 Agency' ) && ! str_contains( $agent_output, 'PRIVATE' );
$GLOBALS['post'] = get_post( $agency_id );
$agency_output = $render_tag( $tag_map['rep_agency_title'] ?? null ) . $render_tag( $tag_map['rep_agency_office_address'] ?? null );
$checks['agency_dynamic_context'] = str_contains( $agency_output, 'Phase 8 Agency' ) && str_contains( $agency_output, 'Public Delhi office' ) && ! str_contains( $agency_output, 'PRIVATE' );
$GLOBALS['post'] = get_post( $insight );
$insight_output = $render_tag( $tag_map['rep_insight_title'] ?? null ) . $render_tag( $tag_map['rep_insight_topic'] ?? null ) . $render_tag( $tag_map['rep_insight_subtitle'] ?? null );
$checks['insight_dynamic_context'] = str_contains( $insight_output, 'Phase 8 Insight' ) && str_contains( $insight_output, 'Market' ) && str_contains( $insight_output, 'Public insight subtitle' ) && ! str_contains( $insight_output, 'PRIVATE' );
$GLOBALS['post'] = get_post( $draft );
$checks['unpublished_context_empty'] = '' === $render_tag( $tag_map['rep_property_price'] ?? null );
$GLOBALS['post'] = null;
$checks['missing_context_empty'] = '' === $render_tag( $tag_map['rep_property_price'] ?? null );
$GLOBALS['post'] = get_post( $property );

$property_query = new \WP_Query( array( 'posts_per_page' => 10, 'rep_keyword' => 'Phase 8 Property Delhi' ) );
do_action( 'elementor/query/rep_properties', $property_query );
$property_query_ids = array_map( 'intval', is_array( $property_query->get( 'post__in' ) ) ? $property_query->get( 'post__in' ) : array() );
$checks['property_query_adapter'] = 'property' === $property_query->get( 'post_type' ) && 'publish' === $property_query->get( 'post_status' ) && in_array( $property, $property_query_ids, true ) && true === $property_query->get( 'no_found_rows' );
$entity_query = new \WP_Query( array( 'posts_per_page' => 1000 ) );
do_action( 'elementor/query/rep_agencies', $entity_query );
$checks['entity_query_bounds'] = 'agency' === $entity_query->get( 'post_type' ) && 'publish' === $entity_query->get( 'post_status' ) && 100 === (int) $entity_query->get( 'posts_per_page' );

$pro_manager = new class() {
	/** @var list<object> */
	public array $actions = array();
	public function register( object $action ): void { $this->actions[] = $action; }
};
do_action( 'elementor_pro/forms/actions/register', $pro_manager );
$checks['pro_action_registered'] = count( $pro_manager->actions ) === 1 && 'realestate_platform_lead' === $pro_manager->actions[0]->get_name();

$record_factory = static function ( array $fields ): object {
	return new class( $fields ) {
		/** @param list<array<string,mixed>> $fields */
		public function __construct( private array $fields ) {}
		public function get( string $key ): mixed { return 'fields' === $key ? $this->fields : null; }
	};
};
$handler_factory = static function (): object {
	return new class() {
		/** @var list<string> */
		public array $errors = array();
		/** @var list<string> */
		public array $successes = array();
		public function add_error_message( string $message ): void { $this->errors[] = $message; }
		public function add_success_message( string $message ): void { $this->successes[] = $message; }
	};
};
$elementor_fields = array(
	array( 'id' => 'name', 'value' => 'Elementor Buyer' ),
	array( 'id' => 'email', 'value' => 'elementor-buyer@example.test' ),
	array( 'id' => 'message', 'value' => 'Elementor request.' ),
	array( 'id' => 'property_id', 'value' => (string) $property ),
	array( 'id' => 'consent', 'value' => 'on' ),
	array( 'id' => 'idempotency_key', 'value' => 'elementor-submit-1' ),
	array( 'id' => 'status', 'value' => 'converted' ),
	array( 'id' => 'agent_id', 'value' => (string) $agent_id ),
	array( 'id' => 'agency_id', 'value' => (string) $agency_id ),
	array( 'id' => 'lead_id', 'value' => (string) $core_lead_id ),
	array( 'id' => 'internal_notes', 'value' => 'PRIVATE INJECTION' ),
);
$form_record = $record_factory( $elementor_fields );
$form_handler = $handler_factory();
$pro_manager->actions[0]->run( $form_record, $form_handler );
$form_lead_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'rep_leads WHERE email=%s ORDER BY id DESC LIMIT 1', 'elementor-buyer@example.test' ) );
$form_lead = $form_lead_id > 0 ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'rep_leads WHERE id=%d', $form_lead_id ), ARRAY_A ) : array();
$form_request_count = $form_lead_id > 0 ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_lead_requests WHERE lead_id=%d', $form_lead_id ) ) : 0;
$form_event_count = $form_lead_id > 0 ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_notification_events WHERE aggregate_type=%s AND aggregate_id=%d', 'lead', $form_lead_id ) ) : 0;
$checks['pro_form_valid_submission'] = empty( $form_handler->errors ) && ! empty( $form_handler->successes ) && $form_lead_id > 0 && 'new' === (string) ( $form_lead['status'] ?? '' ) && 0 === (int) ( $form_lead['agent_id'] ?? 0 ) && 0 === (int) ( $form_lead['agency_id'] ?? 0 ) && 1 === $form_request_count;
$form_handler_duplicate = $handler_factory();
$pro_manager->actions[0]->run( $form_record, $form_handler_duplicate );
$form_lead_count_after_duplicate = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_leads WHERE email=%s', 'elementor-buyer@example.test' ) );
$form_request_count_after_duplicate = $form_lead_id > 0 ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_lead_requests WHERE lead_id=%d', $form_lead_id ) ) : 0;
$checks['pro_form_replay_single_engine'] = 1 === $form_lead_count_after_duplicate && 1 === $form_request_count_after_duplicate && 1 === $form_event_count;

$invalid_record = $record_factory(
	array(
		array( 'id' => 'name', 'value' => 'Invalid Property Buyer' ),
		array( 'id' => 'email', 'value' => 'invalid-property@example.test' ),
		array( 'id' => 'property_id', 'value' => (string) $draft ),
		array( 'id' => 'consent', 'value' => 'on' ),
	)
);
$invalid_handler = $handler_factory();
$pro_manager->actions[0]->run( $invalid_record, $invalid_handler );
$checks['pro_form_invalid_property_no_mutation'] = ! empty( $invalid_handler->errors ) && 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_leads WHERE email=%s', 'invalid-property@example.test' ) );

$no_consent_record = $record_factory(
	array(
		array( 'id' => 'name', 'value' => 'No Consent Buyer' ),
		array( 'id' => 'email', 'value' => 'no-consent@example.test' ),
		array( 'id' => 'property_id', 'value' => (string) $property ),
		array( 'id' => 'consent', 'value' => '0' ),
	)
);
$no_consent_handler = $handler_factory();
$pro_manager->actions[0]->run( $no_consent_record, $no_consent_handler );
$checks['pro_form_consent_no_mutation'] = ! empty( $no_consent_handler->errors ) && 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_leads WHERE email=%s', 'no-consent@example.test' ) );

$honeypot_record = $record_factory(
	array(
		array( 'id' => 'name', 'value' => 'Bot Buyer' ),
		array( 'id' => 'email', 'value' => 'bot@example.test' ),
		array( 'id' => 'property_id', 'value' => (string) $property ),
		array( 'id' => 'consent', 'value' => 'on' ),
		array( 'id' => 'website_url', 'value' => 'https://bot.example.test' ),
	)
);
$honeypot_handler = $handler_factory();
$pro_manager->actions[0]->run( $honeypot_record, $honeypot_handler );
$checks['pro_form_honeypot_no_mutation'] = ! empty( $honeypot_handler->errors ) && 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rep_leads WHERE email=%s', 'bot@example.test' ) );

$performance = array();
$performance_start = microtime( true );
for ( $i = 1; $i <= 100; $i++ ) {
	wp_insert_post( array( 'post_type' => 'property', 'post_status' => 'publish', 'post_title' => 'Phase 8 Performance Property ' . $i ) );
}
for ( $i = 1; $i <= 100; $i++ ) {
	wp_insert_post( array( 'post_type' => 'agent', 'post_status' => 'publish', 'post_title' => 'Phase 8 Performance Agent ' . $i, 'post_author' => $owner_id ) );
	wp_insert_post( array( 'post_type' => 'agency', 'post_status' => 'publish', 'post_title' => 'Phase 8 Performance Agency ' . $i, 'post_author' => $owner_id ) );
}
$performance['fixture_seconds'] = microtime( true ) - $performance_start;
$rebuilder->rebuild( 100 );
foreach ( array( 'property' => 100, 'agent' => 100, 'agency' => 100 ) as $entity => $per_page ) {
	$start_queries = $wpdb->num_queries;
	$start_time    = microtime( true );
	$start_memory  = memory_get_usage( true );
	$query         = new \WP_Query( array( 'post_type' => $entity, 'posts_per_page' => $per_page ) );
	if ( 'property' === $entity ) {
		do_action( 'elementor/query/rep_properties', $query );
	} else {
		$query_id = 'agency' === $entity ? 'rep_agencies' : 'rep_' . $entity . 's';
		do_action( 'elementor/query/' . $query_id, $query );
	}
	$posts = $query->get_posts();
	$performance[ $entity ] = array(
		'returned'       => count( $posts ),
		'queries'        => $wpdb->num_queries - $start_queries,
		'seconds'        => microtime( true ) - $start_time,
		'memory_delta'   => memory_get_usage( true ) - $start_memory,
		'bounded_per_page' => min( 100, $per_page ),
	);
}
$checks['performance_query_bounds'] = $performance['property']['returned'] <= 100 && $performance['agent']['returned'] <= 100 && $performance['agency']['returned'] <= 100 && $performance['property']['queries'] <= 12 && $performance['agent']['queries'] <= 8 && $performance['agency']['queries'] <= 8;

$final_workflow = $workflow_check->run();
$checks['diagnostics_after_adapter_tests'] = 'PASS' === $final_workflow->status;
$elementor_document_after = array( get_post_meta( $property, '_elementor_data', true ), get_post_meta( $property, '_elementor_page_settings', true ) );
$checks['elementor_document_integrity'] = $elementor_document_before === $elementor_document_after;

$flat = array();
foreach ( $checks as $key => $value ) {
	$flat[ $key ] = (bool) $value;
}
$not_verified = array( 'real Elementor runtime/editor', 'Elementor Pro runtime/editor', 'browser/Theme Builder/Loop Grid visual rendering', 'ACF runtime', 'remote CI', 'PHPStan at required memory budget' );
echo wp_json_encode(
	array(
		'status'       => in_array( false, $flat, true ) ? 'FAIL' : 'PASS',
		'checks'       => $flat,
		'performance'  => $performance,
		'elementor'    => array( 'actual_runtime' => $initial_availability['elementor'] ? 'AVAILABLE_BUT_NOT_RUN_IN_THIS_CONTRACT_HARNESS' : 'NOT_AVAILABLE', 'pro_runtime' => $initial_availability['elementor_pro'] ? 'AVAILABLE_BUT_NOT_RUN_IN_THIS_CONTRACT_HARNESS' : 'NOT_AVAILABLE', 'acf_runtime' => $initial_availability['acf'] ? 'AVAILABLE' : 'NOT_AVAILABLE' ),
		'not_verified' => $not_verified,
		'environment' => array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'database' => 'SQLite' ),
	),
	JSON_PRETTY_PRINT
);
