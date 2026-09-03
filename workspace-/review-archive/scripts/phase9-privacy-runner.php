<?php
/** Phase 9 export privacy boundary runner; local WordPress/SQLite fixture only. */
require '/wordpress/wp-load.php';

$bootstrap = \Mayfair\RealEstatePlatform\Core\Bootstrap::instance();
$services  = $bootstrap->services();
$services->get( 'content' )->initialize();
$imports = $services->get( 'imports' );
$exports = $services->get( 'exports' );
$schema  = new \Mayfair\RealEstatePlatform\ImportExport\SchemaCatalog( new \Mayfair\RealEstatePlatform\Fields\FieldRegistry(), new \Mayfair\RealEstatePlatform\Classification\TaxonomyRegistry() );
$checks  = array();
$record  = static function ( string $name, bool $value ) use ( &$checks ): void {
	$checks[ $name ] = $value;
};

$owner_id = wp_create_user( 'phase9-privacy-owner', 'phase9-privacy-pass', 'phase9-privacy-owner@example.test' );
if ( is_wp_error( $owner_id ) ) {
	$owner = get_user_by( 'login', 'phase9-privacy-owner' );
	$owner_id = $owner instanceof \WP_User ? (int) $owner->ID : 0;
}
$owner = get_user_by( 'id', $owner_id );
if ( $owner instanceof \WP_User ) {
	foreach ( array( 'manage_realestate', 'manage_realestate_imports', 'manage_realestate_exports', 'edit_properties', 'publish_properties', 'edit_projects', 'publish_projects', 'edit_insights', 'publish_insights', 'edit_agents', 'publish_agents', 'edit_agencies', 'publish_agencies' ) as $capability ) {
		$owner->add_cap( $capability );
	}
}
wp_set_current_user( $owner_id );

$property = wp_insert_post( array( 'post_type' => 'property', 'post_title' => 'Privacy fixture', 'post_status' => 'publish', 'post_author' => $owner_id ) );
$draft    = wp_insert_post( array( 'post_type' => 'property', 'post_title' => 'Nonpublic fixture', 'post_status' => 'draft', 'post_author' => $owner_id ) );
$agent    = wp_insert_post( array( 'post_type' => 'agent', 'post_title' => 'Agent privacy fixture', 'post_status' => 'publish', 'post_author' => $owner_id ) );
update_post_meta( $property, 'rep_private_notes', 'PRIVATE LEAD REQUEST SECRET' );
update_post_meta( $property, 'rep_private_email', 'private@example.test' );
update_post_meta( $agent, 'rep_private_notes', 'AGENT PRIVATE SECRET' );

$columns = array();
foreach ( $schema->entities() as $entity ) {
	$columns[ $entity ] = $schema->exportColumns( $entity );
}
$serialized = array();
foreach ( $schema->entities() as $entity ) {
	$serialized[ $entity ] = $exports->content( $entity, 'json', $owner_id, array( 'limit' => 100, 'include_nonpublic' => true ) );
}
$combined = wp_json_encode( $serialized );
$forbidden = '/private_notes|private_email|lead|request|site.?visit|notification|authentication|password|credential|security|rep_private/i';
$record( 'catalog_excludes_private_and_operational_columns', ! preg_match( $forbidden, wp_json_encode( $columns ) ) );
$record( 'public_exports_are_strings', ! in_array( false, array_map( 'is_string', $serialized ), true ) );
$record( 'private_editorial_metadata_excluded', ! preg_match( $forbidden, (string) $combined ) );
$record( 'draft_content_can_be_explicitly_reviewed', is_string( $serialized['property'] ) && str_contains( $serialized['property'], 'Nonpublic fixture' ) );
$default = $exports->content( 'property', 'json', $owner_id, array( 'limit' => 100 ) );
$record( 'default_export_is_public_only', is_string( $default ) && ! str_contains( $default, 'Nonpublic fixture' ) );

$limited_id = wp_create_user( 'phase9-privacy-limited', 'phase9-privacy-pass', 'phase9-privacy-limited@example.test' );
$limited = get_user_by( 'id', $limited_id );
if ( $limited instanceof \WP_User ) {
	foreach ( array( 'manage_realestate_exports', 'edit_properties' ) as $capability ) {
		$limited->add_cap( $capability );
	}
}
wp_set_current_user( $limited_id );
$limited_result = $exports->content( 'property', 'json', (int) $limited_id, array( 'include_nonpublic' => true ) );
$record( 'nonpublic_export_requires_manage_capability', is_wp_error( $limited_result ) && 'export_forbidden' === $limited_result->get_error_code() );
wp_set_current_user( $owner_id );

$import_private = $imports->runContent( 'dry_run', 'property', 'json', '{"rows":[{"slug":"phase9-privacy-import-private","title":"Private input","private_notes":"secret"}]}', array(), $owner_id );
$record( 'private_import_input_rejected', is_array( $import_private ) && 'FAIL' === $import_private['status'] && ! get_page_by_path( 'phase9-privacy-import-private', OBJECT, 'property' ) );

$failed = array_filter( $checks, static fn ( bool $value ): bool => ! $value );
echo wp_json_encode(
	array(
		'status'      => array() === $failed ? 'PASS' : 'FAIL',
		'checks'      => $checks,
		'failed'      => array_keys( $failed ),
		'environment' => array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'database' => 'SQLite' ),
		'privacy_boundary' => 'Editorial public fields only; Phase 7 private workflow tables and user data are not export targets.',
	),
	JSON_PRETTY_PRINT
);
