<?php
/**
 * Property-card highlights, and the blogs/articles/insights screen.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Actions;
use EstatOS\Admin\Screens\AddHome;
use EstatOS\Admin\Screens\InsightsScreen;
use EstatOS\Data\Highlights;
use EstatOS\Data\Insights;
use EstatOS\Data\Listings;
use EstatOS\Data\PostTypes;
use EstatOS\Frontend\Components;
use EstatOS\Security\Roles;

estat_reset_world();
estat_login_owner();

/* ------------------------------------------------------- The catalogue */

EstatTests::group( 'Integration: the highlight catalogue' );

$all = Highlights::all();

EstatTests::ok( count( $all ) >= 30, 'there are enough highlights to describe a real property (got ' . count( $all ) . ')' );

$bad = array();
foreach ( $all as $key => $item ) {
	if ( '' === (string) $key || empty( $item['label'] ) || empty( $item['icon'] ) || ! isset( $item['priority'] ) ) {
		$bad[] = (string) $key;
	}
	if ( isset( $item['icon'] ) && '' === trim( (string) $item['icon'] ) ) {
		$bad[] = (string) $key . ' (blank icon)';
	}
}
EstatTests::is( array(), $bad, 'every highlight has a label, a visible icon and a priority' );

$groups = Highlights::groups();
EstatTests::ok( count( $groups ) >= 5, 'highlights are split into groups the seller can scan' );

$grouped = 0;
foreach ( Highlights::by_group() as $group => $items ) {
	EstatTests::ok( isset( $groups[ $group ] ), 'group "' . $group . '" has a name the seller can read' );
	$grouped += count( $items );
}
EstatTests::is( count( $all ), $grouped, 'no highlight is stranded outside a group' );

/* ------------------------------------------------------- Saving ticks */

EstatTests::group( 'Integration: ticking highlights on a listing' );

$listing_input = array(
		'title'         => 'Corner flat in Saket',
		'offer'         => 'sale',
		'property_type' => 'apartment',
		'locality'      => 'Saket',
		'price'         => 6500000,
		'area'          => 1200,
		'area_unit'     => 'sqft',
		'bedrooms'      => 3,
		'bathrooms'     => 2,
		'status'        => 'publish',
		'highlights'    => array( 'corner_plot', 'park_facing', 'near_metro', 'clear_title', 'gym' ),
);

$listing = Listings::save( $listing_input );

EstatTests::ok( ! is_wp_error( $listing ), 'a listing saves with highlights attached' );
$listing = (int) $listing;

EstatTests::is(
	array( 'corner_plot', 'park_facing', 'near_metro', 'clear_title', 'gym' ),
	Highlights::get( $listing ),
	'every ticked highlight is stored'
);

$saved = Listings::save(
	array_merge( $listing_input, array( 'highlights' => array( 'corner_plot', 'not_a_real_highlight', '<script>x</script>' ) ) ),
	$listing
);
EstatTests::ok( ! is_wp_error( $saved ), 'the listing still saves when junk is submitted' );
EstatTests::is(
	array( 'corner_plot' ),
	Highlights::get( $listing ),
	'invented highlight keys are thrown away instead of stored'
);

// Put the real set back for the card tests.
Listings::save( $listing_input, $listing );

/* ------------------------------------------------------- Card chips */

EstatTests::group( 'Integration: what shows on the property card' );

$chips = Highlights::for_card( $listing );

EstatTests::ok( count( $chips ) <= Highlights::CARD_LIMIT, 'the card never shows more chips than the limit' );
EstatTests::ok( count( $chips ) > 0, 'a card with ticks is not empty' );

$labels = array();
foreach ( $chips as $chip ) {
	$labels[] = $chip['label'];
	EstatTests::ok( '' !== trim( (string) $chip['icon'] ), 'chip "' . $chip['label'] . '" has a visible icon' );
}

EstatTests::ok( in_array( 'Corner plot', $labels, true ), 'the first ticked highlight reaches the card' );
EstatTests::ok(
	! in_array( '3 Beds', $labels, true ),
	'ticked highlights are not pushed off the card by automatic facts'
);

// A listing with nothing ticked still says something useful.
$plain = Listings::save(
	array(
		'title'         => 'Plain flat',
		'offer'         => 'sale',
		'property_type' => 'apartment',
		'locality'      => 'Saket',
		'price'         => 4000000,
		'area'          => 900,
		'area_unit'     => 'sqft',
		'bedrooms'      => 2,
		'bathrooms'     => 1,
		'status'        => 'publish',
	)
);
$plain       = (int) $plain;
$plain_chips = Highlights::for_card( $plain );

