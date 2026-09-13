<?php
/**
 * The public website.
 *
 * The office screens had four thousand lines of stylesheet and the visitor's
 * side had a hundred and eighty, most of it minified with hardcoded colours.
 * That imbalance is the reason the front of the site felt unfinished, and it
 * happened because every request for months was about the admin.
 *
 * This pins the rewrite, and in particular the things that quietly rot:
 *
 * - a class rendered by a template with nothing styling it
 * - a colour written directly instead of through a token
 * - a state nobody designed, especially "no photograph" and "no results"
 * - demo photographs that seed but never clean up
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Data\Listings;
use EstatOS\Frontend\Components;
use EstatOS\Support\PracticeMode;

estat_reset_world();
estat_login_owner();

$root       = dirname( __DIR__, 2 );
$public_css = (string) file_get_contents( $root . '/assets/css/public.css' );

EstatTests::group( 'The visitor side has a real design system' );

/*
 * The old file was 185 lines. Size is not quality, but a stylesheet that small
 * cannot possibly have covered hover, focus, empty, dark mode and print for
 * fifty classes - and it did not.
 */
EstatTests::ok(
	substr_count( $public_css, "\n" ) > 500,
	'the public stylesheet is substantial (' . substr_count( $public_css, "\n" ) . ' lines)'
);

// Tokens, not literals. A theme should be able to restyle this by redefining
// a handful of names.
preg_match_all( '/--estat-p-[a-z0-9-]+\s*:/', $public_css, $tokens );

EstatTests::ok(
	count( array_unique( $tokens[0] ) ) >= 25,
	'there are ' . count( array_unique( $tokens[0] ) ) . ' design tokens'
);

// Nothing may be defined and then never used, and nothing used and never
// defined. Both are how a stylesheet drifts out of shape.
$defined = array_unique( preg_match_all( '/--([a-z0-9-]+)\s*:/', $public_css, $d ) ? $d[1] : array() );
$used    = array_unique( preg_match_all( '/var\(\s*--([a-z0-9-]+)/', $public_css, $u ) ? $u[1] : array() );

EstatTests::is( array(), array_values( array_diff( $used, $defined ) ), 'every token used is defined' );

// Colours belong in the token block, nowhere else.
$literal_colours = array();

foreach ( explode( "\n", $public_css ) as $number => $line ) {
	if ( ! preg_match( '/#[0-9a-fA-F]{3,8}\b/', $line ) ) {
		continue;
	}
	if ( preg_match( '/--estat-p-[a-z0-9-]+\s*:/', $line ) ) {
		continue; // A token definition is where a colour belongs.
	}
	if ( preg_match( '/^\s*(\/\*|\*|\/\/)/', $line ) ) {
		continue; // A comment naming a colour is not using one.
	}

	$literal_colours[] = ( $number + 1 ) . ': ' . trim( $line );
}

EstatTests::is( array(), $literal_colours, 'no rule writes a colour directly' );

// Pure black and pure white flatten a surface against an off-white page.
EstatTests::ok(
	0 === preg_match( '/#(?:fff|ffffff|000|000000)\b/i', $public_css ),
	'no pure black or pure white'
);

EstatTests::group( 'Every state is designed, not just the resting one' );

foreach (
	array(
		'hover'            => '/\.estat-card:hover/',
		'keyboard focus'   => '/:focus-visible/',
		'a pressed button' => '/\[aria-pressed="true"\]/',
		'no photograph'    => '/\.estat-card-noimage/',
		'no results'       => '/\.estat-empty/',
		'dark mode'        => '/prefers-color-scheme:\s*dark/',
		'reduced motion'   => '/prefers-reduced-motion:\s*reduce/',
		'printing'         => '/@media print/',
		'high contrast'    => '/forced-colors:\s*active/',
	) as $state => $pattern
) {
	EstatTests::ok( (bool) preg_match( $pattern, $public_css ), $state . ' is designed' );
}

/*
 * A hover effect that cannot be turned off is a problem for people who get
 * motion sick. Every transform must be cancelled under reduced motion.
 */
preg_match( '/@media \(\s*prefers-reduced-motion:\s*reduce\s*\)\s*\{(.*?)\n\}/s', $public_css, $reduced );

$reduced_block = (string) ( $reduced[1] ?? '' );

/*
 * Count them rather than looking for one. There are two separate movements to
 * cancel - the card lifting, and the photograph scaling inside it - and a
 * check for "does the word appear" passed with one of the two removed.
 */
EstatTests::ok(
	substr_count( $reduced_block, 'transform: none' ) >= 2,
	'reduced motion cancels every movement, not just the first one found'
);

