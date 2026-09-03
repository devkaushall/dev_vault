<?php
require '/wordpress/wp-load.php';
update_option( 'realestate_platform_settings_general', array( 'operating_mode' => 'standalone' ) );
$b = \Mayfair\RealEstatePlatform\Core\Bootstrap::instance(); $b->services()->get( 'content' )->initialize(); do_action( 'rest_api_init' );
$service = $b->services()->get( 'profiles' ); $admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID; wp_set_current_user( $admin );
$a = wp_create_user( 'phase6-a', 'pass', 'p6a@example.test' ); $u = get_user_by( 'id', $a ); foreach ( array( 'edit_agents', 'edit_agencies', 'publish_agents', 'publish_agencies', 'edit_properties', 'edit_published_properties' ) as $cap ) { $u->add_cap( $cap ); }
$b_id = wp_create_user( 'phase6-b', 'pass', 'p6b@example.test' ); $ub = get_user_by( 'id', $b_id ); foreach ( array( 'edit_agents', 'edit_agencies', 'publish_agents', 'publish_agencies', 'edit_properties', 'edit_published_properties' ) as $cap ) { $ub->add_cap( $cap ); }
function p6req( string $method, string $route, array $params = array() ): \WP_REST_Response { $r = new \WP_REST_Request( $method, $route ); foreach ( $params as $k => $v ) { $r->set_param( $k, $v ); } return rest_do_request( $r ); }
function p6ok( \WP_REST_Response $r ): bool { return $r->get_status() >= 200 && $r->get_status() < 300; }
function p6bad( \WP_REST_Response $r ): bool { return $r->get_status() >= 400 && $r->get_status() < 500; }
$c = array(); wp_set_current_user( $a );
$agency = $service->create( 'agency', array( 'title' => 'Agency <script>x</script>', 'content' => '<p>Office</p><script>x</script>', 'public_phone' => '123', 'public_email' => 'public@example.test', 'office_address' => 'Delhi', 'private_notes' => 'secret' ), $a );
$agent = $service->create( 'agent', array( 'title' => 'Agent A', 'content' => '<b>Profile</b>', 'public_phone' => '456', 'public_email' => 'agent@example.test', 'private_notes' => 'never public' ), $a );
if ( is_wp_error( $agency ) || is_wp_error( $agent ) ) { echo wp_json_encode( array( 'agency_error' => is_wp_error( $agency ) ? $agency->get_error_code() : null, 'agent_error' => is_wp_error( $agent ) ? $agent->get_error_code() : null, 'caps' => $u->allcaps, 'types' => array( post_type_exists( 'agent' ), post_type_exists( 'agency' ) ) ) ); exit; }
$c['agency_create'] = is_array( $agency ); $c['agent_create'] = is_array( $agent );
$c['agency_assign'] = true === $service->assignAgency( $agent['id'], $agency['id'], $a );
$property = wp_insert_post( array( 'post_type' => 'property', 'post_status' => 'publish', 'post_title' => 'Assigned', 'post_author' => $a ) );
$c['property_assign'] = true === $service->assignProperty( $property, $agent['id'], $agency['id'], $a );
$c['relationships'] = (int) get_post_meta( $agent['id'], 'rep_agency_id', true ) === $agency['id'] && (int) get_post_meta( $property, 'rep_agent_id', true ) === $agent['id'];
$other_agency = $service->create( 'agency', array( 'title' => 'Unrelated Agency' ), $a );
$before_mismatch = serialize( array( get_post_meta( $property, 'rep_agent_id', true ), get_post_meta( $property, 'rep_agency_id', true ) ) );
$mismatch = is_array( $other_agency ) ? $service->assignProperty( $property, $agent['id'], $other_agency['id'], $a ) : false;
$c['relationship_consistency'] = is_array( $other_agency ) && is_wp_error( $mismatch ) && $before_mismatch === serialize( array( get_post_meta( $property, 'rep_agent_id', true ), get_post_meta( $property, 'rep_agency_id', true ) ) );
if ( is_array( $other_agency ) ) {
    $service->delete( 'agency', $other_agency['id'], $a );
}
$c['strict_id_rejection'] = 0 === \Mayfair\RealEstatePlatform\Security\StrictId::parse( -1 ) && 0 === \Mayfair\RealEstatePlatform\Security\StrictId::parse( '-1' ) && 0 === \Mayfair\RealEstatePlatform\Security\StrictId::parse( array( 1 ) );
$c['agency_delete_blocked'] = is_wp_error( $service->delete( 'agency', $agency['id'], $a ) );
$published_agency = $service->update( 'agency', $agency['id'], array( 'status' => 'publish' ), $a ); $published_agent = $service->update( 'agent', $agent['id'], array( 'status' => 'publish' ), $a );
$c['lifecycle'] = 'publish' === $published_agency['status'] && 'publish' === $published_agent['status'];
$public = $service->get( 'agent', $agent['id'] ); $c['public_allowlist'] = is_array( $public ) && ! isset( $public['private_notes'], $public['status'] ) && ! str_contains( wp_json_encode( $public ), 'never public' );
$c['public_xss_safe'] = ! str_contains( $service->get( 'agency', $agency['id'] )['title'], '<' ) && ! str_contains( $service->get( 'agency', $agency['id'] )['content'], '<script' );
wp_set_current_user( $b_id ); $before = serialize( array( get_post( $agent['id'] ), get_post_meta( $agent['id'] ), get_post( $agency['id'] ), get_post_meta( $agency['id'] ) ) );
$c['agent_idor_update'] = is_wp_error( $service->update( 'agent', $agent['id'], array( 'title' => 'stolen' ), $b_id ) );
$c['agent_idor_delete'] = is_wp_error( $service->delete( 'agent', $agent['id'], $b_id ) );
$c['agency_idor_update'] = is_wp_error( $service->update( 'agency', $agency['id'], array( 'title' => 'stolen' ), $b_id ) );
$c['agency_idor_delete'] = is_wp_error( $service->delete( 'agency', $agency['id'], $b_id ) );
$c['assignment_idor'] = is_wp_error( $service->assignAgency( $agent['id'], $agency['id'], $b_id ) );
$c['idor_no_mutation'] = $before === serialize( array( get_post( $agent['id'] ), get_post_meta( $agent['id'] ), get_post( $agency['id'] ), get_post_meta( $agency['id'] ) ) );
wp_set_current_user( 0 ); $c['anonymous_public'] = p6ok( p6req( 'GET', '/realestate-platform/v1/agents/' . $agent['id'] ) ); $c['anonymous_write_blocked'] = p6bad( p6req( 'POST', '/realestate-platform/v1/agents', array( 'title' => 'No' ) ) );
$invalid_routes = array( '/realestate-platform/v1/agents/0', '/realestate-platform/v1/agents/-1', '/realestate-platform/v1/agents/abc' ); $invalid_ok = true; foreach ( $invalid_routes as $invalid_route ) { $invalid_ok = $invalid_ok && p6bad( p6req( 'GET', $invalid_route ) ); } $c['invalid_id'] = $invalid_ok; $c['numeric_string_id'] = p6ok( p6req( 'GET', '/realestate-platform/v1/agents/' . $agent['id'], array( 'id' => (string) $agent['id'] ) ) ); $c['pagination_bound'] = p6bad( p6req( 'GET', '/realestate-platform/v1/agents', array( 'per_page' => 101 ) ) );
wp_set_current_user( $a ); $c['rest_list'] = p6ok( p6req( 'GET', '/realestate-platform/v1/agents' ) );
$rest_created = p6req( 'POST', '/realestate-platform/v1/agents', array( 'title' => 'REST Agent', 'private_notes' => 'private' ) ); $c['rest_create'] = p6ok( $rest_created ); $rid = $rest_created->get_data()['id'];
$c['rest_update'] = p6ok( p6req( 'PUT', '/realestate-platform/v1/agents/' . $rid, array( 'id' => $rid, 'title' => 'REST Updated' ) ) ); $c['rest_delete'] = p6ok( p6req( 'DELETE', '/realestate-platform/v1/agents/' . $rid, array( 'id' => $rid ) ) );
$c['rest_agency_assignment'] = p6ok( p6req( 'PUT', '/realestate-platform/v1/agents/' . $agent['id'] . '/agency', array( 'id' => $agent['id'], 'agency_id' => $agency['id'] ) ) );
$c['rest_property_assignment'] = p6ok( p6req( 'PUT', '/realestate-platform/v1/properties/' . $property . '/profile', array( 'id' => $property, 'agent_id' => $agent['id'], 'agency_id' => $agency['id'] ) ) );
$c['rest_agency_removal'] = p6ok( p6req( 'DELETE', '/realestate-platform/v1/agents/' . $agent['id'] . '/agency', array( 'id' => $agent['id'] ) ) );
$c['rest_agency_reassignment'] = p6ok( p6req( 'PUT', '/realestate-platform/v1/agents/' . $agent['id'] . '/agency', array( 'id' => $agent['id'], 'agency_id' => $agency['id'] ) ) );
$c['invalid_relationship'] = is_wp_error( $service->assignAgency( $agent['id'], $property, $a ) );
$privacy = new \Mayfair\RealEstatePlatform\Privacy\PrivacyFoundation(); $export = $privacy->export( 'p6a@example.test' ); $export_json = wp_json_encode( $export ); $c['privacy_export'] = str_contains( $export_json, 'never public' ) && str_contains( $export_json, 'agent@example.test' ); $erase = $privacy->erase( 'p6a@example.test' ); $c['privacy_erase'] = $erase['items_removed'] && '' === get_post_meta( $agent['id'], 'rep_private_notes', true ) && '' === get_post_meta( $agent['id'], 'rep_public_email', true );
$c['relationship_remove'] = true === $service->removeAgency( $agent['id'], $a ) && 0 === (int) get_post_meta( $agent['id'], 'rep_agency_id', true );
$c['agency_delete_blocked_by_property'] = is_wp_error( $service->delete( 'agency', $agency['id'], $a ) );
$c['rest_property_removal'] = p6ok( p6req( 'DELETE', '/realestate-platform/v1/properties/' . $property . '/profile', array( 'id' => $property ) ) );
$c['property_relationship_remove'] = 0 === (int) get_post_meta( $property, 'rep_agent_id', true ) && 0 === (int) get_post_meta( $property, 'rep_agency_id', true );
$c['agency_delete_after_remove'] = true === $service->delete( 'agency', $agency['id'], $a );
$c['service_singleton'] = $service === $b->services()->get( 'profiles' );
require_once ABSPATH . 'wp-admin/includes/user.php'; $delete_user = wp_create_user( 'phase6-delete', 'pass', 'p6delete@example.test' ); $du = get_user_by( 'id', $delete_user ); $du->add_cap( 'edit_agents' ); $du->add_cap( 'edit_agencies' ); wp_set_current_user( $delete_user ); $delete_agent = $service->create( 'agent', array( 'title' => 'Delete Agent' ), $delete_user ); $delete_agency = $service->create( 'agency', array( 'title' => 'Delete Agency' ), $delete_user ); wp_set_current_user( $admin ); wp_delete_user( $delete_user ); $c['privacy_user_delete'] = null === get_post( $delete_agent['id'] ) && null === get_post( $delete_agency['id'] );
$perf = array(); foreach ( array( 'agent', 'agency' ) as $type ) { $m0 = memory_get_usage(); $t0 = microtime( true ); for ( $i = 0; $i < 100; $i++ ) { wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => 'Perf ' . $i, 'post_author' => $a ) ); } $fixture_seconds = microtime( true ) - $t0; $perf[ $type ] = array( 'fixture_seconds' => $fixture_seconds ); foreach ( array( 10, 100 ) as $scale ) { $q0 = $GLOBALS['wpdb']->num_queries; $t0 = microtime( true ); $list = $service->listPublic( $type, 1, $scale ); $perf[ $type ][ (string) $scale ] = array( 'listing_seconds' => microtime( true ) - $t0, 'listing_queries' => $GLOBALS['wpdb']->num_queries - $q0, 'memory_delta' => memory_get_usage() - $m0, 'returned' => count( $list['items'] ) ); } } $c['performance_smoke'] = 10 === $perf['agent']['10']['returned'] && 100 === $perf['agent']['100']['returned'] && 10 === $perf['agency']['10']['returned'] && 100 === $perf['agency']['100']['returned'];
$status = in_array( false, $c, true ) ? 'FAIL' : 'PASS'; echo wp_json_encode( array( 'status' => $status, 'checks' => $c, 'performance' => $perf, 'environment' => array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'database' => 'SQLite' ) ), JSON_PRETTY_PRINT );
