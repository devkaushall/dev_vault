<?php
/**
 * Four things an office asks for once it has been using the system a while:
 * doing twenty records at once, taking back a mistake, keyboard shortcuts,
 * and reading the screens in its own language.
 *
 * The two that carry real risk are bulk and undo, so most of this file is
 * about what they must refuse to do.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Actions;
use EstatOS\Data\Listings;
use EstatOS\Data\Projects;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Undo;

estat_reset_world();
estat_login_owner();

/**
 * A listing with sensible values.
 *
 * @param string $title  Title.
 * @param string $status Post status.
 * @param float  $price  Price.
 * @return int
 */
function estat_it25_listing( string $title, string $status = 'publish', float $price = 5000000 ): int {
	$id = Listings::save(
		array(
			'title'         => $title,
			'offer'         => 'sale',
			'property_type' => 'apartment',
			'locality'      => 'Saket',
			'price'         => $price,
			'area'          => 900,
			'area_unit'     => 'sqft',
			'status'        => $status,
		)
	);

	return is_wp_error( $id ) ? 0 : (int) $id;
}

/**
 * Run a bulk action the way the browser posts it.
 *
 * @param string     $operation Operation key.
 * @param array<int> $ids       Record IDs.
 * @param array      $caps      Capabilities for the acting user.
 * @return string The redirect URL, decoded.
 */
function estat_it25_bulk( string $operation, array $ids, array $caps = array( 'read', 'estat_delete_listings' ) ): string {
	estat_login_with( 7, $caps );

	$_POST = array(
		'estat_action' => 'bulk_records',
		'bulk_action'  => $operation,
		'record_ids'   => array_map( 'strval', $ids ),
		'back_page'    => 'estat-listings',
		'estat_nonce'  => wp_create_nonce( 'estat_bulk_records' ),
	);

	$_REQUEST = $_POST;

	try {
		Actions::handle();
		$where = 'no redirect';
	} catch ( \Throwable $e ) {
		$where = $e->getMessage();
	}

	$_POST    = array();
	$_REQUEST = array();

	return urldecode( $where );
}

EstatTests::group( 'Several records at once' );

$one   = estat_it25_listing( 'Bulk home one' );
$two   = estat_it25_listing( 'Bulk home two' );
$three = estat_it25_listing( 'Bulk home three' );

EstatTests::ok( $one > 0 && $two > 0 && $three > 0, 'three listings exist' );

$result = estat_it25_bulk( 'trash', array( $one, $two, $three ) );

EstatTests::ok( false !== strpos( $result, 'estat_notice=saved' ), 'the bulk action succeeded' );
EstatTests::is( 'trash', get_post_status( $one ), 'the first went to the bin' );
EstatTests::is( 'trash', get_post_status( $two ), 'so did the second' );
EstatTests::is( 'trash', get_post_status( $three ), 'and the third' );

// Restoring must never bring something back live.
estat_it25_bulk( 'restore', array( $one, $two ) );

EstatTests::is( 'draft', get_post_status( $one ), 'restoring returns a record as a draft, not live' );
EstatTests::is( 'draft', get_post_status( $two ), 'and the same for the second' );

estat_it25_bulk( 'publish', array( $one ) );

EstatTests::is( 'publish', get_post_status( $one ), 'bulk publish works' );

estat_it25_bulk( 'draft', array( $one ) );

EstatTests::is( 'draft', get_post_status( $one ), 'and bulk unpublish works' );

EstatTests::group( 'Bulk is not a way round permission' );

// This is the whole risk of a bulk action: one tick box acting on records the
// user is not allowed to touch. Every record must be checked on its own.
estat_reset_world();
estat_login_owner();

$listing = estat_it25_listing( 'A listing anyone may bin' );
$project = (int) Projects::save(
	array(
		'title'  => 'A project only managers may touch',
		'status' => 'publish',
	)
);

EstatTests::ok( $listing > 0 && $project > 0, 'a listing and a project exist' );

// A user who may delete listings but has no project permission at all.
$message = estat_it25_bulk( 'trash', array( $listing, $project ), array( 'read', 'estat_delete_listings' ) );

