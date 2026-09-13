<?php
/**
 * Photo pickers must actually be wired up.
 *
 * These bugs were invisible to every earlier test because the PHP was fine on
 * its own and the JavaScript was fine on its own -- they simply disagreed
 * about the attribute that joins them. So this file reads BOTH sides and
 * checks that what the JavaScript looks for is what the PHP writes.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Screens\GalleryScreen;
use EstatOS\Admin\Screens\Partials;

estat_reset_world();
estat_login_owner();

/**
 * Render one field and hand back the HTML.
 *
 * @param array $args Field arguments.
 * @return string
 */
function estat_it19_field( array $args ): string {
	ob_start();
	try {
		Partials::field( $args );
	} catch ( \Throwable $e ) {
		$html = ob_get_contents();
		ob_end_clean();
		return $html . "\n<!-- FATAL " . get_class( $e ) . ': ' . $e->getMessage() . ' -->';
	}
	$html = ob_get_contents();
	ob_end_clean();
	return $html;
}

$existing_photo = (int) wp_insert_post(
	array(
		'post_type'      => 'attachment',
		'post_title'     => 'already-added.jpg',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image/jpeg',
	)
);

$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/admin.js' );

$image   = estat_it19_field(
	array(
		'name'  => 'cover_id',
		'type'  => 'image',
		'label' => 'Main photo',
		'value' => 0,
	)
);
$gallery = estat_it19_field(
	array(
		'name'  => 'gallery_ids',
		'type'  => 'gallery',
		'label' => 'Photo gallery',
		'value' => array(),
	)
);

EstatTests::group( 'The single-photo picker is wired to its hidden input' );

EstatTests::ok( false === strpos( $image, 'FATAL' ), 'the image field renders without fatalling' );

// BUG 1: the JS reads data-target off the BUTTON, so the button must carry it.
preg_match_all( '/<button\b[^>]*class="[^"]*estat-pick-image[^"]*"[^>]*>/', $image, $picks );
EstatTests::ok( count( $picks[0] ) > 0, 'the field renders at least one "choose a photo" control' );
foreach ( $picks[0] as $tag ) {
	EstatTests::ok(
		false !== strpos( $tag, 'data-target=' ),
		'every estat-pick-image control carries data-target on the element the JS listens to'
	);
}

preg_match( '/<button\b[^>]*estat-clear-image[^>]*>/', $image, $clear );
EstatTests::ok( ! empty( $clear ), 'the remove control exists' );
EstatTests::ok(
	false !== strpos( (string) ( $clear[0] ?? '' ), 'data-target=' ),
	'the remove control also carries data-target'
);

// BUG 2: data-target is fed straight to querySelector, so it needs the "#".
preg_match_all( '/data-target="([^"]*)"/', $image, $targets );
EstatTests::ok( count( $targets[1] ) > 0, 'the image field writes data-target somewhere' );
foreach ( $targets[1] as $value ) {
	EstatTests::ok(
		'' !== $value && '#' === $value[0],
		'data-target "' . $value . '" is a real CSS selector (starts with #)'
	);
}

// And the selector must find the hidden input that actually gets submitted.
preg_match( '/<input type="hidden" id="([^"]+)" name="cover_id"/', $image, $hidden );
EstatTests::ok( ! empty( $hidden ), 'the single-photo field has a hidden input named cover_id' );
EstatTests::is(
	'#' . ( $hidden[1] ?? '' ),
	$targets[1][0] ?? '',
	'data-target points at the id of that hidden input'
);

EstatTests::group( 'Tapping the photo itself opens the picker' );

EstatTests::ok(
	(bool) preg_match( '/<button\b[^>]*class="[^"]*estat-image-preview[^"]*estat-pick-image/', $image ),
	'the preview area is itself a picker button, so tapping the photo does something'
);
EstatTests::ok(
	false !== strpos( $image, 'estat-image-empty' ),
	'an empty picker tells the user they can tap it'
);

EstatTests::group( 'The gallery picker is wired to its list' );

EstatTests::ok( false === strpos( $gallery, 'FATAL' ), 'the gallery field renders without fatalling' );

// BUG 3: the JS reads data-list off the "add photos" button. It never existed.
EstatTests::ok(
	false !== strpos( $js, "button.getAttribute( 'data-list' )" ),
	'the JS really does read data-list off the add-photos button (guards this test)'
);
preg_match( '/<button\b[^>]*estat-pick-gallery[^>]*>/', $gallery, $add );
EstatTests::ok( ! empty( $add ), 'the "add photos" button exists' );
EstatTests::ok(
	false !== strpos( (string) ( $add[0] ?? '' ), 'data-list=' ),
	'the "add photos" button carries data-list'
);

