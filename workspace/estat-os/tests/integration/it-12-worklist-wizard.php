<?php
/**
 * The "what to do next" list, the step-by-step add form, and one shared
 * look for every list screen.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Actions;
use EstatOS\Admin\Screens\AddHome;
use EstatOS\Admin\Screens\Enquiries;
use EstatOS\Admin\Screens\ListingsScreen;
use EstatOS\Admin\Screens\Today;
use EstatOS\Admin\Screens\VisitsScreen;
use EstatOS\Data\Highlights;
use EstatOS\Data\Listings;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Worklist;
use EstatOS\Leads\Leads;
use EstatOS\Leads\Visits;

estat_reset_world();
estat_login_owner();

/* ============================================ What to do next */

EstatTests::group( 'Integration: an empty office is told so plainly' );

EstatTests::is( array(), Worklist::tasks(), 'a brand new office has no jobs waiting' );

$_GET = array( 'page' => 'estat-today' );
ob_start();
Today::render();
$today = (string) ob_get_clean();

EstatTests::ok( false !== strpos( $today, 'What to do next' ), 'the dashboard leads with what to do' );
EstatTests::ok( false !== strpos( $today, 'Nothing needs you right now' ), 'an empty office is reassured, not shown a blank box' );
EstatTests::ok( false !== strpos( $today, 'estat-add-home' ), 'and is offered something useful to do instead' );

/* ------------------------------------------------- Real work */

EstatTests::group( 'Integration: real work appears as jobs' );

$property = (int) Listings::save(
	array(
		'title'         => 'Saket flat with a park view',
		'offer'         => 'sale',
		'property_type' => 'apartment',
		'locality'      => 'Saket',
		'price'         => 5000000,
		'area'          => 1000,
		'area_unit'     => 'sqft',
		'status'        => 'publish',
	)
);

$lead = (int) Leads::create(
	array(
		'name'       => 'Ramesh Kumar',
		'phone'      => '9876543210',
		'listing_id' => $property,
	)
);

$tasks = Worklist::tasks();
EstatTests::ok( count( $tasks ) > 0, 'a new enquiry creates a job' );

$titles = array_column( $tasks, 'title' );
EstatTests::ok(
	(bool) preg_grep( '/Ramesh Kumar/', $titles ),
	'the job names the person, not a record number'
);

foreach ( $tasks as $task ) {
	foreach ( array( 'urgency', 'tone', 'icon', 'title', 'detail', 'action', 'url' ) as $key ) {
		EstatTests::ok( array_key_exists( $key, $task ), 'every job describes its "' . $key . '"' );
	}
	EstatTests::ok( '' !== trim( (string) $task['title'] ), 'no job has a blank title' );
	EstatTests::ok( '' !== trim( (string) $task['url'] ), 'every job links somewhere' );
	EstatTests::ok( '' !== trim( (string) $task['action'] ), 'every job says what the button does' );
}

/* --------------------------------------------- Urgency order */

EstatTests::group( 'Integration: the most urgent job comes first' );

// An overdue follow-up is the costliest thing to forget.
Leads::update(
	$lead,
	array(
		'status'      => 'contacted',
		'followup_at' => gmdate( 'Y-m-d H:i:s', time() - ( 86400 * 2 ) ),
	)
);

Visits::schedule(
	array(
		'lead_id'      => $lead,
		'listing_id'   => $property,
		'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
	)
);

$tasks = Worklist::tasks();

EstatTests::ok( count( $tasks ) >= 2, 'both the overdue call and today\'s visit are listed' );
EstatTests::ok(
	false !== strpos( (string) $tasks[0]['title'], 'Ramesh Kumar' ),
	'the overdue follow-up is the very first job'
);
EstatTests::is( 'danger', $tasks[0]['tone'], 'and it is marked as the urgent one' );

$urgencies = array_column( $tasks, 'urgency' );
$sorted    = $urgencies;
sort( $sorted );
EstatTests::is( $sorted, $urgencies, 'jobs are always in urgency order' );

