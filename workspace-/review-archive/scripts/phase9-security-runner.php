<?php
/**
 * Phase 9 security and privacy boundary runner.
 *
 * This is a local WordPress/SQLite contract fixture. It does not represent
 * native database, real provider, or production security verification.
 */
require '/wordpress/wp-load.php';

$bootstrap = \Mayfair\RealEstatePlatform\Core\Bootstrap::instance();
$services  = $bootstrap->services();
$services->get( 'content' )->initialize();
$imports = $services->get( 'imports' );
$exports = $services->get( 'exports' );
$checks  = array();
$check   = static function ( string $name, bool $value ) use ( &$checks ): void {
	$checks[ $name ] = $value;
};

$owner_id = wp_create_user( 'phase9-security-owner', 'phase9-security-pass', 'phase9-security-owner@example.test' );
if ( is_wp_error( $owner_id ) ) {
	$owner = get_user_by( 'login', 'phase9-security-owner' );
	$owner_id = $owner instanceof \WP_User ? (int) $owner->ID : 0;
}
$owner = $owner_id > 0 ? get_user_by( 'id', $owner_id ) : false;
if ( $owner instanceof \WP_User ) {
	foreach ( array( 'manage_realestate', 'manage_realestate_imports', 'manage_realestate_exports', 'edit_properties', 'publish_properties', 'edit_projects', 'publish_projects', 'edit_insights', 'publish_insights', 'edit_agents', 'publish_agents', 'edit_agencies', 'publish_agencies', 'edit_others_properties', 'edit_others_agents', 'edit_others_agencies' ) as $capability ) {
		$owner->add_cap( $capability );
	}
}

$guest_id = wp_create_user( 'phase9-security-guest', 'phase9-security-pass', 'phase9-security-guest@example.test' );
wp_set_current_user( $guest_id );
$guest_import = $imports->runContent( 'dry_run', 'property', 'json', '{"rows":[{"slug":"phase9-guest-denied","title":"Denied"}]}', array(), (int) $guest_id );
$guest_export = $exports->content( 'property', 'json', (int) $guest_id );
$check( 'import_capability_boundary', is_wp_error( $guest_import ) && 'import_forbidden' === $guest_import->get_error_code() );
$check( 'export_capability_boundary', is_wp_error( $guest_export ) && 'export_forbidden' === $guest_export->get_error_code() );
$check( 'guest_no_mutation', ! get_page_by_path( 'phase9-guest-denied', OBJECT, 'property' ) );
wp_set_current_user( $owner_id );
$actor_mismatch = $imports->runContent( 'dry_run', 'property', 'json', '{"rows":[{"slug":"phase9-actor-mismatch","title":"Denied"}]}', array(), (int) $guest_id );
$check( 'actor_identity_binding', is_wp_error( $actor_mismatch ) && 'import_forbidden' === $actor_mismatch->get_error_code() );

wp_set_current_user( $owner_id );
$private = $imports->runContent( 'dry_run', 'property', 'json', '{"rows":[{"slug":"phase9-private-injection","title":"No private field","private_notes":"secret"}]}', array(), $owner_id );
$protected = $imports->runContent( 'dry_run', 'property', 'json', '{"rows":[{"slug":"phase9-protected-injection","title":"No protected field","post_type":"property","post_author":1,"option_name":"bad","capabilities":["manage_options"]}]}', array(), $owner_id );
$tax = $imports->runContent( 'dry_run', 'property', 'json', '{"rows":[{"slug":"phase9-arbitrary-taxonomy","title":"No arbitrary taxonomy","tax_not_registered":["x"]}]}', array(), $owner_id );
$check( 'private_field_rejected', is_array( $private ) && 'FAIL' === $private['status'] && 'NONE' === $private['mutation'] && ! get_page_by_path( 'phase9-private-injection', OBJECT, 'property' ) );
$check( 'protected_input_rejected', is_array( $protected ) && 'FAIL' === $protected['status'] && 'NONE' === $protected['mutation'] && ! get_page_by_path( 'phase9-protected-injection', OBJECT, 'property' ) );
$check( 'arbitrary_taxonomy_rejected', is_array( $tax ) && 'FAIL' === $tax['status'] && 'NONE' === $tax['mutation'] && ! get_page_by_path( 'phase9-arbitrary-taxonomy', OBJECT, 'property' ) );

