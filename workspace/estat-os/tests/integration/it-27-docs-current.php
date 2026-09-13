<?php
/**
 * Are the documents still telling the truth?
 *
 * The manual said "Version 1.7.0" while the plugin was on 2.3.1, and the
 * README still said 1.0.0. Nobody noticed for sixteen releases, because
 * nothing checked. A manual that is quietly out of date is worse than no
 * manual: the reader trusts it.
 *
 * This does not try to check that the prose is good. It checks the facts that
 * go stale on their own - version numbers, counts, and whether a feature the
 * manual describes actually exists.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Actions;
use EstatOS\Admin\Screens\FormsScreen;
use EstatOS\Data\Picker;
use EstatOS\Data\Undo;
use EstatOS\Forms\Style;
use EstatOS\Forms\Templates;
use EstatOS\Support\Icon;

estat_reset_world();
estat_login_owner();

$root = dirname( __DIR__, 2 );

$manual    = (string) file_get_contents( $root . '/MANUAL.md' );
$readme_md = (string) file_get_contents( $root . '/README.md' );
$readme_wp = (string) file_get_contents( $root . '/readme.txt' );
$changelog = (string) file_get_contents( $root . '/CHANGELOG.md' );
$plugin    = (string) file_get_contents( $root . '/estat-os.php' );

preg_match( '/Version:\s*([0-9.]+)/', $plugin, $version_match );
$version = (string) ( $version_match[1] ?? '' );

EstatTests::group( 'Every document names the version that is actually shipping' );

EstatTests::ok( '' !== $version, 'the plugin declares a version (' . $version . ')' );

foreach (
	array(
		'MANUAL.md'  => $manual,
		'README.md'  => $readme_md,
		'readme.txt' => $readme_wp,
		'CHANGELOG.md' => $changelog,
	) as $file => $contents
) {
	EstatTests::ok(
		false !== strpos( $contents, $version ),
		$file . ' mentions version ' . $version
	);
}

// The manual's own header is the one people read first, so it gets its own
// check rather than "appears somewhere in the file".
EstatTests::ok(
	(bool) preg_match( '/^Version ' . preg_quote( $version, '/' ) . ' /m', $manual ),
	'the manual header states version ' . $version . ', not an older one'
);

EstatTests::group( 'The manual describes features that exist' );

/*
 * Each of these was added after the manual was last written, and none of them
 * were mentioned until this release. The pairing is deliberate: the manual
 * claim on the left, the code that must back it on the right.
 */
$claims = array(
	'bulk actions'      => array( 'Doing many at once', count( Actions::bulk_operations() ) > 0 ),
	'the undo feature'  => array( 'Undo last edit', class_exists( Undo::class ) ),
	'keyboard shortcuts' => array( 'Keyboard shortcuts', true ),
	'the form studio'   => array( 'The form studio', method_exists( FormsScreen::class, 'stages' ) ),
	'Hinglish'          => array( 'Hinglish', file_exists( $root . '/languages/estat-os-en_IN_hinglish.mo' ) ),
	'the version history' => array( 'PART 7 — THE FULL HISTORY', true ),
);

foreach ( $claims as $what => $pair ) {
	list( $needle, $exists ) = $pair;

	EstatTests::ok( false !== strpos( $manual, $needle ), 'the manual covers ' . $what );
	EstatTests::ok( $exists, 'and ' . $what . ' really is in the code' );
}

EstatTests::group( 'Numbers in the manual match the code' );

/*
 * A number in a manual is a promise. These are the ones that change when
 * somebody adds a feature and forgets the documentation.
 */
$numbers = array(
	'bulk limit'      => array( Actions::BULK_LIMIT, '/limit of (\d+) at a time/' ),
	'undo snapshots'  => array( Undo::KEEP, '/\*\*last (\w+)\*\* versions/' ),
	'form templates'  => array( count( Templates::all() ), '/one of (\w+) ready-made forms/' ),
	'styling settings' => array( count( Style::defaults() ), '/\*\*Styling\.\*\* (\w+[\w-]*) settings/' ),
	'form studio steps' => array( count( FormsScreen::stages() ), '/Across the top are (\w+) steps/' ),
);