EstatTests::ok(
	(bool) preg_grep( '/Site visit/', array_column( $tasks, 'title' ) ),
	'today\'s visit is listed'
);

// A visit next month is not today's problem.
Visits::schedule(
	array(
		'lead_id'      => $lead,
		'listing_id'   => $property,
		'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + ( 86400 * 30 ) ),
	)
);

$visit_jobs = array_filter(
	Worklist::tasks(),
	static function ( array $task ): bool {
		return false !== strpos( (string) $task['title'], 'Site visit' );
	}
);
EstatTests::is( 1, count( $visit_jobs ), 'a visit next month does not clutter today\'s list' );

/* ------------------------------------------------- Bounded */

EstatTests::group( 'Integration: the list stays short and fast' );

for ( $i = 0; $i < 30; $i++ ) {
	Leads::create(
		array(
			'name'  => 'Walk-in ' . $i,
			'phone' => '99999000' . $i,
		)
	);
}

$tasks = Worklist::tasks();
EstatTests::ok( count( $tasks ) <= Worklist::LIMIT, 'the list never grows past its limit however busy the office is' );
EstatTests::ok( count( Worklist::tasks( 3 ) ) <= 3, 'a smaller limit is honoured' );

EstatTests::ok(
	(bool) preg_grep( '/more enquiries are waiting/', array_column( $tasks, 'title' ) ),
	'when there is more than fits, the office is told how many'
);

/* ------------------------------------------- Permissions */

EstatTests::group( 'Integration: people only see jobs they may act on' );

estat_login_with( 720, array( 'estat_manage_listings' ) );
$limited = Worklist::tasks();

foreach ( $limited as $task ) {
	EstatTests::ok(
		false === strpos( (string) $task['url'], 'estat-enquiries' ),
		'someone who cannot see enquiries is not shown enquiry jobs'
	);
}

estat_login_owner();

/* ==================================== Step-by-step add form */

EstatTests::group( 'Integration: adding a home is broken into steps' );

$_GET = array( 'page' => 'estat-add-home' );
ob_start();
AddHome::render();
$form = (string) ob_get_clean();

EstatTests::ok( false !== strpos( $form, 'estat-wizard' ), 'the add form runs as a step-by-step form' );
EstatTests::is( 4, substr_count( $form, 'estat-wizard-panel' ), 'there are four steps' );

foreach ( array( 'basics', 'price', 'photo', 'highlights' ) as $step ) {
	EstatTests::ok( false !== strpos( $form, 'data-step="' . $step . '"' ), 'there is a "' . $step . '" step' );
}

EstatTests::ok( false !== strpos( $form, 'estat-wizard-next' ), 'there is a Next button' );
EstatTests::ok( false !== strpos( $form, 'estat-wizard-back' ), 'there is a Back button' );

// Every field must survive the split — losing one would silently lose data.
foreach ( array( 'title', 'offer', 'property_type', 'price', 'area', 'area_unit', 'bedrooms', 'locality', 'cover_id' ) as $field ) {
	EstatTests::ok(
		false !== strpos( $form, 'name="' . $field . '"' ),
		'the "' . $field . '" box is still on the form after splitting it into steps'
	);
}

EstatTests::is( count( Highlights::all() ), substr_count( $form, 'name="highlights[]"' ), 'all the highlight tick boxes survived the split' );

// The token's value is a hash, so look for the field rather than the action name.
EstatTests::ok( false !== strpos( $form, 'name="estat_nonce"' ), 'the form still carries its security token' );
EstatTests::ok( false !== strpos( $form, 'value="add_home"' ), 'the form still tells the plugin what it is doing' );
EstatTests::is( 1, substr_count( $form, '<form ' ), 'it is still one single form, so it posts in one go' );

// Opening and closing tags must balance, or the page would break.
EstatTests::is(
	substr_count( $form, '<section' ),
	substr_count( $form, '</section>' ),
	'the step panels are opened and closed properly'
);

/* ------------------------------- Saving still works unchanged */

EstatTests::group( 'Integration: the steps did not break saving' );

