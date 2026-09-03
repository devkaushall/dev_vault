<?php
/**
 * Phase 9 executable contract runner.
 *
 * Runs inside a WordPress Playground or equivalent WordPress runtime. It deliberately
 * uses only fixture data and never represents Mayfair compatibility as verified.
 */
require '/wordpress/wp-load.php';

$bootstrap = \Mayfair\RealEstatePlatform\Core\Bootstrap::instance();
$services  = $bootstrap->services();
update_option( 'realestate_platform_settings_general', array( 'operating_mode' => 'standalone' ), false );
$content = $services->get( 'content' );
$content->initialize();
do_action( 'init' );

$checks = array();
$owner_id = wp_create_user( 'phase9-owner', 'phase9-pass', 'phase9-owner@example.test' );
if ( is_wp_error( $owner_id ) ) {
	$owner = get_user_by( 'login', 'phase9-owner' );
	$owner_id = $owner instanceof \WP_User ? (int) $owner->ID : 0;
}
$owner = $owner_id > 0 ? get_user_by( 'id', $owner_id ) : false;
if ( $owner instanceof \WP_User ) {
	foreach ( array( 'manage_realestate_imports', 'manage_realestate_exports', 'manage_realestate', 'edit_properties', 'publish_properties', 'edit_projects', 'publish_projects', 'edit_insights', 'publish_insights', 'edit_agents', 'publish_agents', 'edit_agencies', 'publish_agencies', 'edit_others_agents', 'edit_others_agencies', 'edit_others_properties' ) as $capability ) {
		$owner->add_cap( $capability );
	}
}
wp_set_current_user( $owner_id );

$imports = $services->get( 'imports' );
$exports = $services->get( 'exports' );
$schema  = new \Mayfair\RealEstatePlatform\ImportExport\SchemaCatalog( new \Mayfair\RealEstatePlatform\Fields\FieldRegistry(), new \Mayfair\RealEstatePlatform\Classification\TaxonomyRegistry() );
$checks['services_composed'] = $imports instanceof \Mayfair\RealEstatePlatform\ImportExport\ImportService && $exports instanceof \Mayfair\RealEstatePlatform\ImportExport\ExportService && in_array( 'reference', $schema->columns( 'property' ), true );

$term = wp_insert_term( 'Apartment Phase 9', 'property_type' );
$term_id = is_array( $term ) ? (int) $term['term_id'] : 0;
if ( $term_id < 1 ) {
	$existing = term_exists( 'Apartment Phase 9', 'property_type' );
	$term_id = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
}
$source = wp_json_encode( array( 'rows' => array( array( 'slug' => 'phase9-deterministic-home', 'reference' => 'P9-001', 'title' => 'Phase 9 Deterministic Home', 'price' => 12500000, 'status' => 'draft', 'tax_property_type' => array( $term_id ) ) ) ) );
$before = (int) wp_count_posts( 'property' )->draft;
$dry = $imports->runContent( 'dry_run', 'property', 'json', (string) $source, array(), $owner_id );
$after_dry = (int) wp_count_posts( 'property' )->draft;
$checks['dry_run_zero_mutation'] = is_array( $dry ) && 'NONE' === $dry['mutation'] && 1 === $dry['counts']['create'] && $before === $after_dry && 'PASS' === $dry['status'];
$run = $imports->runContent( 'import', 'property', 'json', (string) $source, array(), $owner_id );
$property_id = is_array( $run ) && isset( $run['rows'][0]['id'] ) ? (int) $run['rows'][0]['id'] : 0;
$checks['create_and_taxonomy'] = is_array( $run ) && 'PASS' === $run['status'] && $property_id > 0 && (float) get_post_meta( $property_id, 'rep_price', true ) === 12500000.0 && in_array( $term_id, array_map( 'intval', wp_get_post_terms( $property_id, 'property_type', array( 'fields' => 'ids' ) ) ), true );