EstatTests::ok( count( $plain_chips ) > 0, 'a card with no ticks falls back to bedrooms and size, never blank' );

$plain_labels = array();
foreach ( $plain_chips as $chip ) {
	$plain_labels[] = $chip['label'];
}
EstatTests::ok(
	(bool) preg_grep( '/Bed/', $plain_labels ),
	'the fallback mentions bedrooms'
);

/* ------------------------------------------------------- Rendering */

EstatTests::group( 'Integration: highlights render on the card' );

$html = Components::card( $listing );

EstatTests::ok( false !== strpos( $html, 'estat-chips' ), 'the card markup contains the chip list' );
EstatTests::ok( false !== strpos( $html, 'Corner plot' ), 'the ticked highlight is visible in the HTML' );
EstatTests::ok(
	false !== strpos( $html, 'aria-hidden' ),
	'the decorative icon is hidden from screen readers'
);

$xss = Listings::save(
	array(
		'title'         => '<script>alert(1)</script> House',
		'offer'         => 'sale',
		'property_type' => 'apartment',
		'locality'      => 'Saket',
		'price'         => 100000,
		'area'          => 500,
		'area_unit'     => 'sqft',
		'status'        => 'publish',
		'highlights'    => array( 'corner_plot' ),
	)
);
$xss_html = Components::card( (int) $xss );
EstatTests::ok( false === strpos( $xss_html, '<script>alert' ), 'a hostile title cannot break out through the card' );

/* ------------------------------------------------------- The API shape */

EstatTests::group( 'Integration: highlights in the data shape' );

$data = Listings::to_array( $listing );

EstatTests::ok( isset( $data['highlights'] ), 'the listing data exposes the raw ticks' );
EstatTests::ok( isset( $data['chips'] ), 'the listing data exposes ready-to-render chips' );
EstatTests::ok( is_array( $data['chips'] ), 'chips are an array' );
EstatTests::ok( count( $data['chips'] ) <= Highlights::CARD_LIMIT, 'the API honours the same card limit' );

/* ------------------------------------------------------- The editor UI */

EstatTests::group( 'Integration: the seller can tick highlights' );

$_GET = array( 'page' => 'estat-add-home' );
ob_start();
AddHome::render();
$form = (string) ob_get_clean();

EstatTests::ok( false !== strpos( $form, 'name="highlights[]"' ), 'the add form offers the highlight tick boxes' );
EstatTests::ok(
	substr_count( $form, 'name="highlights[]"' ) === count( $all ),
	'every highlight in the catalogue is offered (got ' . substr_count( $form, 'name="highlights[]"' ) . ')'
);
EstatTests::ok( false !== strpos( $form, 'estat-highlights-preview' ), 'the form previews what will land on the card' );
EstatTests::ok( false !== strpos( $form, 'data-card-limit' ), 'the preview knows how many chips fit' );

/* ------------------------------------------------------- Saving from the form */

EstatTests::group( 'Integration: ticks survive the admin form post' );

$_REQUEST = array();
$_POST    = array(
	'estat_action'    => 'add_home',
	'estat_nonce'     => wp_create_nonce( 'estat_add_home' ),
	'title'           => 'Posted through the form',
	'offer'           => 'sale',
	'property_type'   => 'apartment',
	'locality'        => 'Saket',
	'price'           => '7000000',
	'area'            => '1100',
	'area_unit'       => 'sqft',
	'bedrooms'        => '3',
	'estat_save_mode' => 'publish',
	'highlights'      => array( 'corner_plot', 'near_metro', 'lift' ),
);
$_REQUEST = $_POST;

$redirected = false;
try {
	Actions::handle();
} catch ( Throwable $e ) {
	$redirected = true;
}
EstatTests::ok( $redirected, 'the form post finishes with a redirect rather than an error page' );

// The newest listing is the one the form just created.
$posted    = get_posts( array( 'post_type' => PostTypes::LISTING, 'numberposts' => -1 ) );
$posted_id = 0;
foreach ( (array) $posted as $candidate ) {
	if ( 'Posted through the form' === $candidate->post_title ) {
		$posted_id = (int) $candidate->ID;
	}
}

EstatTests::ok( $posted_id > 0, 'the listing was created' );
EstatTests::is(
	array( 'corner_plot', 'near_metro', 'lift' ),
	Highlights::get( $posted_id ),
	'the ticks made it from the browser to the database'
);

/* ================================================================ INSIGHTS */

EstatTests::group( 'Integration: the office can write three kinds of thing' );

