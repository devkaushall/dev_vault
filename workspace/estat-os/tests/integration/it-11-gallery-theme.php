<?php
/**
 * The office gallery, and the light/dark appearance setting.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Actions;
use EstatOS\Admin\Admin;
use EstatOS\Admin\Screens\GalleryScreen;
use EstatOS\Admin\Screens\SettingsScreen;
use EstatOS\Data\Listings;
use EstatOS\Data\Media;
use EstatOS\Data\PostTypes;
use EstatOS\Settings\Settings;

estat_reset_world();
estat_login_owner();

/**
 * Create a file in the library.
 *
 * @param string $title File name.
 * @param string $mime  MIME type.
 * @return int
 */
function estat_make_file( string $title, string $mime ): int {
	return (int) wp_insert_post(
		array(
			'post_type'      => 'attachment',
			'post_title'     => $title,
			'post_status'    => 'inherit',
			'post_mime_type' => $mime,
		)
	);
}

$property = (int) Listings::save(
	array(
		'title'         => 'Saket flat with a view',
		'offer'         => 'sale',
		'property_type' => 'apartment',
		'locality'      => 'Saket',
		'price'         => 5000000,
		'area'          => 1000,
		'area_unit'     => 'sqft',
		'status'        => 'publish',
	)
);

$photo    = estat_make_file( 'front-view.jpg', 'image/jpeg' );
$photo2   = estat_make_file( 'kitchen.png', 'image/png' );
$brochure = estat_make_file( 'tower-brochure.pdf', 'application/pdf' );

/* ------------------------------------------------------------ Kinds */

EstatTests::group( 'Integration: the gallery sorts files by what they are' );

$kinds = Media::kinds();

EstatTests::ok( count( $kinds ) >= 4, 'the office can file photos, plans, brochures and documents' );
EstatTests::ok( isset( $kinds['photo'] ), 'photos are one of the kinds' );
EstatTests::ok( isset( $kinds['brochure'] ), 'brochures are one of the kinds' );

EstatTests::is( 'photo', Media::kind_of( $photo ), 'a picture is treated as a photo without being told' );
EstatTests::is( 'document', Media::kind_of( $brochure ), 'a PDF is treated as a document without being told' );

EstatTests::ok( Media::set_kind( $brochure, 'brochure' ), 'a file can be refiled as a brochure' );
EstatTests::is( 'brochure', Media::kind_of( $brochure ), 'and it stays refiled' );

EstatTests::ok( ! Media::set_kind( $brochure, 'not_a_kind' ), 'an invented kind is refused' );
EstatTests::is( 'brochure', Media::kind_of( $brochure ), 'and the real kind is untouched' );

EstatTests::ok( ! Media::set_kind( $property, 'photo' ), 'a property cannot be refiled as if it were a file' );

/* ------------------------------------------------------- Attaching */

EstatTests::group( 'Integration: files can belong to a property' );

EstatTests::ok( Media::link( $photo, $property ), 'a photo can be attached to a property' );
EstatTests::is( $property, Media::to_array( $photo )['linked_id'], 'the link is stored' );
EstatTests::is( 'Saket flat with a view', Media::to_array( $photo )['linked_name'], 'the gallery shows which property it belongs to' );

EstatTests::ok( ! Media::link( $photo, 999999 ), 'attaching to something that does not exist is refused' );
EstatTests::ok( ! Media::link( $property, $property ), 'a property cannot be attached as though it were a file' );

EstatTests::ok( Media::link( $photo, 0 ), 'a file can be detached again' );
EstatTests::is( 0, Media::to_array( $photo )['linked_id'], 'and the link is gone' );

Media::link( $photo, $property );

/* ------------------------------------------------------- Searching */

EstatTests::group( 'Integration: finding files' );

EstatTests::is( 2, Media::query( array( 'kind' => 'photo' ) )['total'], 'the photo tab shows only pictures' );
EstatTests::is( 1, Media::query( array( 'kind' => 'brochure' ) )['total'], 'the brochure tab shows only documents' );