$update_source = wp_json_encode( array( 'rows' => array( array( 'slug' => 'phase9-deterministic-home', 'reference' => 'P9-001', 'title' => 'Phase 9 Updated Home', 'price' => 13000000, 'status' => 'draft' ) ) ) );
$update = $imports->runContent( 'import', 'property', 'json', (string) $update_source, array( 'strategy' => 'upsert' ), $owner_id );
$property_after = is_array( $update ) && isset( $update['rows'][0]['id'] ) ? (int) $update['rows'][0]['id'] : 0;
$checks['deterministic_upsert'] = is_array( $update ) && 'PASS' === $update['status'] && $property_after === $property_id && 1 === (int) $update['counts']['update'] && 'Phase 9 Updated Home' === get_post_field( 'post_title', $property_id ) && 13000000.0 === (float) get_post_meta( $property_id, 'rep_price', true );
$conflict = $imports->runContent( 'dry_run', 'property', 'json', (string) $source, array( 'strategy' => 'create_only' ), $owner_id );
$checks['create_only_conflict_visible'] = is_array( $conflict ) && 1 === $conflict['counts']['conflict'] && 'conflict' === $conflict['rows'][0]['status'];

$private_source = wp_json_encode( array( 'rows' => array( array( 'slug' => 'phase9-private-rejected', 'title' => 'Should Not Exist', 'private_notes' => 'PRIVATE' ) ) ) );
$private_result = $imports->runContent( 'import', 'property', 'json', (string) $private_source, array(), $owner_id );
$checks['private_field_rejected_without_mutation'] = is_array( $private_result ) && 'FAIL' === $private_result['status'] && 0 === (int) $private_result['counts']['imported'] && ! get_page_by_path( 'phase9-private-rejected', OBJECT, 'property' );

$missing_name = 'Phase 9 Opt-In Term';
$missing_source = wp_json_encode( array( 'rows' => array( array( 'slug' => 'phase9-missing-term', 'title' => 'Phase 9 Missing Term', 'tax_property_type' => array( $missing_name ) ) ) ) );
$missing_before = term_exists( $missing_name, 'property_type' );
$missing_dry = $imports->runContent( 'dry_run', 'property', 'json', (string) $missing_source, array( 'create_missing_terms' => true ), $owner_id );
$checks['missing_term_dry_run_no_mutation'] = is_array( $missing_dry ) && ! $missing_before && ! term_exists( $missing_name, 'property_type' ) && $missing_dry['counts']['create'] === 1;
$missing_run = $imports->runContent( 'import', 'property', 'json', (string) $missing_source, array( 'create_missing_terms' => true ), $owner_id );
$checks['missing_term_execution_opt_in'] = is_array( $missing_run ) && 'PASS' === $missing_run['status'] && false !== term_exists( $missing_name, 'property_type' );

$profiles = $services->get( 'profiles' );
$agency = $profiles->create( 'agency', array( 'title' => 'Phase 9 Agency' ), $owner_id );
$agency_id = is_array( $agency ) ? (int) $agency['id'] : 0;
if ( $agency_id > 0 ) {
	wp_update_post( array( 'ID' => $agency_id, 'post_status' => 'publish' ) );
}
$agent = $profiles->create( 'agent', array( 'title' => 'Phase 9 Agent' ), $owner_id );
$agent_id = is_array( $agent ) ? (int) $agent['id'] : 0;
if ( $agent_id > 0 && $agency_id > 0 ) {
	$profiles->assignAgency( $agent_id, $agency_id, $owner_id );
	wp_update_post( array( 'ID' => $agent_id, 'post_status' => 'publish' ) );
}
$relation_source = wp_json_encode( array( 'rows' => array( array( 'slug' => 'phase9-related-home', 'title' => 'Phase 9 Related Home', 'relationship_agent_id' => $agent_id, 'relationship_agency_id' => $agency_id ) ) ) );
$relation = $imports->runContent( 'import', 'property', 'json', (string) $relation_source, array(), $owner_id );
$relation_id = is_array( $relation ) && isset( $relation['rows'][0]['id'] ) ? (int) $relation['rows'][0]['id'] : 0;
$checks['supported_relationship_consistency'] = is_array( $relation ) && 'PASS' === $relation['status'] && $relation_id > 0 && (int) get_post_meta( $relation_id, 'rep_agent_id', true ) === $agent_id && (int) get_post_meta( $relation_id, 'rep_agency_id', true ) === $agency_id;

