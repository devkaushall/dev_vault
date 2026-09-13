<?php
/**
 * Changes proposed from the field.
 *
 * The feature has one job: an agent who cannot touch a colleague's property can
 * still correct it, and cannot correct it until the office says so. Everything
 * below is a way that could quietly stop being true — a permission that lets a
 * proposal through, an approval that forgets to write, a refusal that writes
 * anyway, a reviewer shown a number with nothing to compare it against.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Admin\Actions;
use EstatOS\Admin\Screens\ListingsScreen;
use EstatOS\Admin\Screens\RequestsScreen;
use EstatOS\Data\Listings;
use EstatOS\Field\Requests;
use EstatOS\Security\Roles;
use EstatOS\Settings\Settings;

/** An agent who is not the owner of the test listing. */
const IT31_AGENT = 42;

/**
 * Post an office action and report the notice it redirects with.
 *
 * @param array<string,mixed> $fields Form fields, estat_action included.
 * @param string              $nonce  Nonce action, when it is not the obvious one.
 * @return string
 */
function estat_it31_submit( array $fields, string $nonce = '' ): string {
	if ( '' === $nonce ) {
		$nonce = 'estat_' . (string) $fields['estat_action'];
		if ( isset( $fields['request_id'] ) ) {
			$nonce .= '_' . (int) $fields['request_id'];
		}
	}

	$fields['estat_nonce'] = wp_create_nonce( $nonce );

	$_POST    = $fields;
	$_REQUEST = $fields;
	$_GET     = array();

	try {
		Actions::handle();
		return 'no-redirect';
	} catch ( Estat_Redirect_Exception $e ) {
		return preg_match( '/estat_notice=([a-z_]+)/', $e->getMessage(), $m ) ? $m[1] : 'unknown';
	}
}

/**
 * Post a propose form, whose nonce is keyed to the listing.
 *
 * @param int                 $listing_id The property.
 * @param array<string,mixed> $changes    Proposed values.
 * @param string              $message    The note.
 * @return string
 */
function estat_it31_propose( int $listing_id, array $changes, string $message ): string {
	return estat_it31_submit(
		array(
			'estat_action' => 'propose_listing',
			'listing_id'   => $listing_id,
			'changes'      => $changes,
			'message'      => $message,
		),
		'estat_propose_request_' . $listing_id
	);
}

/**
 * A listing belonging to somebody else, at a known price.
 *
 * @return int
 */
function estat_it31_foreign_listing(): int {
	estat_login_owner();

	return (int) Listings::save(
		array(
			'title'         => 'Villa on Dwarka Expressway',
			'description'   => 'Corner unit with a garden and two covered parks.',
			'offer'         => 'sale',
			'property_type' => 'villa',
			'price'         => 8500000,
			'area'          => 2400,
			'area_unit'     => 'sqft',
			'bedrooms'      => 4,
			'availability'  => 'available',
			'status'        => 'publish',
		)
	);
}

/**
 * The agent persona: may manage and ask, may not decide.
 *
 * @return void
 */
function estat_it31_agent(): void {
	estat_login_with(
		IT31_AGENT,
		array(
			'estat_manage_listings',
			'estat_request_listings',
			'estat_manage_leads',
			'estat_manage_visits',
		)
	);
}

/**
 * Capture a screen without letting a fatal escape.
 *
 * @param callable $render Renderer.
 * @return string
 */
function estat_it31_capture( callable $render ): string {
	ob_start();
	try {
		$render();
	} catch ( \Throwable $e ) {
		$unused = $e;
	}
	$html = (string) ob_get_contents();
	ob_end_clean();
	return $html;
}

/**
 * The module map the plugin really builds.
 *
 * @return array<int,string>
 */
function estat_it31_module_map(): array {
	$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Plugin.php' );
	preg_match_all( '/=>\s*([A-Za-z0-9_\\\\]+)::class/', $src, $found );
	return $found[1];
}

/**
 * The action-to-capability map, read from the source the plugin runs.
 *
 * @return array<string,string>
 */
function estat_it31_caps_map(): array {
	$src  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Admin/Actions.php' );
	$ok   = preg_match( '/const CAPS = array\((.*?)\n\t\);/s', $src, $block );
	$caps = array();

	if ( $ok ) {
		preg_match_all( "/'([a-z_]+)'\s*=>\s*'([a-z_]+)'/", $block[1], $found, PREG_SET_ORDER );
		foreach ( $found as $hit ) {
			$caps[ $hit[1] ] = $hit[2];
		}
	}

	return $caps;
}