EstatTests::is( 1, Media::query( array( 'attached_to' => $property ) )['total'], 'a property\'s own files can be listed' );
EstatTests::is( 1, Media::query( array( 'kind' => 'photo', 'unattached' => true ) )['total'], 'files not attached to anything can be found' );

EstatTests::is( 1, Media::query( array( 'kind' => 'photo', 'search' => 'kitchen' ) )['total'], 'searching by file name works' );
EstatTests::is( 0, Media::query( array( 'kind' => 'photo', 'search' => 'zzzz' ) )['total'], 'search does not invent matches' );

$shape = Media::to_array( $photo );
foreach ( array( 'id', 'title', 'mime', 'is_image', 'kind', 'thumb', 'url', 'filesize', 'date', 'linked_id', 'linked_name' ) as $key ) {
	EstatTests::ok( array_key_exists( $key, $shape ), 'a file describes its "' . $key . '"' );
}
EstatTests::ok( true === $shape['is_image'], 'a picture knows it is a picture' );
EstatTests::ok( false === Media::to_array( $brochure )['is_image'], 'a PDF knows it is not a picture' );

$per_page = Media::query( array( 'kind' => 'photo', 'per_page' => 9999 ) );
EstatTests::ok( count( $per_page['items'] ) <= 100, 'the gallery never loads an unbounded number of files at once' );

/* --------------------------------------------------------- Screen */

EstatTests::group( 'Integration: the gallery screen' );

$_GET = array( 'page' => 'estat-gallery', 'kind' => 'photo' );
ob_start();
GalleryScreen::render();
$screen = (string) ob_get_clean();

EstatTests::ok( false !== strpos( $screen, 'estat-dropzone' ), 'there is a place to drop files' );
EstatTests::ok( false !== strpos( $screen, 'estat-gallery-grid' ), 'photos are shown as a grid' );
EstatTests::ok( false !== strpos( $screen, 'front-view.jpg' ), 'an uploaded photo is listed' );
EstatTests::ok( false === strpos( $screen, 'tower-brochure.pdf' ), 'a brochure does not appear on the photo tab' );
EstatTests::ok( false !== strpos( $screen, 'Saket flat with a view' ), 'the grid says which property a photo belongs to' );
EstatTests::ok( false !== strpos( $screen, 'loading="lazy"' ), 'pictures load lazily so the page stays fast' );

$_GET = array( 'page' => 'estat-gallery', 'kind' => 'brochure' );
ob_start();
GalleryScreen::render();
$brochure_screen = (string) ob_get_clean();

EstatTests::ok( false !== strpos( $brochure_screen, 'tower-brochure.pdf' ), 'the brochure tab lists brochures' );
EstatTests::ok( false === strpos( $brochure_screen, 'estat-gallery-grid' ), 'documents are shown as a list, not a picture grid' );

// A hostile file name must not be able to run anything.
$nasty = estat_make_file( '<script>alert(1)</script>.jpg', 'image/jpeg' );
$_GET  = array( 'page' => 'estat-gallery', 'kind' => 'photo' );
ob_start();
GalleryScreen::render();
$nasty_screen = (string) ob_get_clean();

EstatTests::ok( false === strpos( $nasty_screen, '<script>alert' ), 'a hostile file name cannot run code in the gallery' );

/* ---------------------------------------------------- Permissions */

EstatTests::group( 'Integration: the gallery respects permissions' );

estat_login_with( 610, array( 'estat_manage_leads' ) );
$_GET    = array( 'page' => 'estat-gallery' );
$blocked = false;
try {
	ob_start();
	GalleryScreen::render();
	ob_end_clean();
} catch ( Throwable $e ) {
	ob_end_clean();
	$blocked = true;
}
EstatTests::ok( $blocked, 'someone without the listings permission cannot open the gallery' );