$_REQUEST = $_POST = array(
	'estat_action'    => 'add_home',
	'estat_nonce'     => wp_create_nonce( 'estat_add_home' ),
	'title'           => '3 BHK with park view in Saket',
	'offer'           => 'sale',
	'property_type'   => 'apartment',
	'locality'        => 'Green Park',
	'price'           => '7500000',
	'area'            => '1400',
	'area_unit'       => 'sqft',
	'bedrooms'        => '3',
	'estat_save_mode' => 'publish',
	'highlights'      => array( 'corner_plot', 'park_facing', 'lift' ),
);

try {
	Actions::handle();
} catch ( Throwable $e ) {
	$e->getMessage();
}

$made = 0;
foreach ( (array) get_posts( array( 'post_type' => PostTypes::LISTING, 'numberposts' => -1 ) ) as $post ) {
	if ( '3 BHK with park view in Saket' === $post->post_title ) {
		$made = (int) $post->ID;
	}
}

EstatTests::ok( $made > 0, 'a property posted from the step form is created' );
EstatTests::is( 'publish', get_post_status( $made ), 'and published as asked' );
EstatTests::is( '7500000', (string) get_post_meta( $made, '_estat_price', true ), 'the price from step two is saved' );
EstatTests::is( '1400', (string) get_post_meta( $made, '_estat_area_sqft', true ), 'the size from step two is saved' );
EstatTests::is( '3', (string) get_post_meta( $made, '_estat_bedrooms', true ), 'the bedrooms from step two are saved' );
EstatTests::is(
	array( 'corner_plot', 'park_facing', 'lift' ),
	Highlights::get( $made ),
	'the highlights from step four are saved'
);

$localities = wp_get_object_terms( $made, 'estat_locality', array( 'fields' => 'names' ) );
EstatTests::ok( in_array( 'Green Park', (array) $localities, true ), 'the locality from step one is saved' );

/* ============================== One shared look for lists */

EstatTests::group( 'Integration: every list screen looks the same' );

$screens = array(
	'estat-listings'  => ListingsScreen::class,
	'estat-enquiries' => Enquiries::class,
	'estat-visits'    => VisitsScreen::class,
);

foreach ( $screens as $slug => $class ) {
	$_GET = array( 'page' => $slug );
	ob_start();
	call_user_func( array( $class, 'render' ) );
	$html = (string) ob_get_clean();

	EstatTests::ok( false !== strpos( $html, 'estat-filters' ), $slug . ' uses the shared search and filter bar' );
	EstatTests::ok( false !== strpos( $html, 'estat-filter-count' ), $slug . ' says how many results there are' );
	EstatTests::ok( false !== strpos( $html, 'Show' ), $slug . ' has the same Show button as the others' );
}

// The shared bar must not have broken the filters themselves.
$_GET = array( 'page' => 'estat-listings', 'status' => 'draft' );
ob_start();
ListingsScreen::render();
$drafts = (string) ob_get_clean();
EstatTests::ok( false !== strpos( $drafts, 'selected' ), 'the chosen filter stays chosen after searching' );

$_GET = array( 'page' => 'estat-enquiries', 's' => 'Ramesh' );
ob_start();
Enquiries::render();
$found = (string) ob_get_clean();
EstatTests::ok( false !== strpos( $found, 'Ramesh' ), 'searching enquiries still finds the person' );

$_GET = array( 'page' => 'estat-enquiries', 's' => 'zzzznothing' );
ob_start();
Enquiries::render();
$none = (string) ob_get_clean();
EstatTests::ok( false === strpos( $none, 'Ramesh Kumar' ), 'and a search with no matches shows nothing instead of everything' );

// A hostile search term must not be able to run anything.
$_GET = array( 'page' => 'estat-listings', 's' => '<script>alert(1)</script>' );
ob_start();
ListingsScreen::render();
$hostile = (string) ob_get_clean();
EstatTests::ok( false === strpos( $hostile, '<script>alert' ), 'a hostile search term cannot run code in the filter bar' );

$_GET  = array();
$_POST = array();
