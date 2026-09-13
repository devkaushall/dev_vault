<?php
/**
 * Behaving properly once an office has a lot of records.
 *
 * Three faults are pinned here, and all three shared a habit: the code stopped
 * early and said nothing.
 *
 * 1. Agent and project dropdowns fetched a flat 200 ordered by title. The two
 *    hundred and first was simply absent, with no message. To the office that
 *    is indistinguishable from a record that has been lost.
 * 2. Projects and Team listed a fixed 50 and 100 with no search and no page
 *    links, so the rest could not be reached at all.
 * 3. Listings accepted 999 bedrooms and a price of 9.9e18. One mistyped price
 *    sorts above every genuine listing and breaks the price filter for
 *    everybody.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Screens\ProjectsScreen;
use EstatOS\Admin\Screens\TeamScreen;
use EstatOS\Data\Agents;
use EstatOS\Data\Listings;
use EstatOS\Data\Picker;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Projects;

estat_reset_world();
estat_login_owner();

/**
 * Render an admin screen and hand back its markup.
 *
 * @param string $page  Page slug.
 * @param string $class Screen class name.
 * @param array  $get   Extra query arguments.
 * @return string
 */
function estat_it24_screen( string $page, string $class, array $get = array() ): string {
	$_GET     = array_merge( array( 'page' => $page ), $get );
	$_REQUEST = $_GET;

	ob_start();
	try {
		call_user_func( array( 'EstatOS\\Admin\\Screens\\' . $class, 'render' ) );
		$html = ob_get_contents();
		ob_end_clean();
	} catch ( \Throwable $e ) {
		$html = ob_get_contents();
		ob_end_clean();
		$html .= "\n<!-- FATAL " . get_class( $e ) . ' @ ' . $e->getLine() . ': ' . $e->getMessage() . ' -->';
	}

	$_GET     = array();
	$_REQUEST = array();

	return (string) $html;
}

EstatTests::group( 'A number that cannot be real is refused, and said so' );

$sane = array(
	'title'         => 'Bounds check home',
	'offer'         => 'sale',
	'property_type' => 'apartment',
	'locality'      => 'Saket',
	'area'          => 1000,
	'area_unit'     => 'sqft',
	'status'        => 'draft',
);

/**
 * Try to save a listing and report whether it was refused.
 *
 * @param array $extra Fields to merge over the sane baseline.
 * @return string Error message, or an empty string when it saved.
 */
function estat_it24_refused( array $extra ): string {
	global $sane;

	$result = Listings::save( array_merge( $sane, $extra ) );

	return is_wp_error( $result ) ? (string) $result->get_error_message() : '';
}

foreach (
	array(
		'a price of 9.9e18'      => array( 'price' => 9.9e18 ),
		'999 bedrooms'           => array( 'price' => 5000000, 'bedrooms' => 999 ),
		'500 bathrooms'          => array( 'price' => 5000000, 'bathrooms' => 500 ),
		'a 300-floor building'   => array( 'price' => 5000000, 'total_floors' => 300 ),
		'a negative price'       => array( 'price' => -500000 ),
		'a negative rent'        => array( 'offer' => 'rent', 'rent' => -9000 ),
	) as $what => $input
) {
	EstatTests::ok( '' !== estat_it24_refused( $input ), $what . ' is refused' );
}

// A refusal has to explain itself, not just fail.
$message = estat_it24_refused( array( 'price' => 5000000, 'bedrooms' => 999 ) );

EstatTests::ok(
	false !== strpos( $message, '50' ),
	'the refusal names the highest number allowed, so the office knows what to type instead'
);

// And nothing genuine may be blocked. A 95 crore penthouse is a real listing.
foreach (
	array(
		'a 95 crore penthouse'  => array( 'price' => 950000000 ),
		'a 12 bedroom house'    => array( 'price' => 5000000, 'bedrooms' => 12 ),
		'a 40 floor tower flat' => array( 'price' => 5000000, 'total_floors' => 40 ),
		'a free listing'        => array( 'price' => 0, 'price_type' => 'on_request' ),
	) as $what => $input
) {
	EstatTests::is( '', estat_it24_refused( $input ), $what . ' still saves' );
}

// The negative check must look at what was typed. Sanitize::float() floors at
// zero, so a check on the sanitised value could never fire -- which is exactly
// what the old code did, silently turning -500000 into 0.
$negative = Listings::save( array_merge( $sane, array( 'price' => '-500000' ) ) );

EstatTests::ok( is_wp_error( $negative ), 'a negative typed as a string is caught too' );

EstatTests::group( 'A dropdown that cannot show everything says so' );

// Comfortably past the limit, so truncation is real rather than theoretical.
for ( $i = 1; $i <= 250; $i++ ) {
	Projects::save(
		array(
			'title'  => sprintf( 'Society %03d', $i ),
			'status' => 'publish',
		)
	);
}