estat_reset_world();

// Earlier files in the suite share this process and one of them set the
// currency to 'Rs' on purpose; say which currency this file reads.
Settings::update( array( 'currency_symbol' => '₹', 'price_format' => 'indian' ) );

$listing_id = estat_it31_foreign_listing();
$price_key  = '_estat_price';

EstatTests::group( 'The permission line is real, not decorative' );

estat_it31_agent();

EstatTests::ok( ! Requests::may_edit_directly( $listing_id ), 'an agent cannot change a property that is not theirs' );
EstatTests::ok( ! current_user_can( 'estat_review_listings' ), 'and cannot approve their own or anybody else’s request' );
EstatTests::ok( current_user_can( 'estat_request_listings' ), 'but they can ask' );

// Both capabilities are registered by name, and the agent role gets exactly
// the one it should.
EstatTests::ok( isset( Roles::all()['estat_request_listings'] ), 'asking is a named permission the office can see' );
EstatTests::ok( isset( Roles::all()['estat_review_listings'] ), 'deciding is a separate named permission' );
EstatTests::ok( in_array( 'estat_request_listings', Roles::agent_caps(), true ), 'agents are given the asking permission' );
EstatTests::ok( ! in_array( 'estat_review_listings', Roles::agent_caps(), true ), 'and not the deciding one' );

/* ============================================================== Proposing */

EstatTests::group( 'An agent proposes a change' );

$notice = estat_it31_propose(
	$listing_id,
	array(
		'price'        => 8200000,
		'availability' => 'sold',
		'bedrooms'     => 99, // Not offered to anybody.
		'_estat_price' => 1, // The same trick played with the meta key.
	),
	'Owner told me on site this morning.'
);

EstatTests::is( 'requested', $notice, 'a well-formed proposal is accepted' );
EstatTests::is( '8500000', (string) get_post_meta( $listing_id, $price_key, true ), 'and nothing on the website has changed yet' );

$pending = Requests::query( array( 'status' => 'pending' ) );
EstatTests::is( 1, (int) $pending['total'], 'exactly one request is waiting' );

$request = $pending['items'][0];
EstatTests::is( '8200000', (string) $request['changes']['price'], 'the proposed price is stored' );
EstatTests::is( 'sold', (string) $request['changes']['availability'], 'and so is the availability' );
EstatTests::ok( ! isset( $request['changes']['bedrooms'] ), 'a field the form never offers is dropped, not stored' );
EstatTests::ok( ! isset( $request['changes']['_estat_price'] ), 'and so is the meta-key trick' );
EstatTests::is( IT31_AGENT, (int) $request['requested_by'], 'it is attributed to the agent who sent it' );
EstatTests::is( $listing_id, (int) $request['record_id'], 'and pointed at the property it is about' );
EstatTests::is( 1, Requests::pending_count(), 'the queue count sees it' );

/* --------------------------------------------------- Refused before asking */

EstatTests::group( 'A useless proposal is refused, and so is a pointless one' );

estat_reset_world();
$listing_id = estat_it31_foreign_listing();
estat_it31_agent();

$notice = estat_it31_propose( $listing_id, array( 'price' => 8200000 ), '' );
EstatTests::is( 'error', $notice, 'a proposal with no note is sent straight back — the office cannot decide from a number alone' );
EstatTests::is( 0, Requests::pending_count(), 'and nothing was queued' );

$notice = estat_it31_propose( $listing_id, array( 'price' => 'not a number' ), 'Price is wrong.' );
EstatTests::is( 'error', $notice, 'a proposal that turns out to contain nothing real is refused with a message' );
EstatTests::is( 0, Requests::pending_count(), 'and nothing was queued either' );

// The office does not propose to itself; the editor is right there.
estat_login_owner();
$notice = estat_it31_propose( $listing_id, array( 'price' => 8200000 ), 'Mine to change, testing.' );
EstatTests::is( 'error', $notice, 'somebody who can edit is told to just edit' );
EstatTests::is( 0, Requests::pending_count(), 'and no request was created' );