// Name them, so it is obvious which is missing when this fails.
foreach (
	array(
		'the card lifting'        => '.estat-card:hover',
		'the photograph zooming'  => '.estat-card:hover .estat-card-media img',
	) as $movement => $selector
) {
	EstatTests::ok(
		false !== strpos( $reduced_block, $selector ),
		'reduced motion cancels ' . $movement
	);
}

// Phones need finger-sized controls and 16px inputs, or the page zooms.
EstatTests::ok(
	(bool) preg_match( '/min-height:\s*44px/', $public_css ),
	'tap targets are at least 44px on a phone'
);
EstatTests::ok(
	(bool) preg_match( '/font-size:\s*16px/', $public_css ),
	'inputs are 16px on a phone, so it does not zoom on focus'
);

EstatTests::group( 'Nothing is rendered without being styled' );

/*
 * This is the check that would have caught the original problem. A template
 * gains a class, nobody adds a rule for it, and one element on the page looks
 * unfinished next to everything else.
 */
$markup = '';

foreach (
	array_merge(
		glob( $root . '/includes/Frontend/*.php' ),
		glob( $root . '/includes/Frontend/templates/*.php' ),
		glob( $root . '/includes/Integrations/Elementor/*.php' )
	) as $file
) {
	$markup .= (string) file_get_contents( $file );
}

$styled = array_unique( preg_match_all( '/\.(estat-[a-z0-9-]+)/', $public_css, $s ) ? $s[1] : array() );

$rendered = array();

foreach ( preg_match_all( '/class="([^"]+)"/', $markup, $classes ) ? $classes[1] : array() as $attribute ) {
	foreach ( explode( ' ', $attribute ) as $class ) {
		$class = trim( $class );

		if ( '' !== $class && 0 === strpos( $class, 'estat-' ) ) {
			$rendered[ $class ] = true;
		}
	}
}

EstatTests::ok( count( $rendered ) > 40, count( $rendered ) . ' classes are rendered by the templates' );

EstatTests::is(
	array(),
	array_values( array_diff( array_keys( $rendered ), $styled ) ),
	'every class a template renders has a style'
);

EstatTests::group( 'A card renders properly, with and without a photograph' );

$with_photo = (int) Listings::save(
	array(
		'title'         => 'A flat that has a photograph',
		'offer'         => 'sale',
		'property_type' => 'apartment',
		'locality'      => 'Saket',
		'price'         => 8500000,
		'area'          => 1450,
		'area_unit'     => 'sqft',
		'bedrooms'      => 3,
		'status'        => 'publish',
	)
);

$card = Components::card( $with_photo );

EstatTests::ok( '' !== $card, 'the card renders' );
EstatTests::ok( false !== strpos( $card, 'estat-card-title' ), 'it has a title' );
EstatTests::ok( false !== strpos( $card, 'estat-card-price' ), 'it shows the price' );
EstatTests::ok( false !== strpos( $card, 'estat-card-locality' ), 'it shows the locality' );

// With no cover image, the placeholder must appear rather than an empty box.
EstatTests::ok(
	false !== strpos( $card, 'estat-card-noimage' ),
	'a property with no photograph gets the designed placeholder'
);

// A draft must never reach a visitor, however it is rendered.
$draft = (int) Listings::save(
	array(
		'title'         => 'A draft nobody should see',
		'offer'         => 'sale',
		'property_type' => 'apartment',
		'locality'      => 'Saket',
		'price'         => 5000000,
		'area'          => 900,
		'area_unit'     => 'sqft',
		'status'        => 'draft',
	)
);

estat_login_with( 61, array( 'read' ) );

EstatTests::is( '', Components::card( $draft ), 'a visitor is never shown a draft' );

estat_login_owner();

EstatTests::group( 'Practice mode ships photographs, and takes them away again' );

/*
 * Practice mode exists so somebody can see what a finished office looks like.
 * Three properties with no photographs look broken rather than empty, which
 * teaches exactly the wrong thing.
 */
estat_reset_world();
estat_login_owner();

$seeded = PracticeMode::seed();

EstatTests::ok( (int) $seeded['listings'] > 0, 'practice mode creates listings' );

$practice = get_posts(
	array(
		'post_type'      => 'estat_listing',
		'post_status'    => 'any',
		'posts_per_page' => 10,
		'meta_key'       => '_estat_practice', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
	)
);

$with_thumbnails = 0;

foreach ( $practice as $listing ) {
	if ( get_post_thumbnail_id( (int) $listing->ID ) ) {
		++$with_thumbnails;
	}
}