estat_login_with( 611, array( 'estat_manage_listings' ) );
$allowed = true;
try {
	ob_start();
	GalleryScreen::render();
	ob_end_clean();
} catch ( Throwable $e ) {
	ob_end_clean();
	$allowed = false;
}
EstatTests::ok( $allowed, 'someone with the listings permission can open the gallery' );

// Without the delete permission there must be no remove button at all.
ob_start();
GalleryScreen::render();
$no_delete = (string) ob_get_clean();
EstatTests::ok( false === strpos( $no_delete, 'delete_file' ), 'no remove button is offered to someone who may not delete' );

/* ------------------------------------------------- Deleting files */

EstatTests::group( 'Integration: removing a file cannot remove a property' );

estat_login_owner();

// The most important test on this screen: the file-delete action must never
// be usable to destroy business records.
$_REQUEST = $_GET = array(
	'estat_action' => 'delete_file',
	'file_id'      => (string) $property,
	'estat_nonce'  => wp_create_nonce( 'estat_delete_file_' . $property ),
);
try {
	Actions::handle();
} catch ( Throwable $e ) {
	$e->getMessage();
}
EstatTests::ok( null !== get_post( $property ), 'a property cannot be destroyed through the file remove action' );
EstatTests::is( PostTypes::LISTING, get_post_type( $property ), 'and it is still a property' );

// The check above passes even without the plugin's own guard, because
// WordPress refuses too. Assert the plugin refuses on its own account, so the
// protection is not left resting on core's behaviour alone.
$handler = (string) file_get_contents( ESTAT_DIR . 'includes/Admin/Actions.php' );
preg_match( '/private static function delete_file\(\).*?
	\}/s', $handler, $body );

EstatTests::ok(
	isset( $body[0] ) && false !== strpos( $body[0], "'attachment' !== get_post_type" ),
	'the remove action checks for itself that the target really is a file'
);
EstatTests::ok(
	isset( $body[0] ) && false !== strpos( $body[0], 'self::verify(' ),
	'the remove action refuses a request without a valid security token'
);
EstatTests::ok(
	false !== strpos( $handler, "'delete_file'      => 'estat_delete_listings'" ),
	'the remove action is guarded by the delete permission'
);

// A forged link must do nothing.
$_REQUEST = $_GET = array(
	'estat_action' => 'delete_file',
	'file_id'      => (string) $photo2,
	'estat_nonce'  => 'forged-token',
);
try {
	Actions::handle();
} catch ( Throwable $e ) {
	$e->getMessage();
}
EstatTests::ok( null !== get_post( $photo2 ), 'a forged remove link does not remove anything' );

// Someone without the delete permission must not succeed.
estat_login_with( 612, array( 'estat_manage_listings' ) );
$_REQUEST = $_GET = array(
	'estat_action' => 'delete_file',
	'file_id'      => (string) $photo2,
	'estat_nonce'  => wp_create_nonce( 'estat_delete_file_' . $photo2 ),
);
try {
	Actions::handle();
} catch ( Throwable $e ) {
	$e->getMessage();
}
EstatTests::ok( null !== get_post( $photo2 ), 'someone without the delete permission cannot remove a file' );

// The owner can.
estat_login_owner();
$_REQUEST = $_GET = array(
	'estat_action' => 'delete_file',
	'file_id'      => (string) $photo2,
	'estat_nonce'  => wp_create_nonce( 'estat_delete_file_' . $photo2 ),
);
try {
	Actions::handle();
} catch ( Throwable $e ) {
	$e->getMessage();
}
EstatTests::ok( null === get_post( $photo2 ), 'the owner can remove a real file' );

/* ------------------------------------------------- Menu placement */

EstatTests::group( 'Integration: the gallery is the office\'s own screen' );

$admin_source = (string) file_get_contents( ESTAT_DIR . 'includes/Admin/Admin.php' );