EstatTests::is( 'trash', get_post_status( $listing ), 'the listing they may touch was binned' );
EstatTests::is( 'publish', get_post_status( $project ), 'the project they may NOT touch was left alone' );
EstatTests::ok(
	false !== strpos( $message, 'skipped' ),
	'and the office is told something was skipped rather than it failing silently'
);

// Nothing at all should happen for a user with no record permissions.
estat_reset_world();
estat_login_owner();
$lonely = estat_it25_listing( 'Nobody may touch this' );

estat_it25_bulk( 'trash', array( $lonely ), array( 'read' ) );

EstatTests::is( 'publish', get_post_status( $lonely ), 'a user with no delete permission changes nothing' );

EstatTests::group( 'Bulk refuses nonsense' );

estat_reset_world();
estat_login_owner();
$victim = estat_it25_listing( 'Still here afterwards' );

$no_action = estat_it25_bulk( '', array( $victim ) );

EstatTests::ok( false !== strpos( $no_action, 'estat_notice=error' ), 'no chosen action is an error' );
EstatTests::is( 'publish', get_post_status( $victim ), 'and nothing was changed' );

$made_up = estat_it25_bulk( 'delete_everything_forever', array( $victim ) );

EstatTests::ok( false !== strpos( $made_up, 'estat_notice=error' ), 'an invented action is refused' );
EstatTests::is( 'publish', get_post_status( $victim ), 'and still nothing was changed' );

$nothing_ticked = estat_it25_bulk( 'trash', array() );

EstatTests::ok( false !== strpos( $nothing_ticked, 'estat_notice=error' ), 'ticking nothing is an error' );

// Permanent deletion must not be reachable from a checkbox column at all.
$operations = array_keys( Actions::bulk_operations() );

EstatTests::ok( ! in_array( 'delete', $operations, true ), 'bulk cannot permanently delete' );
EstatTests::ok( ! in_array( 'erase', $operations, true ), 'nor erase' );
EstatTests::is( 4, count( $operations ), 'there are exactly four bulk operations' );

/*
 * A runaway request must be stopped before it times out halfway through,
 * which would leave the office unsure what actually happened.
 *
 * The count here is deliberately a fixed number rather than derived from
 * BULK_LIMIT: building the list from the constant made this test move with
 * it, so raising the limit to a million would still have passed.
 */
EstatTests::ok(
	Actions::BULK_LIMIT > 0 && Actions::BULK_LIMIT <= 200,
	'the bulk limit is a sane size (it is ' . Actions::BULK_LIMIT . ')'
);

$too_many = estat_it25_bulk( 'trash', range( 1000, 1400 ) );

EstatTests::ok( false !== strpos( $too_many, 'estat_notice=error' ), '401 records at once is refused outright' );

EstatTests::group( 'Taking back an edit' );

estat_reset_world();
estat_login_owner();

$home = estat_it25_listing( 'Undo test home', 'publish', 5000000 );

EstatTests::ok( ! Undo::available( $home ), 'a brand new record has nothing to undo' );

Listings::save(
	array(
		'title'         => 'Undo test home',
		'offer'         => 'sale',
		'property_type' => 'apartment',
		'locality'      => 'Saket',
		'price'         => 7500000,
		'area'          => 900,
		'area_unit'     => 'sqft',
		'status'        => 'publish',
	),
	$home
);

EstatTests::is( '7500000', (string) get_post_meta( $home, '_estat_price', true ), 'the edit took effect' );
EstatTests::ok( Undo::available( $home ), 'and there is now something to undo' );

EstatTests::ok( Undo::restore( $home ), 'the undo runs' );
EstatTests::is( '5000000', (string) get_post_meta( $home, '_estat_price', true ), 'the old price is back' );

// Undoing an undo has to work, or one wrong click is unrecoverable.
EstatTests::ok( Undo::available( $home ), 'the undo can itself be undone' );
EstatTests::ok( Undo::restore( $home ), 'the redo runs' );
EstatTests::is( '7500000', (string) get_post_meta( $home, '_estat_price', true ), 'and the newer price is back' );

EstatTests::group( 'Undo must never republish something' );