// A person with no permission at all cannot even post the form.
estat_login_with( 55, array( 'estat_manage_leads' ) );
$notice = estat_it31_propose( $listing_id, array( 'price' => 8200000 ), 'Anyone can post a form.' );
EstatTests::is( 'forbidden', $notice, 'and a stranger to the feature is turned away' );
EstatTests::is( 0, Requests::pending_count(), 'with nothing queued' );

/* ============================================================= Deciding */

EstatTests::group( 'Approving applies the change through the ordinary save path' );

estat_reset_world();
$listing_id = estat_it31_foreign_listing();

estat_it31_agent();
$request_id = (int) Requests::create(
	array(
		'record_id' => $listing_id,
		'changes'   => array(
			'price' => 8200000,
			'note'  => 'Owner dropped the price this morning.',
		),
		'message'   => 'Owner dropped the price this morning.',
	)
);
EstatTests::ok( $request_id > 0, 'the data layer takes a well-formed request' );

estat_login_owner();
$notice = estat_it31_submit(
	array(
		'estat_action' => 'approve_request',
		'request_id'   => $request_id,
	)
);

EstatTests::is( 'request_approved', $notice, 'the office approves it' );
EstatTests::is( '8200000', (string) get_post_meta( $listing_id, $price_key, true ), 'and the property now really says the new price' );

$after = Requests::get( $request_id );
EstatTests::is( 'approved', (string) $after['status'], 'the request is marked approved' );
EstatTests::is( 1, (int) $after['reviewed_by'], 'and it records who said yes' );
EstatTests::ok( '' === (string) get_post_meta( $listing_id, '_estat_note', true ), 'the note was not smuggled into the property as a field of its own' );
EstatTests::ok(
	false === strpos( (string) get_post( $listing_id )->post_content, 'Owner dropped' ),
	'and it did not replace the description either'
);
EstatTests::ok(
	false !== strpos( (string) get_post_meta( $listing_id, '_estat_internal_notes', true ), 'Owner dropped the price this morning.' ),
	'but the note itself is kept on the property, where it is useful'
);
EstatTests::is( 0, Requests::pending_count(), 'nothing is left in the queue' );

EstatTests::group( 'Declining changes nothing on the website' );

estat_reset_world();
$listing_id = estat_it31_foreign_listing();

estat_it31_agent();
$request_id = (int) Requests::create(
	array(
		'record_id' => $listing_id,
		'changes'   => array( 'availability' => 'sold' ),
		'message'   => 'May be sold, unconfirmed.',
	)
);

estat_login_owner();
$notice = estat_it31_submit(
	array(
		'estat_action' => 'decline_request',
		'request_id'   => $request_id,
		'reason'       => 'Seller has not confirmed it, so we keep it listed.',
	)
);

EstatTests::is( 'request_declined', $notice, 'the office turns it down' );
EstatTests::is( 'available', (string) get_post_meta( $listing_id, '_estat_availability', true ), 'and the property is left exactly as it was' );
EstatTests::is( '8500000', (string) get_post_meta( $listing_id, $price_key, true ), 'down to the stored price' );

$after = Requests::get( $request_id );
EstatTests::is( 'declined', (string) $after['status'], 'the request is marked declined' );
EstatTests::is( 'Seller has not confirmed it, so we keep it listed.', (string) $after['reason'], 'and the agent gets the reason' );

$notice = estat_it31_submit(
	array(
		'estat_action' => 'approve_request',
		'request_id'   => $request_id,
	)
);
EstatTests::is( 'error', $notice, 'a request already decided cannot be decided again' );

EstatTests::group( 'Nobody but the office can settle a request' );

estat_reset_world();
$listing_id = estat_it31_foreign_listing();

estat_it31_agent();
$request_id = (int) Requests::create(
	array(
		'record_id' => $listing_id,
		'changes'   => array( 'price' => 4000000 ),
		'message'   => 'Approve my own request, please.',
	)
);

// The agent can build a perfectly valid nonce; it is the permission that must
// stop them, not the form.
$notice = estat_it31_submit(
	array(
		'estat_action' => 'approve_request',
		'request_id'   => $request_id,
	)
);
EstatTests::is( 'forbidden', $notice, 'an agent cannot approve, right nonce or not' );
EstatTests::is( '8500000', (string) get_post_meta( $listing_id, $price_key, true ), 'and the price is untouched' );
EstatTests::is( 'pending', (string) Requests::get( $request_id )['status'], 'and the request is still waiting' );