preg_match( '/<button\b[^>]*estat-pick-gallery[^>]*data-list="([^"]*)"/', $gallery, $listsel );
$list_selector = (string) ( $listsel[1] ?? '' );
EstatTests::ok(
	'' !== $list_selector && '#' === $list_selector[0],
	'data-list "' . $list_selector . '" is a real CSS selector'
);
preg_match( '/<ul class="estat-gallery-list" id="([^"]+)"/', $gallery, $listid );
EstatTests::ok( ! empty( $listid ), 'the gallery list carries an id so it can be found' );
EstatTests::is(
	'#' . ( $listid[1] ?? '' ),
	$list_selector,
	'data-list points at the id of the real gallery list'
);

// syncGallery() reads data-target off the LIST, not the wrapper.
EstatTests::ok(
	false !== strpos( $js, "list.getAttribute( 'data-target' )" ),
	'the JS reads data-target off the list element (guards this test)'
);
preg_match( '/<ul class="estat-gallery-list"[^>]*data-target="([^"]*)"/', $gallery, $ltarget );
$list_target = (string) ( $ltarget[1] ?? '' );
EstatTests::ok(
	'' !== $list_target && '#' === $list_target[0],
	'the gallery list carries a usable data-target so reordering saves'
);
preg_match( '/<input type="hidden" id="([^"]+)" name="gallery_ids"/', $gallery, $ghidden );
EstatTests::is(
	'#' . ( $ghidden[1] ?? '' ),
	$list_target,
	'the list data-target points at the hidden input that stores the order'
);

EstatTests::group( 'Every selector the picker JS uses exists in the rendered HTML' );

// A gallery that already holds a photo, so the per-photo remove button renders.
$filled = estat_it19_field(
	array(
		'name'  => 'gallery_ids',
		'type'  => 'gallery',
		'label' => 'Photo gallery',
		'value' => array( $existing_photo ),
	)
);

EstatTests::ok(
	(bool) preg_match( '/<li data-id="' . $existing_photo . '"[^>]*draggable="true"/', $filled ),
	'an existing gallery photo is draggable for reordering'
);
EstatTests::ok(
	(bool) preg_match( '/<li data-id="' . $existing_photo . '"[^>]*tabindex="0"/', $filled ),
	'an existing gallery photo is reachable by keyboard'
);

$both = $image . $gallery . $filled;
// Only the classes the JS actually hooks. estat-image-picker and
// estat-gallery-picker are PHP-side wrappers the JS reaches via closest()
// or never at all, so they are checked on the PHP side only.
foreach ( array( 'estat-pick-image', 'estat-clear-image', 'estat-gallery-list', 'estat-pick-gallery', 'estat-gallery-remove', 'estat-image-preview' ) as $class ) {
	EstatTests::ok(
		false !== strpos( $js, $class ),
		'the JS knows about .' . $class
	);
	EstatTests::ok(
		false !== strpos( $both, $class ),
		'the PHP actually renders .' . $class
	);
}

foreach ( array( 'estat-image-picker', 'estat-gallery-picker' ) as $class ) {
	EstatTests::ok(
		false !== strpos( $both, $class ),
		'the PHP renders the .' . $class . ' wrapper the JS reaches with closest()'
	);
}

EstatTests::group( 'Gallery screen: tapping a photo opens it' );

$attachment = (int) wp_insert_post(
	array(
		'post_type'      => 'attachment',
		'post_title'     => 'tap-me.jpg',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image/jpeg',
	)
);

ob_start();
try {
	GalleryScreen::render();
	$screen = ob_get_contents();
	ob_end_clean();
} catch ( \Throwable $e ) {
	$screen = ob_get_contents();
	ob_end_clean();
	$screen .= "\n<!-- FATAL " . get_class( $e ) . ' @ ' . $e->getLine() . ': ' . $e->getMessage() . ' -->';
}

EstatTests::ok( false === strpos( $screen, 'FATAL' ), 'the gallery screen renders without fatalling' );
EstatTests::ok(
	(bool) preg_match( '/<a\b[^>]*class="estat-tile-image"[^>]*href="[^"]+"/', $screen ),
	'each tile image is a real link, so tapping the photo opens the file'
);
EstatTests::ok(
	(bool) preg_match( '/<a\b[^>]*class="estat-tile-image"[^>]*aria-label="[^"]+"/', $screen ),
	'that link is labelled for screen readers'
);
EstatTests::ok(
	0 === preg_match( '/<div class="estat-tile-image">/', $screen ),
	'the old dead, unclickable tile wrapper is gone'
);

unset( $attachment );