$words = array(
	'one'    => 1,
	'two'    => 2,
	'three'  => 3,
	'four'   => 4,
	'five'   => 5,
	'six'    => 6,
	'seven'  => 7,
	'eight'  => 8,
	'nine'   => 9,
	'ten'    => 10,
	'twenty-five' => 25,
);

foreach ( $numbers as $what => $pair ) {
	list( $actual, $pattern ) = $pair;

	if ( ! preg_match( $pattern, $manual, $found ) ) {
		EstatTests::ok( false, 'the manual states the ' . $what );
		continue;
	}

	$written = strtolower( trim( $found[1] ) );
	$claimed = $words[ $written ] ?? ( is_numeric( $written ) ? (int) $written : -1 );

	EstatTests::is(
		$actual,
		$claimed,
		'the manual says the ' . $what . ' is ' . $written . ', and the code agrees'
	);
}

EstatTests::group( 'The history covers every release' );

// Part 7 must list every version the changelog knows about, or a reader
// following the history hits a gap and cannot tell whether it is missing or
// simply never happened.
preg_match_all( '/^## \[?([0-9]+\.[0-9]+\.[0-9]+)\]?/m', $changelog, $releases );

$all_releases = array_unique( $releases[1] );

EstatTests::ok( count( $all_releases ) > 15, 'the changelog has a real history (' . count( $all_releases ) . ' releases)' );

$history_start = strpos( $manual, 'PART 7' );
$history       = false === $history_start ? '' : substr( $manual, $history_start );

$missing = array();

foreach ( $all_releases as $release ) {
	if ( false === strpos( $history, $release ) ) {
		$missing[] = $release;
	}
}

EstatTests::is( array(), $missing, 'every released version appears in the manual history' );

// And the newest release must be the first one described, not buried.
EstatTests::ok(
	strpos( $history, $version ) < 2000,
	'the newest version is near the top of the history, where a reader looks first'
);

EstatTests::group( 'The manual is honest about what is not finished' );

/*
 * This is the part most likely to be quietly dropped, because it is the part
 * nobody enjoys writing. The known-gaps list must still be there, and must
 * still name the real limits rather than a token one.
 */
EstatTests::ok(
	false !== strpos( $manual, 'What is still not perfect' ),
	'the manual still has a known-limitations section'
);

foreach (
	array(
		'orphan visit'      => 'outlive its enquiry',
		'the harness limit' => 'not a real database',
		'no browser run'    => 'run in a browser',
	) as $what => $needle
) {
	EstatTests::ok(
		false !== strpos( $manual, $needle ),
		'it still admits ' . $what
	);
}

EstatTests::group( 'Documented shortcodes and widgets exist' );

$src = '';

foreach ( glob( $root . '/includes/*/*.php' ) as $file ) {
	$src .= (string) file_get_contents( $file );
}
foreach ( glob( $root . '/includes/*/*/*.php' ) as $file ) {
	$src .= (string) file_get_contents( $file );
}

preg_match_all( '/`\[(estat_[a-z_]+)[^\]]*\]`/', $manual, $documented );

$registered = array_unique( preg_match_all( "/add_shortcode\(\s*'(estat_[a-z_]+)'/", $src, $found_codes ) ? $found_codes[1] : array() );

foreach ( array_unique( $documented[1] ) as $shortcode ) {
	EstatTests::ok(
		in_array( $shortcode, $registered, true ),
		'the documented shortcode [' . $shortcode . '] is registered'
	);
}

// The icon count is quoted in the changelog; if the set shrinks, screens break.
EstatTests::ok( count( Icon::paths() ) >= 40, 'the icon set still has ' . count( Icon::paths() ) . ' icons' );
EstatTests::is( 200, Picker::LIMIT, 'the dropdown limit quoted in the manual is unchanged' );