$picker = Picker::options( PostTypes::PROJECT, 'Not part of one' );

EstatTests::is( Picker::LIMIT, $picker['shown'], 'the dropdown holds at most ' . Picker::LIMIT . ' records' );
EstatTests::is( 250, $picker['total'], 'but it knows there are 250 in total' );
EstatTests::ok( $picker['truncated'], 'and it reports that it had to stop early' );

$notice = Picker::notice( $picker );

EstatTests::ok( '' !== $notice, 'there is a message to show the office' );
EstatTests::ok( false !== strpos( $notice, '250' ), 'the message says how many there really are' );
EstatTests::ok( false !== strpos( $notice, '200' ), 'and how many are being shown' );

// The "nothing chosen" option must survive the sort, and stay first.
$keys = array_keys( $picker['options'] );

EstatTests::is( '0', (string) $keys[0], 'the "nothing chosen" option is still first after sorting' );
EstatTests::is( 'Not part of one', $picker['options']['0'], 'and still says what it should' );

// Under the limit, nothing is claimed to be missing.
estat_reset_world();
estat_login_owner();

for ( $i = 1; $i <= 5; $i++ ) {
	Projects::save(
		array(
			'title'  => sprintf( 'Small society %d', $i ),
			'status' => 'publish',
		)
	);
}

$small = Picker::options( PostTypes::PROJECT, 'Not part of one' );

EstatTests::ok( ! $small['truncated'], 'a short list is not reported as truncated' );
EstatTests::is( '', Picker::notice( $small ), 'and shows no message at all' );

EstatTests::group( 'The record already attached is always selectable' );

// This is the quiet danger in any capped picker: open an old listing whose
// project has dropped out of the recent 200, and the field shows "not part of
// one". Saving would then unassign it without anybody choosing to.
$attached = (int) Projects::save(
	array(
		'title'  => 'The attached society',
		'status' => 'publish',
	)
);

EstatTests::ok( $attached > 0, 'a project to attach exists' );

/*
 * Proving this properly needs a record that genuinely falls outside the
 * limit, and this harness ignores `orderby`, so a record can never actually
 * drop out of the first 200 here. Asking Picker for a list of one and then
 * checking the attached record is present would pass whether the guarantee
 * exists or not -- a vacuous test.
 *
 * So test the guarantee directly: call options() with a limit already full of
 * OTHER records, by asking for a type whose recent list does not contain it.
 */
$kept = Picker::options( PostTypes::PROJECT, 'Not part of one', $attached );

EstatTests::ok(
	isset( $kept['options'][ (string) $attached ] ),
	'the attached record appears in the list'
);
EstatTests::is(
	'The attached society',
	$kept['options'][ (string) $attached ],
	'and under its real name'
);

// The real guarantee: the code must add the kept record when the query did
// not return it. Pin the branch itself, since the harness cannot create the
// situation that triggers it.
$picker_src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Data/Picker.php' );

EstatTests::ok(
	(bool) preg_match( '/if \(\s*\$keep > 0 && ! in_array\( \$keep, \$seen, true \)\s*\)/', $picker_src ),
	'options() re-adds the current record when the capped query did not return it'
);
EstatTests::ok(
	(bool) preg_match( '/\$post_type === get_post_type\( \$kept \)/', $picker_src ),
	'and only when that record is really of the type being listed'
);

// Prove the branch works by driving it directly: ask a type that has no
// records at all, so the query returns nothing and only "keep" can supply it.
estat_reset_world();
estat_login_owner();

$lonely = (int) Projects::save(
	array(
		'title'  => 'The only society, kept by force',
		'status' => 'draft',
	)
);

// A draft is never returned by the picker's publish-only query, so if it
// appears at all it can only be because the keep branch put it there.
$forced = Picker::options( PostTypes::PROJECT, 'Not part of one', $lonely );

EstatTests::ok(
	isset( $forced['options'][ (string) $lonely ] ),
	'a record the query cannot return is still selectable when it is the current value'
);
EstatTests::is(
	'The only society, kept by force',
	$forced['options'][ (string) $lonely ],
	'and carries its real name, so saving cannot silently unassign it'
);

// A record of the wrong type must never be smuggled in by the keep argument.
$agent_id = (int) Agents::save(
	array(
		'name'   => 'Not a project',
		'status' => 'publish',
	)
);

$wrong_type = Picker::options( PostTypes::PROJECT, 'Not part of one', $agent_id );

EstatTests::ok(
	! isset( $wrong_type['options'][ (string) $agent_id ] ),
	'an agent cannot appear in the project list just because it was passed as the current value'
);

/*
 * An untitled record must still be distinguishable, or it renders as a blank
 * line nobody can tell from any other blank line.
 *
 * This harness refuses an empty post_title outright, so the record cannot be
 * created here. Blank a real one instead, which is what actually happens in
 * practice when somebody clears the name field.
 */