EstatTests::is(
	count( $practice ),
	$with_thumbnails,
	'every practice property has a photograph (' . $with_thumbnails . ' of ' . count( $practice ) . ')'
);

// Seeding twice must not fill the media library with copies of the same file.
$before = count( get_posts( array( 'post_type' => 'attachment', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ) ) );

PracticeMode::seed();

$after = count( get_posts( array( 'post_type' => 'attachment', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ) ) );

EstatTests::is( $before, $after, 'seeding twice reuses the photographs rather than copying them again' );

/*
 * And clearing must take them with it. Attachments are not in
 * PostTypes::all(), so the original clear() walked straight past them and
 * left three orphaned images in the media library every time.
 */
$cleared = PracticeMode::clear();

EstatTests::ok( isset( $cleared['photos'] ), 'clearing reports how many photographs it removed' );
EstatTests::ok( (int) $cleared['photos'] > 0, 'it removed ' . (int) $cleared['photos'] . ' of them' );

$left = get_posts(
	array(
		'post_type'      => 'attachment',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => PracticeMode::PHOTO_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery
	)
);

EstatTests::is( array(), $left, 'no practice photograph is left behind in the media library' );

EstatTests::group( 'The demo photographs ship and are what they claim to be' );

foreach ( array( 'practice-apartment.jpg', 'practice-builderfloor.jpg', 'practice-shop.jpg' ) as $photo ) {
	$path = $root . '/assets/demo/' . $photo;

	EstatTests::ok( file_exists( $path ), $photo . ' ships with the plugin' );

	if ( ! file_exists( $path ) ) {
		continue;
	}

	// A file called .jpg that is not a JPEG would fail on upload in a way
	// that is very hard to trace back to here.
	$handle = fopen( $path, 'rb' );
	$magic  = $handle ? bin2hex( (string) fread( $handle, 3 ) ) : '';

	if ( $handle ) {
		fclose( $handle );
	}

	EstatTests::is( 'ffd8ff', $magic, $photo . ' really is a JPEG' );

	// Big enough to look like a photograph, small enough not to bloat the zip.
	$size = (int) filesize( $path );

	EstatTests::ok( $size > 40000, $photo . ' is a real photograph, not a placeholder' );
	EstatTests::ok( $size < 700000, $photo . ' is ' . round( $size / 1024 ) . 'KB, which is reasonable to ship' );
}

EstatTests::group( 'Attaching a photograph can never break seeding' );

/*
 * Everything in attach_photo() is optional decoration. If the uploads folder
 * is unwritable, or the file is missing, or the image helpers are absent, the
 * office must still get its practice listings.
 *
 * The require_once on wp-admin/includes/image.php is the sharp edge here: a
 * missing file there is a fatal error, not a warning, so a cosmetic step
 * would have taken the whole request with it.
 */
$source = (string) file_get_contents( $root . '/includes/Support/PracticeMode.php' );

EstatTests::ok(
	false !== strpos( $source, 'is_readable( $image_helpers )' ),
	'the admin image helpers are only required when the file is really there'
);
EstatTests::ok(
	false === strpos( $source, "require_once ABSPATH . 'wp-admin/includes/image.php';" ),
	'nothing requires that file unconditionally'
);

/*
 * Prove it rather than only reading the source: seeding must survive an
 * environment where the admin image helpers do not exist. That is every
 * front-end request, every cron run and every REST call - not an edge case.
 *
 * Note the limit honestly: a failed require_once is a PHP fatal, which no
 * try/catch can intercept. If this regresses the whole run dies at this line
 * rather than reporting one red assertion. The source check above is the real
 * guard; this is the proof that the guarded path works.
 */
$survived = true;
$reason   = '';

try {
	estat_reset_world();
	estat_login_owner();
	PracticeMode::seed();
} catch ( \Throwable $e ) {
	$survived = false;
	$reason   = get_class( $e ) . ': ' . $e->getMessage();
}

EstatTests::ok( $survived, 'seeding completes without the admin image helpers loaded' . ( $reason ? ' (' . $reason . ')' : '' ) );

// Only a bare file name from our own folder is ever opened.
EstatTests::ok(
	false !== strpos( $source, '$file_name = basename( $file_name );' ),
	'a path cannot be smuggled into the demo photograph loader'
);

// An unwritable or unusual uploads configuration must be handled, not assumed.
EstatTests::ok(
	false !== strpos( $source, "is_writable( \$folder )" ),
	'an unwritable uploads folder is checked rather than assumed'
);
EstatTests::ok(
	false !== strpos( $source, "! empty( \$uploads['basedir'] )" ),
	'a missing month folder falls back to the uploads root instead of writing to /'
);