/*
 * The quiet danger. Somebody takes a property off the website deliberately,
 * then undoes an unrelated price edit. If undo restored the status too, the
 * property would silently go back live.
 */
$taken_off = estat_it25_listing( 'Deliberately taken offline', 'publish', 4000000 );

Listings::save(
	array(
		'title'         => 'Deliberately taken offline',
		'offer'         => 'sale',
		'property_type' => 'apartment',
		'locality'      => 'Saket',
		'price'         => 4400000,
		'area'          => 900,
		'area_unit'     => 'sqft',
		'status'        => 'publish',
	),
	$taken_off
);

wp_update_post(
	array(
		'ID'          => $taken_off,
		'post_status' => 'draft',
	)
);

EstatTests::is( 'draft', get_post_status( $taken_off ), 'it is off the website' );

Undo::restore( $taken_off );

EstatTests::is( '4000000', (string) get_post_meta( $taken_off, '_estat_price', true ), 'the price was undone' );
EstatTests::is( 'draft', get_post_status( $taken_off ), 'but it did NOT go back on the website' );

// Snapshots must not pile up without limit.
$busy = estat_it25_listing( 'Edited many times', 'draft', 1000000 );

for ( $i = 1; $i <= 12; $i++ ) {
	Listings::save(
		array(
			'title'         => 'Edited many times',
			'offer'         => 'sale',
			'property_type' => 'apartment',
			'locality'      => 'Saket',
			'price'         => 1000000 + ( $i * 100000 ),
			'area'          => 900,
			'area_unit'     => 'sqft',
			'status'        => 'draft',
		),
		$busy
	);
}

EstatTests::ok(
	count( Undo::history( $busy ) ) <= Undo::KEEP,
	'no more than ' . Undo::KEEP . ' snapshots are kept, so meta cannot grow without limit'
);

// Saving without changing anything must not push a useful snapshot out.
$before = count( Undo::history( $busy ) );

Undo::capture( $busy );
Undo::capture( $busy );

EstatTests::is( $before, count( Undo::history( $busy ) ), 'an unchanged save adds no snapshot' );

// Only known fields are captured, so undo cannot resurrect anything odd.
$fields = Undo::fields();

EstatTests::ok( isset( $fields[ PostTypes::LISTING ] ), 'listings have a captured field list' );
EstatTests::ok(
	! in_array( '_estat_completeness', $fields[ PostTypes::LISTING ], true ),
	'computed values are not captured, so undo cannot restore a stale score'
);

EstatTests::group( 'Keyboard shortcuts, without trapping anybody' );

$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/admin.js' );

EstatTests::ok( false !== strpos( $js, "function typing(" ), 'there is a check for whether the user is typing' );

/*
 * Defining the guard is not the same as using it. An earlier version of this
 * test only checked that typing() existed, so deleting the line that CALLS it
 * -- which makes every letter key fire a shortcut mid-sentence -- passed
 * cleanly. Pin the call, inside the key handler, with its early return.
 */
EstatTests::ok(
	(bool) preg_match( '/if \( typing\( document\.activeElement \) \) \{\s*return;\s*\}/', $js ),
	'the key handler actually calls that guard and gives up when the user is typing'
);
EstatTests::ok(
	(bool) preg_match( "/tag === 'input' \|\| tag === 'textarea' \|\| tag === 'select'/", $js ),
	'a shortcut never fires while a field is focused'
);
EstatTests::ok(
	false !== strpos( $js, 'isContentEditable' ),
	'including inside a rich text area'
);

// Every shortcut must have a visible equivalent, or it is hidden knowledge.
EstatTests::ok(
	false !== strpos( $js, "event.key === '?'" ),
	'there is a shortcut that lists the shortcuts'
);

$partials = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Admin/Screens/Partials.php' );

EstatTests::ok(
	false !== strpos( $partials, 'Press ? for shortcuts' ),
	'and the page says so on screen, so it is discoverable without knowing already'
);

// Ctrl/Cmd+S must not fight the browser when there is nothing to save.
EstatTests::ok(
	(bool) preg_match( '/if \( saveCurrentForm\(\) \) \{\s*event\.preventDefault\(\);/', $js ),
	'Ctrl+S only takes over the key when there really is a form to save'
);