estat_login_with( 77, array( 'estat_manage_leads' ) );
$notice = estat_it31_submit(
	array(
		'estat_action' => 'decline_request',
		'request_id'   => $request_id,
	)
);
EstatTests::is( 'forbidden', $notice, 'a colleague with the wrong permission is stopped too' );

/* =============================================================== Rendering */

EstatTests::group( 'The review queue shows a decision, not a database dump' );

estat_reset_world();
$listing_id = estat_it31_foreign_listing();

estat_it31_agent();
Requests::create(
	array(
		'record_id' => $listing_id,
		'changes'   => array(
			'price'        => 8200000,
			'availability' => 'sold',
			'note'         => 'Owner said so on site.',
		),
		'message'   => 'Owner said so on site.',
	)
);

estat_login_owner();
$_GET = array( 'page' => RequestsScreen::SLUG );
$queue = estat_it31_capture(
	static function () {
		RequestsScreen::render();
	}
);

EstatTests::ok( false !== strpos( $queue, 'Villa on Dwarka Expressway' ), 'the queue names the property' );
EstatTests::ok( false !== strpos( $queue, 'Owner said so on site.' ), 'and shows what the agent wrote' );
EstatTests::ok( false !== strpos( $queue, '₹85 Lakh' ), 'it shows what the website says today, formatted the way the website formats it' );
EstatTests::ok( false !== strpos( $queue, '₹82 Lakh' ), 'and what is being proposed' );
EstatTests::ok( false !== strpos( $queue, 'Yes, change it' ), 'with one button that means yes' );
EstatTests::ok( false !== strpos( $queue, 'Not now' ), 'and one that means no' );
EstatTests::ok( false !== strpos( $queue, 'approve_request' ), 'wired to the approve action' );
EstatTests::ok( false !== strpos( $queue, 'decline_request' ), 'and to the decline action' );
EstatTests::ok( false !== strpos( $queue, 'estat_nonce' ), 'both carrying a nonce' );
EstatTests::ok( false === strpos( $queue, 'name="title"' ), 'the reviewer is not handed an editor by accident' );

$css  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/admin.css' );
$from = strpos( $css, 'Change requests' );
EstatTests::ok( false !== $from, 'the queue block exists in the stylesheet' );
EstatTests::ok( false === strpos( substr( $css, (int) $from ), '#' ), 'and it sets no colour outside the tokens, so dark mode covers it' );

EstatTests::group( 'Each person sees their own half, and only their own half' );

// A person without either permission sees nothing at all.
$denied = '';
estat_it31_capture(
	static function () use ( &$denied ) {
		estat_login_with( 88, array( 'estat_manage_leads' ) );
		$_GET = array( 'page' => RequestsScreen::SLUG );
		ob_start();
		try {
			RequestsScreen::render();
		} catch ( \Throwable $e ) {
			echo 'STOP:' . ( $e instanceof Estat_Die_Exception ? $e->getMessage() : get_class( $e ) );
		}
		$denied = (string) ob_get_contents();
		ob_end_clean();
	}
);
estat_login_owner();
EstatTests::ok( false === strpos( $denied, 'approve_request' ), 'a colleague without the permission is shown no queue and no buttons' );

// The agent's half: the property they cannot edit offers the short form.
estat_it31_agent();
$_GET = array( 'page' => 'estat-listings', 'edit' => $listing_id );
$agent_view = estat_it31_capture(
	static function () {
		ListingsScreen::render();
	}
);

EstatTests::ok( false !== strpos( $agent_view, 'belongs to somebody else' ), 'the agent is told plainly whose it is' );
EstatTests::ok( false !== strpos( $agent_view, 'Suggest a change' ), 'and offered the way to fix it' );
EstatTests::ok( false !== strpos( $agent_view, 'propose_listing' ), 'the form runs the propose action' );
EstatTests::ok( false !== strpos( $agent_view, 'name="changes[price]"' ), 'with a price box' );
EstatTests::ok( false !== strpos( $agent_view, 'name="changes[availability]"' ), 'and a list of what it could be now' );
EstatTests::ok( false === strpos( $agent_view, 'name="changes[bedrooms]"' ), 'and nothing nobody could approve from a lobby' );
EstatTests::ok( false !== strpos( $agent_view, 'Today it says:' ), 'each field states what the website says today' );
EstatTests::ok( false !== strpos( $agent_view, 'name="message"' ), 'and a required note' );
EstatTests::ok( false === strpos( $agent_view, 'name="estat_save_mode"' ), 'the agent is not handed a publish button' );
EstatTests::ok( false === strpos( $agent_view, 'name="_estat_price"' ), 'the form cannot be renamed into an arbitrary meta write' );

