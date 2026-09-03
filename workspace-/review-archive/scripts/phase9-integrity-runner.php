<?php
/** Phase 9 data-integrity and restartability contract runner; SQLite fixture only. */
require '/wordpress/wp-load.php';

$bootstrap = \Mayfair\RealEstatePlatform\Core\Bootstrap::instance();
$services  = $bootstrap->services();
$services->get( 'content' )->initialize();
$imports = $services->get( 'imports' );
$profiles = $services->get( 'profiles' );
$checks = array();
$record = static function ( string $name, bool $value ) use ( &$checks ): void {
	$checks[ $name ] = $value;
};

$owner_id = 1;
$owner = get_user_by( 'id', $owner_id );
if ( $owner instanceof \WP_User ) {
	foreach ( array( 'manage_realestate', 'manage_realestate_imports', 'edit_posts', 'publish_posts', 'edit_properties', 'edit_property', 'publish_properties', 'edit_agents', 'edit_agent', 'publish_agents', 'edit_agencies', 'edit_agency', 'publish_agencies', 'edit_others_agents', 'edit_others_agencies', 'edit_others_properties' ) as $capability ) {
		$owner->add_cap( $capability );
	}
}
wp_set_current_user( $owner_id );

$before_count = (int) wp_count_posts( 'property' )->publish + (int) wp_count_posts( 'property' )->draft;
$batch = wp_json_encode(
	array(
		'rows' => array(
			array( 'slug' => 'phase9-integrity-valid', 'reference' => 'P9-INTEGRITY', 'title' => 'Should be skipped' ),
			array( 'slug' => 'phase9-integrity-invalid', 'title' => 'Unknown input', 'protected_meta' => 'must reject' ),
		),
	)
);
$batch_result = $imports->runContent( 'import', 'property', 'json', (string) $batch, array(), $owner_id );
$after_count = (int) wp_count_posts( 'property' )->publish + (int) wp_count_posts( 'property' )->draft;
$record( 'invalid_batch_has_no_partial_mutation', is_array( $batch_result ) && 'FAIL' === $batch_result['status'] && 'NONE' === $batch_result['mutation'] && $before_count === $after_count && ! get_page_by_path( 'phase9-integrity-valid', OBJECT, 'property' ) );
$record( 'invalid_batch_reports_skipped_rows', is_array( $batch_result ) && 1 === (int) $batch_result['counts']['invalid'] && 1 === (int) $batch_result['counts']['skipped'] );

$source = wp_json_encode( array( 'rows' => array( array( 'slug' => 'phase9-integrity-retry', 'reference' => 'P9-RETRY', 'title' => 'Retry source', 'price' => 100 ) ) ) );
$first = $imports->runContent( 'import', 'property', 'json', (string) $source, array(), $owner_id );
$first_id = is_array( $first ) && isset( $first['rows'][0]['id'] ) ? (int) $first['rows'][0]['id'] : 0;
$first_snapshot = $first_id > 0 ? array( get_post( $first_id )->post_title, get_post_meta( $first_id, 'rep_price', true ) ) : array();
$second = $imports->runContent( 'import', 'property', 'json', (string) $source, array(), $owner_id );
$second_id = is_array( $second ) && isset( $second['rows'][0]['id'] ) ? (int) $second['rows'][0]['id'] : 0;
$record( 'deterministic_retry_reuses_identity', $first_id > 0 && $first_id === $second_id && 1 === (int) ( $second['counts']['update'] ?? 0 ) );
$record( 'deterministic_retry_preserves_content', $first_id > 0 && $first_snapshot === array( get_post( $first_id )->post_title, get_post_meta( $first_id, 'rep_price', true ) ) );

$agency = $profiles->create( 'agency', array( 'title' => 'Integrity Agency' ), $owner_id );
$agency_id = is_array( $agency ) ? (int) $agency['id'] : 0;
if ( $agency_id > 0 ) {
	wp_update_post( array( 'ID' => $agency_id, 'post_status' => 'publish' ) );
}
$agent = $profiles->create( 'agent', array( 'title' => 'Integrity Agent' ), $owner_id );
$agent_id = is_array( $agent ) ? (int) $agent['id'] : 0;
if ( $agent_id > 0 && $agency_id > 0 ) {
	$profiles->assignAgency( $agent_id, $agency_id, $owner_id );
	wp_update_post( array( 'ID' => $agent_id, 'post_status' => 'publish' ) );
}
$relation_source = wp_json_encode( array( 'rows' => array( array( 'slug' => 'phase9-integrity-relation', 'title' => 'Integrity relation', 'relationship_agent_id' => $agent_id, 'relationship_agency_id' => $agency_id ) ) ) );
$relation = $imports->runContent( 'import', 'property', 'json', (string) $relation_source, array(), $owner_id );
$relation_id = is_array( $relation ) && isset( $relation['rows'][0]['id'] ) ? (int) $relation['rows'][0]['id'] : 0;
$record( 'relationship_targets_are_consistent', is_array( $relation ) && 'PASS' === $relation['status'] && $relation_id > 0 && (int) get_post_meta( $relation_id, 'rep_agent_id', true ) === $agent_id && (int) get_post_meta( $relation_id, 'rep_agency_id', true ) === $agency_id );

$operational_tables = array( 'rep_leads', 'rep_lead_requests', 'rep_site_visits', 'rep_notification_events' );
$before_operational = array();
$after_operational = array();
global $wpdb;
foreach ( $operational_tables as $table ) {
	$name = $wpdb->prefix . $table;
	$before_operational[ $table ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$name}" );
}
$imports->runContent( 'dry_run', 'property', 'json', (string) $source, array(), $owner_id );
foreach ( $operational_tables as $table ) {
	$name = $wpdb->prefix . $table;
	$after_operational[ $table ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$name}" );
}
$record( 'dry_run_does_not_touch_operational_tables', $before_operational === $after_operational );

$failed = array_filter( $checks, static fn ( bool $value ): bool => ! $value );
echo wp_json_encode(
	array(
		'status'      => array() === $failed ? 'PASS' : 'FAIL',
		'checks'      => $checks,
		'failed'      => array_keys( $failed ),
		'environment' => array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'database' => 'SQLite' ),
		'recovery_model' => 'Complete-plan preflight plus deterministic identity rerun; no durable process-death checkpoint exists in schema 004.',
	),
	JSON_PRETTY_PRINT
);