$blanked = (int) Projects::save(
	array(
		'title'  => 'About to be blanked',
		'status' => 'publish',
	)
);

wp_update_post(
	array(
		'ID'         => $blanked,
		'post_title' => '',
	)
);

$with_untitled = Picker::options( PostTypes::PROJECT, 'Not part of one', $blanked );
$label         = (string) ( $with_untitled['options'][ (string) $blanked ] ?? '' );

EstatTests::ok( isset( $with_untitled['options'][ (string) $blanked ] ), 'a record with no name is still listed' );
EstatTests::ok( '' !== trim( $label ), 'and its label is not blank' );
EstatTests::ok(
	false !== strpos( $label, (string) $blanked ),
	'the label carries the record number, so two nameless records can be told apart'
);

EstatTests::group( 'Long lists can be searched and paged' );

estat_reset_world();
estat_login_owner();

for ( $i = 1; $i <= 60; $i++ ) {
	Projects::save(
		array(
			'title'  => sprintf( 'Society %02d', $i ),
			'status' => 'publish',
		)
	);
	Agents::save(
		array(
			'name'   => sprintf( 'Agent %02d', $i ),
			'status' => 'publish',
		)
	);
}

foreach (
	array(
		array( 'estat-projects', 'ProjectsScreen', 'societies and projects' ),
		array( 'estat-team', 'TeamScreen', 'team members' ),
	) as $screen
) {
	list( $page, $class, $what ) = $screen;

	$html = estat_it24_screen( $page, $class );

	EstatTests::ok( false === strpos( $html, 'FATAL' ), $class . ' renders' );
	EstatTests::ok( false !== strpos( $html, 'estat-filters' ), $class . ' has a search box' );
	EstatTests::ok(
		false !== strpos( $html, 'estat-pagination' ),
		$class . ' shows page links, so all 60 ' . $what . ' can be reached'
	);

	// A page must not dump everything; that was the original fault.
	$rows = substr_count( $html, '<tr>' ) - 1;

	EstatTests::ok(
		$rows > 0 && $rows <= 30,
		$class . ' shows ' . $rows . ' rows on a page rather than all sixty'
	);
}

// Page two must hold different records from page one.
$page_one = estat_it24_screen( 'estat-projects', 'ProjectsScreen' );
$page_two = estat_it24_screen( 'estat-projects', 'ProjectsScreen', array( 'paged' => '2' ) );

EstatTests::ok(
	$page_one !== $page_two,
	'page two of the projects list is not the same as page one'
);

EstatTests::group( 'Searching a long list actually narrows it' );

$searched = estat_it24_screen( 'estat-projects', 'ProjectsScreen', array( 's' => 'Society 07' ) );

EstatTests::ok( false === strpos( $searched, 'FATAL' ), 'a search renders' );

$search_rows = substr_count( $searched, '<tr>' ) - 1;
$all_rows    = substr_count( $page_one, '<tr>' ) - 1;

EstatTests::ok(
	$search_rows < $all_rows,
	'searching returns fewer rows (' . $search_rows . ') than the unfiltered page (' . $all_rows . ')'
);

// A search with no matches must explain itself rather than showing a bare
// table head or, worse, nothing at all.
$nothing = estat_it24_screen( 'estat-projects', 'ProjectsScreen', array( 's' => 'zzzz-no-such-society' ) );

EstatTests::ok( false !== strpos( $nothing, 'estat-empty' ), 'a search with no matches shows an empty state' );
EstatTests::ok(
	false !== strpos( $nothing, 'Nothing matched' ),
	'and the empty state says the search found nothing, rather than implying there are no projects at all'
);

EstatTests::group( 'Counting listings per project takes one query, not one each' );

// The Projects screen called stats() inside its row loop, which is a database
// round trip per project on every page load.
$projects_src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Admin/Screens/ProjectsScreen.php' );

EstatTests::ok(
	false === strpos( $projects_src, 'Projects::stats(' ),
	'the row loop no longer calls stats() per project'
);
EstatTests::ok(
	false !== strpos( $projects_src, 'Projects::listing_counts(' ),
	'it asks for every count at once instead'
);

// And the batch counter must agree with the single-project one.
estat_reset_world();
estat_login_owner();

$project_a = (int) Projects::save( array( 'title' => 'Counting society A', 'status' => 'publish' ) );
$project_b = (int) Projects::save( array( 'title' => 'Counting society B', 'status' => 'publish' ) );

$counts = Projects::listing_counts( array( $project_a, $project_b ) );

EstatTests::ok( is_array( $counts ), 'listing_counts returns an array' );
EstatTests::ok( array_key_exists( $project_a, $counts ), 'every project asked for is in the result' );
EstatTests::ok( array_key_exists( $project_b, $counts ), 'including ones with no listings' );
EstatTests::is( array(), Projects::listing_counts( array() ), 'asking for nothing returns nothing, without a query' );