// A stray "g" must not arm the go-to shortcut forever.
EstatTests::ok(
	false !== strpos( $js, 'pendingTimer = window.setTimeout' ),
	'a half-typed shortcut times out'
);

$admin_php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Admin/Admin.php' );

foreach ( array( 'today', 'listings', 'enquiries', 'addHome' ) as $target ) {
	EstatTests::ok(
		false !== strpos( $admin_php, "'" . $target . "'" ),
		'the shortcut destination "' . $target . '" is given to the browser'
	);
}

EstatTests::group( 'The office can read its screens in Hinglish' );

$po = (string) file_get_contents( dirname( __DIR__, 2 ) . '/languages/estat-os-en_IN_hinglish.po' );

preg_match_all( '/^msgid "((?:[^"\\\\]|\\\\.)*)"\s*\nmsgstr "((?:[^"\\\\]|\\\\.)*)"/m', $po, $pairs, PREG_SET_ORDER );

$total      = 0;
$translated = 0;

foreach ( $pairs as $pair ) {
	if ( '' === $pair[1] ) {
		continue; // The header entry.
	}
	++$total;
	if ( '' !== trim( $pair[2] ) ) {
		++$translated;
	}
}

EstatTests::ok( $total > 1000, 'the translation covers the whole product: ' . $total . ' strings' );
EstatTests::is( $total, $translated, 'every one of them is translated' );

// A translation is useless if WordPress cannot load the compiled file, and a
// hand-written .mo is easy to get subtly wrong.
$mo = dirname( __DIR__, 2 ) . '/languages/estat-os-en_IN_hinglish.mo';

EstatTests::ok( file_exists( $mo ), 'the compiled translation exists' );

/*
 * Read it with WordPress's own MO parser, not with anything of ours. A
 * hand-built .mo can look right and still be unreadable: an earlier one
 * wrote 0 as the hash address, which core rejects outright, and every
 * translation silently fell back to English.
 */
$pomo = '/tmp/wordpress/wp-includes/pomo/';

if ( ! class_exists( 'MO' ) && file_exists( $pomo . 'mo.php' ) ) {
	if ( ! function_exists( 'array_last' ) ) {
		/**
		 * Core helper the plural parser needs.
		 *
		 * @param array $array Array.
		 * @return mixed
		 */
		function array_last( $array ) {
			return $array ? $array[ count( $array ) - 1 ] : null;
		}
	}
	if ( ! function_exists( 'array_first' ) ) {
		/**
		 * Core helper the plural parser needs.
		 *
		 * @param array $array Array.
		 * @return mixed
		 */
		function array_first( $array ) {
			foreach ( $array as $value ) {
				return $value;
			}

			return null;
		}
	}

	require_once $pomo . 'translations.php';
	require_once $pomo . 'streams.php';
	require_once $pomo . 'mo.php';
}

if ( ! class_exists( 'MO' ) ) {
	EstatTests::ok( false, 'the WordPress MO parser is available to check the compiled file' );

	return;
}

$reader = new MO();

EstatTests::ok(
	$reader->import_from_file( $mo ),
	'WordPress itself can read the compiled file'
);
EstatTests::ok(
	count( $reader->entries ) > 1000,
	'and finds ' . count( $reader->entries ) . ' entries in it'
);

// Spot-check that real strings come back in Hinglish, not English.
foreach (
	array(
		'Add a Home',
		'Move to the bin',
		'Undo last edit',
		'Jump to the search box',
		'Box height',
	) as $english
) {
	$out = $reader->translate( $english );

	EstatTests::ok(
		$out !== $english && '' !== $out,
		'"' . $english . '" comes back as "' . $out . '"'
	);
}

// Business data must never be translated: only the interface is.
$language_php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/I18n/Language.php' );

EstatTests::ok(
	false !== strpos( $language_php, 'estat-os' ),
	'the language layer only ever switches the plugin text domain'
);
EstatTests::ok(
	false === strpos( $language_php, 'post_title' ) && false === strpos( $language_php, 'post_content' ),
	'it never touches the words the office typed in'
);