EstatTests::ok( array_key_exists( 'estat_manage_insights', Roles::all() ), 'there is a capability for writing insights' );
EstatTests::ok( in_array( 'estat_manage_insights', Roles::agent_caps(), true ), 'agents may write too' );

$blog = Insights::save(
	array(
		'title'   => 'Home loan tips for first-time buyers',
		'kind'    => 'blog',
		'excerpt' => 'What the bank will ask you for.',
		'content' => '<p>Keep your papers ready.</p>',
		'topics'  => 'Home Loans, Buying',
		'status'  => 'publish',
	)
);
EstatTests::ok( ! is_wp_error( $blog ), 'a blog saves' );

$article = Insights::save(
	array(
		'title'  => 'How registry works in Delhi',
		'kind'   => 'article',
		'status' => 'publish',
	)
);
EstatTests::ok( ! is_wp_error( $article ), 'an article saves' );

$insight = Insights::save(
	array(
		'title'  => 'Dwarka Expressway prices this quarter',
		'kind'   => 'insight',
		'topics' => 'Market Trends',
		'status' => 'draft',
	)
);
EstatTests::ok( ! is_wp_error( $insight ), 'an insight saves' );

EstatTests::is( PostTypes::INSIGHT, get_post_type( (int) $blog ), 'all three are stored as the plugin\'s own content, not blog posts' );

EstatTests::is( 1, Insights::query( array( 'kind' => 'blog' ) )['total'], 'blogs list on their own tab' );
EstatTests::is( 1, Insights::query( array( 'kind' => 'article' ) )['total'], 'articles list on their own tab' );
EstatTests::is( 1, Insights::query( array( 'kind' => 'insight' ) )['total'], 'insights list on their own tab' );

$blog_row = Insights::query( array( 'kind' => 'blog' ) )['items'][0];
EstatTests::is( 'blog', $blog_row['kind'], 'a piece knows which kind it is' );
EstatTests::is( array( 'Home Loans', 'Buying' ), $blog_row['topics'], 'topics are kept as written' );
EstatTests::is( 'publish', $blog_row['status'], 'the published state is reported honestly' );

$draft_row = Insights::query( array( 'kind' => 'insight' ) )['items'][0];
EstatTests::is( 'draft', $draft_row['status'], 'a draft is not reported as live' );

EstatTests::is( 1, Insights::query( array( 'kind' => 'blog', 'search' => 'loan' ) )['total'], 'search finds a piece by title' );
EstatTests::is( 0, Insights::query( array( 'kind' => 'blog', 'search' => 'zzzz' ) )['total'], 'search does not invent matches' );

$refiled = Insights::save( array( 'id' => (int) $blog, 'title' => 'Home loan tips for first-time buyers', 'kind' => 'article' ) );
EstatTests::ok( ! is_wp_error( $refiled ), 're-filing under another kind works' );
EstatTests::is( (int) $blog, (int) $refiled, 're-filing keeps the same record, so the web address does not change' );
EstatTests::is( 0, Insights::query( array( 'kind' => 'blog' ) )['total'], 'it left the old tab' );
EstatTests::is( 2, Insights::query( array( 'kind' => 'article' ) )['total'], 'and appeared on the new one' );

$no_title = Insights::save( array( 'title' => '   ', 'kind' => 'blog' ) );
EstatTests::ok( is_wp_error( $no_title ), 'saving without a title is refused rather than creating an untitled mess' );

$bad_kind = Insights::save( array( 'title' => 'Odd one', 'kind' => 'not_a_kind' ) );
EstatTests::ok( ! is_wp_error( $bad_kind ), 'an unknown kind does not break the save' );
EstatTests::is( 'blog', Insights::to_array( (int) $bad_kind )['kind'], 'an unknown kind falls back to blog' );

/* ------------------------------------------------------- Insights screen */

EstatTests::group( 'Integration: the insights screen' );

$_GET = array( 'page' => 'estat-insights', 'kind' => 'article' );
ob_start();
InsightsScreen::render();
$screen = (string) ob_get_clean();

EstatTests::ok( false !== strpos( $screen, 'estat-tab' ), 'the screen shows tabs' );
EstatTests::ok( false !== strpos( $screen, 'How registry works in Delhi' ), 'it lists the pieces of that kind' );
EstatTests::ok( false === strpos( $screen, 'Dwarka Expressway prices' ), 'it does not mix in another kind' );
EstatTests::ok( false !== strpos( $screen, 'is-active' ), 'the current tab is marked as current' );

$_GET = array( 'page' => 'estat-insights', 'kind' => 'blog', 'new' => '1' );
ob_start();
InsightsScreen::render();
$editor = (string) ob_get_clean();

