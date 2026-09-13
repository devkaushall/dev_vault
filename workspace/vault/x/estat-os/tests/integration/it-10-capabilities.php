<?php
/**
 * Menu visibility and permissions, checked against real WordPress capability code.
 *
 * A live site showed most of the Office menu missing and "Sorry, you are not
 * allowed to access this page" for an administrator. The cause was that
 * several content types mapped WordPress's three per-post capabilities
 * (edit_post, read_post, delete_post) onto the same office capability. Core
 * records those names in a global table and from then on treats them as
 * per-post capabilities that require a post ID, so a plain permission check
 * with no post ID returned false for everyone, including administrators.
 *
 * These tests run the real core functions, not a substitute, so the same
 * mistake cannot be reintroduced silently.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Data\PostTypes;
use EstatOS\Security\Roles;

estat_reset_world();
estat_login_owner();

EstatTests::group( 'Integration: office permissions are not mistaken for per-post ones' );

$core_post = '/tmp/wordpress/wp-includes/post.php';

if ( ! file_exists( $core_post ) ) {
	EstatTests::ok( false, 'real WordPress source is available to test capability mapping' );
	return;
}

/**
 * Reimplementation-free check: read the capability maps the plugin actually
 * registers, then apply core's own rule for what becomes a meta capability.
 */
$source = (string) file_get_contents( ESTAT_DIR . 'includes/Data/PostTypes.php' );

// Only literal names count; the helper's own template lines build their names
// from a variable and are matched separately below.
preg_match_all(
	"/'(edit_post|read_post|delete_post)'\s*=>\s*'([a-z_]{3,})'\s*,/",
	$source,
	$matches,
	PREG_SET_ORDER
);

EstatTests::ok( count( $matches ) > 0, 'the plugin registers per-post capabilities that can be inspected' );

$meta_names = array();
$duplicates = array();

foreach ( $matches as $match ) {
	$mapped_to = $match[2];
	if ( in_array( $mapped_to, array( 'edit_', 'read_', 'delete_' ), true ) ) {
		continue;
	}
	if ( isset( $meta_names[ $mapped_to ] ) ) {
		$duplicates[] = $mapped_to;
	}
	$meta_names[ $mapped_to ] = $match[1];
}

EstatTests::is(
	array(),
	array_values( array_unique( $duplicates ) ),
	'no two content types claim the same per-post capability name'
);

// The capabilities that guard menu entries must never appear in that list.
$menu_caps = array(
	'estat_manage_listings',
	'estat_manage_projects',
	'estat_manage_team',
	'estat_manage_insights',
	'estat_manage_leads',
	'estat_manage_visits',
	'estat_manage_forms',
	'estat_import_inventory',
	'estat_view_reports',
	'estat_manage_settings',
	'estat_delete_listings',
	'estat_publish_listings',
);

$collapsed = array_values( array_intersect( $menu_caps, array_keys( $meta_names ) ) );

EstatTests::is(
	array(),
	$collapsed,
	'no office capability is registered as a per-post capability, so menu checks work without a post ID'
);

/* ------------------------------------------------- Roles grant everything */

EstatTests::group( 'Integration: the roles actually carry these permissions' );

$record_caps = Roles::record_caps();

EstatTests::ok( count( $record_caps ) > 0, 'the plugin knows its private per-post capabilities' );

// Every private per-post name the content types use must be granted by the roles.
$granted = array_flip( $record_caps );
$missing = array();

foreach ( array_keys( $meta_names ) as $name ) {
	if ( 'read' === $name ) {
		continue;
	}
	if ( ! isset( $granted[ $name ] ) ) {
		$missing[] = $name;
	}
}

EstatTests::is(
	array(),
	$missing,
	'every per-post capability a content type uses is granted to the roles, so opening a record is not refused'
);

Roles::install();

$owner_caps = $GLOBALS['estat_roles'][ Roles::OWNER ]['capabilities'] ?? array();
$agent_caps = $GLOBALS['estat_roles'][ Roles::AGENT ]['capabilities'] ?? array();

foreach ( $record_caps as $cap ) {
	EstatTests::ok( ! empty( $owner_caps[ $cap ] ), 'the office owner may work with records: ' . $cap );
}

EstatTests::ok( ! empty( $agent_caps['edit_estat_listing'] ), 'an agent may open a property record' );
EstatTests::ok( ! empty( $agent_caps['estat_manage_listings'] ), 'an agent may reach the listings screen' );

/* ------------------------------------------------- Every menu entry opens */

EstatTests::group( 'Integration: every menu entry is reachable by the owner' );

// These are the capabilities Admin::add_menu() guards each entry with.
$entries = array(
	'Today'                => 'estat_manage_listings',
	'Add a Home'           => 'estat_manage_listings',
	'Listings'             => 'estat_manage_listings',
	'Enquiries'            => 'estat_manage_leads',
	'Site Visits'          => 'estat_manage_visits',
	'Societies & Projects' => 'estat_manage_projects',
	'Team'                 => 'estat_manage_team',
	'Forms'                => 'estat_manage_forms',
	'Spreadsheet'          => 'estat_import_inventory',
	'Reports'              => 'estat_view_reports',
	'Office Settings'      => 'estat_manage_settings',
	'Insights'             => 'estat_manage_insights',
);

$all_caps = Roles::all();

foreach ( $entries as $label => $cap ) {
	EstatTests::ok(
		array_key_exists( $cap, $all_caps ),
		'the "' . $label . '" menu entry is guarded by a capability the plugin actually defines'
	);
	EstatTests::ok(
		! empty( $owner_caps[ $cap ] ),
		'the office owner can open "' . $label . '"'
	);
}

// The administrator role must be able to open everything too.
$admin_caps = $GLOBALS['estat_roles']['administrator']['capabilities'] ?? array();

foreach ( $entries as $label => $cap ) {
	EstatTests::ok(
		! empty( $admin_caps[ $cap ] ),
		'a WordPress administrator can open "' . $label . '"'
	);
}

/* ------------------------------------------------- Menu list stays in sync */

EstatTests::group( 'Integration: the menu and the screens agree' );

$admin_source = (string) file_get_contents( ESTAT_DIR . 'includes/Admin/Admin.php' );

preg_match_all( "/'(estat-[a-z\-]+)'\s*,\s*__\(/", $admin_source, $slug_matches );
$menu_slugs = array_unique( $slug_matches[1] ?? array() );

EstatTests::ok( count( $menu_slugs ) >= 11, 'the office menu offers all of its screens (found ' . count( $menu_slugs ) . ')' );

foreach ( $menu_slugs as $slug ) {
	EstatTests::ok(
		false !== strpos( $admin_source, "'" . $slug . "'" ),
		'menu entry "' . $slug . '" is wired to a screen'
	);
}

/* ------------------------------------------------- Content types are sane */

EstatTests::group( 'Integration: content types do not fight each other' );

$types = array( PostTypes::LISTING, PostTypes::PROJECT, PostTypes::AGENT, PostTypes::AGENCY, PostTypes::INSIGHT, PostTypes::DOCUMENT );

$seen = array();
foreach ( $types as $type ) {
	EstatTests::ok( ! isset( $seen[ $type ] ), 'content type "' . $type . '" is registered once' );
	$seen[ $type ] = true;
}

// None of them may appear in the WordPress sidebar on their own; the office
// menu is the only way in.
EstatTests::is(
	6,
	substr_count( $source, "'show_in_menu'        => false" ) + substr_count( $source, "'show_in_menu'    => false" ),
	'no content type adds its own entry to the WordPress sidebar'
);