update_post_meta( $property_id, 'rep_private_notes', 'PRIVATE MUST NOT EXPORT' );
wp_update_post( array( 'ID' => $property_id, 'post_title' => '=Phase 9 Formula' ) );
$export_options = array( 'limit' => 100, 'include_nonpublic' => true );
$csv_one = $exports->content( 'property', 'csv', $owner_id, $export_options );
$csv_two = $exports->content( 'property', 'csv', $owner_id, $export_options );
$json_one = $exports->content( 'property', 'json', $owner_id, $export_options );
$json_two = $exports->content( 'property', 'json', $owner_id, $export_options );
$checks['deterministic_exports'] = is_string( $csv_one ) && $csv_one === $csv_two && is_string( $json_one ) && $json_one === $json_two;
$checks['export_formula_and_privacy'] = is_string( $csv_one ) && str_contains( $csv_one, "'=Phase 9 Formula" ) && ! str_contains( $csv_one, 'PRIVATE MUST NOT EXPORT' ) && ! str_contains( $csv_one, 'rep_private_notes' ) && is_string( $json_one ) && ! str_contains( $json_one, 'PRIVATE MUST NOT EXPORT' );

$remote_source = wp_json_encode( array( 'rows' => array( array( 'slug' => 'phase9-unsafe-media', 'title' => 'Unsafe Media', 'featured_image_url' => 'https://127.0.0.1/private.jpg' ) ) ) );
$remote = $imports->runContent( 'dry_run', 'property', 'json', (string) $remote_source, array( 'allow_remote_media' => true ), $owner_id );
$checks['unsafe_remote_media_not_verified'] = is_array( $remote ) && 0 === (int) $remote['counts']['imported'] && isset( $remote['rows'][0]['errors'][0] ) && str_contains( $remote['rows'][0]['errors'][0], 'NOT VERIFIED' );

$bench = array();
foreach ( array( 10, 100, 1000 ) as $size ) {
	$rows = array();
	for ( $index = 1; $index <= $size; ++$index ) {
		$rows[] = array( 'slug' => 'phase9-bench-' . $size . '-' . $index, 'reference' => 'P9-B-' . $size . '-' . $index, 'title' => 'Phase 9 Benchmark ' . $index, 'price' => $index );
	}
	$memory_before = memory_get_usage( true );
	$peak_before   = memory_get_peak_usage( true );
	$started       = microtime( true );
	$result        = $imports->runContent( 'dry_run', 'property', 'json', (string) wp_json_encode( array( 'rows' => $rows ) ), array(), $owner_id );
	$bench[ (string) $size ] = array(
		'seconds'      => round( microtime( true ) - $started, 4 ),
		'rows'         => is_array( $result ) ? $result['counts']['total'] : 0,
		'status'       => is_array( $result ) ? $result['status'] : 'FAIL',
		'memory_delta' => max( 0, memory_get_usage( true ) - $memory_before ),
		'peak_delta'   => max( 0, memory_get_peak_usage( true ) - $peak_before ),
	);
}
$checks['bounded_performance_runs'] = $bench['10']['rows'] === 10 && $bench['100']['rows'] === 100 && $bench['1000']['rows'] === 1000 && $bench['1000']['seconds'] < 30;

$checks['no_phase10'] = ! file_exists( ABSPATH . 'wp-content/plugins/realestate-platform/src/Phase10' );
$status = in_array( false, $checks, true ) ? 'FAIL' : 'PASS';
echo wp_json_encode( array( 'status' => $status, 'runtime' => 'WordPress runtime contract checks; external integrations remain NOT VERIFIED.', 'checks' => $checks, 'benchmarks' => $bench, 'memory' => array( 'peak_bytes' => memory_get_peak_usage( true ), 'memory_limit' => ini_get( 'memory_limit' ) ) ), JSON_PRETTY_PRINT );
if ( 'FAIL' === $status ) {
	exit( 1 );
}