// The owner, at the same URL, gets the editor — never a request form.
estat_login_owner();
$_GET = array( 'page' => 'estat-listings', 'edit' => $listing_id );
$owner_view = estat_it31_capture(
	static function () {
		ListingsScreen::render();
	}
);
EstatTests::ok( false !== strpos( $owner_view, 'estat_save_mode' ), 'the office edits directly' );
EstatTests::ok( false === strpos( $owner_view, 'propose_listing' ), 'and is never asked to propose their own change' );

estat_reset_world();

/* ======================================================== Plumbing checks */

EstatTests::group( 'The feature is wired, not just written' );

$maps = estat_it31_caps_map();

EstatTests::ok( in_array( 'Field\Requests', estat_it31_module_map(), true ), 'the plugin boots the change-request module' );
EstatTests::ok( isset( $maps['propose_listing'] ), 'the propose action is in the permission map' );
EstatTests::ok( isset( $maps['approve_request'] ), 'so is approval' );
EstatTests::ok( isset( $maps['decline_request'] ), 'and refusal' );
EstatTests::ok( ! isset( $maps['trash_listing'] ), 'and the dead entry an audit once tripped over is gone' );
EstatTests::is( 'estat_review_listings', $maps['approve_request'] ?? '', 'approving is gated on the deciding permission' );
EstatTests::is( 'estat_review_listings', $maps['decline_request'] ?? '', 'and so is refusing' );
EstatTests::is( 'estat_request_listings', $maps['propose_listing'] ?? '', 'proposing on the asking one' );

$root = dirname( __DIR__, 2 );
$admin_src = (string) file_get_contents( $root . '/includes/Admin/Admin.php' );
EstatTests::ok( false !== strpos( $admin_src, "'estat-requests'" ), 'the office menu carries the screen' );

$worklist_src = (string) file_get_contents( $root . '/includes/Data/Worklist.php' );
EstatTests::ok( false !== strpos( $worklist_src, 'field_requests' ), 'and the Today list sends people to it' );

$partials_src = (string) file_get_contents( $root . '/includes/Admin/Screens/Partials.php' );
foreach ( array( 'requested', 'request_approved', 'request_declined' ) as $key ) {
	EstatTests::ok( false !== strpos( $partials_src, "'$key'" ), 'the ' . $key . ' notice has its own plain-English wording' );
}

EstatTests::group( 'The mail is opt-in, short, and changes nothing by itself' );

estat_reset_world();
$listing_id = estat_it31_foreign_listing();
Settings::update( array( 'currency_symbol' => '₹', 'price_format' => 'indian', 'notify_email' => 'office@example.test', 'notify_change_request' => true ) );

$GLOBALS['estat_mail'] = array();
estat_it31_agent();
Requests::create(
	array(
		'record_id' => $listing_id,
		'changes'   => array( 'price' => 8200000 ),
		'message'   => 'Owner told me.',
	)
);
EstatTests::is( 1, count( $GLOBALS['estat_mail'] ), 'with the switch on, the office is told' );
$mail = $GLOBALS['estat_mail'][0];
EstatTests::is( 'office@example.test', (string) $mail['to'], 'to the office address' );
EstatTests::ok( false !== strpos( (string) $mail['message'], '₹82 Lakh' ), 'with the number already formatted' );
EstatTests::ok( strlen( (string) $mail['message'] ) < 500, 'and short enough to read on a phone' );

Settings::update( array( 'notify_change_request' => false ) );
$GLOBALS['estat_mail'] = array();
Requests::create(
	array(
		'record_id' => $listing_id,
		'changes'   => array( 'price' => 8100000 ),
		'message'   => 'Again.',
	)
);
EstatTests::is( array(), $GLOBALS['estat_mail'], 'with the switch off, nothing is mailed' );
EstatTests::is( 2, Requests::pending_count(), 'but the request is still queued — the switch mutes the mail, not the feature' );