$parser = new \Mayfair\RealEstatePlatform\ImportExport\SourceParser();
$check( 'malformed_csv_rejected', is_wp_error( $parser->parseString( "slug,title\nonly-one\n", 'csv' ) ) );
$check( 'malformed_json_rejected', is_wp_error( $parser->parseString( '{"rows":[["not-an-object"]]}', 'json' ) ) );
$check( 'invalid_utf8_rejected', is_wp_error( $parser->parseString( "slug\n\xFF", 'csv' ) ) );
$check( 'oversized_source_rejected', is_wp_error( $parser->parseString( str_repeat( 'x', $parser::MAX_BYTES + 1 ), 'csv' ) ) );
$deep = str_repeat( '{"a":', 33 ) . '0' . str_repeat( '}', 33 );
$check( 'deep_json_rejected', is_wp_error( $parser->parseString( $deep, 'json' ) ) );
$check( 'duplicate_normalized_json_keys_rejected', is_wp_error( $parser->parseString( '{"rows":[{"slug":"a"," SLUG ":"b"}]}', 'json' ) ) );

$check( 'import_path_traversal_rejected', is_wp_error( $imports->runFile( 'validate', 'property', 'csv', '../outside.csv', array(), $owner_id ) ) );
$check( 'import_absolute_path_rejected', is_wp_error( $imports->runFile( 'validate', 'property', 'csv', '/tmp/outside.csv', array(), $owner_id ) ) );
$check( 'export_path_traversal_rejected', is_wp_error( $exports->writeFile( 'property', 'csv', '../outside.csv', $owner_id ) ) );
$check( 'export_extension_mismatch_rejected', is_wp_error( $exports->writeFile( 'property', 'csv', 'safe.json', $owner_id ) ) );

$urls = array( 'http://example.com/file.jpg', 'https://127.0.0.1/file.jpg', 'https://localhost/file.jpg', 'https://169.254.169.254/latest/meta-data/', 'https://10.0.0.1/file.jpg', 'https://example.com:8443/file.jpg' );
$url_results = array();
foreach ( $urls as $url ) {
	$url_results[ $url ] = \Mayfair\RealEstatePlatform\Security\Security::validateRemoteUrl( $url );
}
$check( 'http_remote_media_rejected', is_wp_error( $url_results['http://example.com/file.jpg'] ) );
$check( 'loopback_ssrf_rejected', is_wp_error( $url_results['https://127.0.0.1/file.jpg'] ) && is_wp_error( $url_results['https://localhost/file.jpg'] ) );
$check( 'metadata_ssrf_rejected', is_wp_error( $url_results['https://169.254.169.254/latest/meta-data/'] ) );
$check( 'private_network_ssrf_rejected', is_wp_error( $url_results['https://10.0.0.1/file.jpg'] ) );
$check( 'unsafe_port_rejected', is_wp_error( $url_results['https://example.com:8443/file.jpg'] ) );

$script_files = array_merge( glob( '/wordpress/wp-content/plugins/realestate-platform/src/ImportExport/*.php' ) ?: array(), array( '/wordpress/wp-content/plugins/realestate-platform/src/CLI/Commands.php' ) );
$forbidden = false;
foreach ( $script_files as $file ) {
	$source = file_get_contents( $file );
	if ( false !== $source && preg_match( '/\$wpdb|\b(?:INSERT|UPDATE|DELETE)\s+INTO\b|wp_insert_user|wp_create_user|add_cap\s*\(|update_option\s*\(|(?:^|[^a-z])(?:eval|unserialize)\s*\(/i', $source ) ) {
		$forbidden = true;
		break;
	}
}
$check( 'import_export_has_no_direct_sql_or_privilege_mutation', ! $forbidden );
$routes = rest_get_server()->get_routes();
$check( 'no_rest_import_surface', ! array_filter( array_keys( $routes ), static fn( string $route ): bool => str_contains( $route, 'realestate-platform/v1/import' ) || str_contains( $route, 'realestate-platform/v1/export' ) ) );

$serialized = $imports->runContent( 'dry_run', 'property', 'json', '{"rows":[{"slug":"phase9-serialized-payload","title":"Serialized payload is data","address":"O:8:\"stdClass\":0:{}"}]}', array(), $owner_id );
$check( 'serialized_payload_is_not_executed', is_array( $serialized ) && 'PASS' === $serialized['status'] && 'NONE' === $serialized['mutation'] && ! get_page_by_path( 'phase9-serialized-payload', OBJECT, 'property' ) );

$failed = array_filter( $checks, static fn ( bool $value ): bool => ! $value );
echo wp_json_encode(
	array(
		'status'      => array() === $failed ? 'PASS' : 'FAIL',
		'checks'      => $checks,
		'failed'      => array_keys( $failed ),
		'environment' => array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'database' => 'SQLite' ),
		'qualifications' => array( 'No REST import/export route exists; nonce is not applicable to the sole WP-CLI transport.', 'Remote media was not downloaded; allowed-public-host behavior remains NOT VERIFIED.' ),
	),
	JSON_PRETTY_PRINT
);