EstatTests::ok(
	false === strpos( $admin_source, "'upload.php'" ),
	'the Gallery menu entry no longer points at the raw WordPress media library'
);
EstatTests::ok(
	false !== strpos( $admin_source, 'estat-gallery' ),
	'the Gallery menu entry points at the plugin\'s own screen'
);
EstatTests::ok(
	false === strpos( $admin_source, "'edit.php'" ),
	'the Insights menu entry no longer points at the WordPress post editor'
);

/* ==================================================== Appearance */

EstatTests::group( 'Integration: light and dark screens' );

$admin = new Admin();
$_GET  = array( 'page' => 'estat-today' );

foreach ( array( 'light', 'dark', 'auto' ) as $theme ) {
	Settings::update( array( 'admin_theme' => $theme ) );
	$classes = $admin->body_class( '' );

	EstatTests::ok(
		false !== strpos( $classes, 'estat-theme-' . $theme ),
		'choosing the "' . $theme . '" appearance marks the page so the styles follow'
	);
	EstatTests::ok(
		false !== strpos( $classes, 'estat-screen' ),
		'the office screens are still marked as office screens in "' . $theme . '"'
	);
}

Settings::update( array( 'admin_theme' => '<script>evil</script>' ) );
EstatTests::is( 'light', Settings::get( 'admin_theme' ), 'a junk appearance value falls back to light instead of breaking the page' );

Settings::update( array( 'admin_theme' => 'dark' ) );
$classes = $admin->body_class( '' );
EstatTests::ok( false === strpos( $classes, '<' ), 'the appearance never puts raw markup into the page' );

// Other screens must not be touched.
$_GET = array( 'page' => 'edit.php' );
EstatTests::is( '', $admin->body_class( '' ), 'the appearance setting does not leak onto other WordPress screens' );

$_GET = array( 'page' => 'estat-settings', 'tab' => 'office' );
ob_start();
SettingsScreen::render();
$settings_screen = (string) ob_get_clean();

EstatTests::ok( false !== strpos( $settings_screen, 'admin_theme' ), 'the office can choose the appearance in settings' );
EstatTests::ok( false !== strpos( $settings_screen, 'Dark' ), 'dark is offered in plain language' );

Settings::update( array( 'admin_theme' => 'light' ) );

/* ------------------------------------------------ Styles are token-based */

EstatTests::group( 'Integration: the dark theme is not half-done' );

$css = (string) file_get_contents( ESTAT_DIR . 'assets/css/admin.css' );

EstatTests::ok( false !== strpos( $css, '.estat-theme-dark' ), 'a dark theme exists' );
EstatTests::ok( false !== strpos( $css, 'prefers-color-scheme: dark' ), 'the automatic option follows the computer' );
EstatTests::ok( false !== strpos( $css, 'prefers-reduced-motion' ), 'movement can be turned off for people who need that' );

// Every token defined for light must also be defined for dark, or parts of
// the screen would stay bright in dark mode.
preg_match( '/\.estat-screen \{(.*?)\}/s', $css, $light_block );
preg_match( '/\.estat-screen\.estat-theme-dark \{(.*?)\}/s', $css, $dark_block );

preg_match_all( '/(--estat-[a-z\-]+):/', $light_block[1] ?? '', $light_tokens );
preg_match_all( '/(--estat-[a-z\-]+):/', $dark_block[1] ?? '', $dark_tokens );

$colour_tokens = array_values(
	array_filter(
		$light_tokens[1] ?? array(),
		static function ( string $token ): bool {
			// Shape, spacing and type size do not change between themes.
			// Only colour has to be restated for dark mode.
			foreach ( array( 'radius', 'gap', 'text', 'leading' ) as $not_a_colour ) {
				if ( false !== strpos( $token, $not_a_colour ) ) {
					return false;
				}
			}

			return true;
		}
	)
);

$missing_in_dark = array_values( array_diff( $colour_tokens, $dark_tokens[1] ?? array() ) );

EstatTests::is( array(), $missing_in_dark, 'every colour has a dark equivalent, so nothing stays glaring in dark mode' );

$_GET  = array();
$_POST = array();