EstatTests::ok( false !== strpos( $editor, 'name="title"' ), 'the write form asks for a title' );
EstatTests::ok( false !== strpos( $editor, 'name="topics"' ), 'the write form asks for topics' );
EstatTests::ok( false !== strpos( $editor, 'save_insight' ), 'the write form posts to the plugin, not the WordPress editor' );
EstatTests::ok( false !== strpos( $editor, 'estat_nonce' ), 'the write form carries a security token' );

/* ------------------------------------------------------- Permissions */

EstatTests::group( 'Integration: only permitted people may write' );

estat_login_with( 501, array( 'estat_manage_listings' ) );

$_REQUEST = $_POST = array(
	'estat_action' => 'save_insight',
	'estat_nonce'  => wp_create_nonce( 'estat_save_insight_0' ),
	'insight_id'   => '0',
	'title'        => 'Should never be saved',
	'kind'         => 'blog',
);

$blocked = false;
try {
	Actions::handle();
} catch ( Throwable $e ) {
	$blocked = true;
}
EstatTests::ok( $blocked, 'someone without the insights permission is stopped' );
EstatTests::is( 0, Insights::query( array( 'kind' => 'blog', 'search' => 'never be saved' ) )['total'], 'and nothing was written' );

estat_login_with( 502, array( 'estat_manage_insights' ) );

$_REQUEST = $_POST = array(
	'estat_action'    => 'save_insight',
	'estat_nonce'     => wp_create_nonce( 'estat_save_insight_0' ),
	'insight_id'      => '0',
	'title'           => 'Written by a permitted agent',
	'kind'            => 'blog',
	'content'         => '<p>Fine.</p><script>alert(1)</script>',
	'estat_save_mode' => 'publish',
);
try {
	Actions::handle();
} catch ( Throwable $e ) {
	$blocked = true;
}
EstatTests::is( 1, Insights::query( array( 'kind' => 'blog', 'search' => 'permitted agent' ) )['total'], 'someone with the permission can write' );

$written = Insights::query( array( 'kind' => 'blog', 'search' => 'permitted agent' ) )['items'][0];
EstatTests::ok(
	false === strpos( (string) get_post( (int) $written['id'] )->post_content, '<script>' ),
	'a script pasted into the writing is stripped before it is stored'
);

// A stale or missing security token must not write anything.
estat_login_owner();
$_REQUEST = $_POST = array(
	'estat_action' => 'save_insight',
	'estat_nonce'  => 'not-a-real-token',
	'insight_id'   => '0',
	'title'        => 'Forged request',
	'kind'         => 'blog',
);
$stopped = false;
try {
	Actions::handle();
} catch ( Throwable $e ) {
	$stopped = true;
}
EstatTests::ok( $stopped, 'a request without a valid security token is stopped' );
EstatTests::is( 0, Insights::query( array( 'kind' => 'blog', 'search' => 'Forged' ) )['total'], 'and the forged piece was not written' );

$_GET  = array();
$_POST = array();

/* ------------------------------------------- Upgrading an existing office */

EstatTests::group( 'Integration: an office that already had the plugin' );

estat_login_owner();

// Pretend this site is running the previous release.
update_option( 'estat_db_version', '1' );
update_option( 'estat_version', '0.9.0' );
unset( $GLOBALS['estat_roles']['estat_office_agent']['capabilities']['estat_manage_insights'] );

\EstatOS\Install\Migrator::maybe_upgrade();

EstatTests::is( ESTAT_DB_VERSION, (string) get_option( 'estat_db_version' ), 'the stored database version catches up' );
EstatTests::is( ESTAT_VERSION, (string) get_option( 'estat_version' ), 'the stored plugin version catches up' );
EstatTests::ok(
	! empty( $GLOBALS['estat_roles']['estat_office_agent']['capabilities']['estat_manage_insights'] ),
	'an existing office gets the new writing permission without reinstalling'
);
EstatTests::ok(
	count( (array) get_terms( array( 'taxonomy' => \EstatOS\Data\Taxonomies::INSIGHT_KIND, 'hide_empty' => false ) ) ) >= 3,
	'the three kinds are set up on upgrade'
);

// Running it again must be harmless.
\EstatOS\Install\Migrator::maybe_upgrade();
EstatTests::is(
	3,
	count( (array) get_terms( array( 'taxonomy' => \EstatOS\Data\Taxonomies::INSIGHT_KIND, 'hide_empty' => false ) ) ),
	'upgrading twice does not duplicate the kinds'
);
