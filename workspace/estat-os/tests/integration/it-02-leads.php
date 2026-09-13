<?php
/**
 * Enquiries, visits, de-duplication and audit logging.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Audit\AuditLog;
use EstatOS\Leads\Leads;
use EstatOS\Leads\Visits;

estat_reset_world();
estat_login_owner();

/* ------------------------------------------------------------- Creation */

EstatTests::group( 'Integration: enquiries' );

$lead_id = Leads::create(
	array(
		'name'    => 'Asha Verma',
		'phone'   => '+91 98765 43210',
		'email'   => 'asha@example.test',
		'message' => 'I would like to see this flat on Sunday.',
		'source'  => 'website',
	)
);
EstatTests::ok( ! is_wp_error( $lead_id ), 'a valid enquiry is accepted' );
$lead_id = (int) $lead_id;

$lead = Leads::get( $lead_id );
EstatTests::ok( is_array( $lead ), 'the enquiry can be read back' );
EstatTests::is( 'Asha Verma', (string) $lead['name'], 'the name round-trips' );
EstatTests::is( 'new', (string) $lead['status'], 'a fresh enquiry starts as new' );

/* ------------------------------------------------------------ Validation */

EstatTests::group( 'Integration: enquiry validation' );

$no_contact = Leads::create( array( 'name' => 'No Contact Details' ) );
EstatTests::ok( is_wp_error( $no_contact ), 'an enquiry with neither phone nor email is refused' );

/* ---------------------------------------------------------- Idempotency */

EstatTests::group( 'Integration: duplicate protection' );

$key   = 'idem-key-12345';
$first = Leads::create(
	array(
		'name'            => 'Ravi Kumar',
		'phone'           => '+91 90000 00001',
		'idempotency_key' => $key,
	)
);
$again = Leads::create(
	array(
		'name'            => 'Ravi Kumar',
		'phone'           => '+91 90000 00001',
		'idempotency_key' => $key,
	)
);
EstatTests::ok( ! is_wp_error( $first ) && ! is_wp_error( $again ), 'both calls return successfully' );
EstatTests::is( (int) $first, (int) $again, 'the same idempotency key returns the original enquiry rather than creating a second' );

// Same person, same message, no key: the 24 hour dedupe hash should catch it.
$dup_a = Leads::create( array( 'name' => 'Same Person', 'phone' => '+91 90000 00002', 'message' => 'Identical text' ) );
$dup_b = Leads::create( array( 'name' => 'Same Person', 'phone' => '+91 90000 00002', 'message' => 'Identical text' ) );
EstatTests::is( (int) $dup_a, (int) $dup_b, 'an identical enquiry within the dedupe window is not duplicated' );

/* ----------------------------------------------------------- Assignment */

EstatTests::group( 'Integration: assignment and follow-up' );

$agent_post = (int) EstatOS\Data\Agents::save( array( 'title' => 'Priya Sharma', 'phone' => '+91 90000 11111' ) );
EstatTests::ok( $agent_post > 0, 'a team member can be created' );

$assigned = Leads::update( $lead_id, array( 'agent_id' => $agent_post, 'status' => 'contacted' ) );
EstatTests::ok( ! is_wp_error( $assigned ), 'the enquiry updates without error' );

$lead = Leads::get( $lead_id );
EstatTests::is( $agent_post, (int) $lead['agent_id'], 'the enquiry is now owned by that person' );
EstatTests::is( 'contacted', (string) $lead['status'], 'the status moved to contacted' );

Leads::append_note( $lead_id, 'Called, will visit on Sunday.' );
$lead = Leads::get( $lead_id );
EstatTests::ok( false !== strpos( (string) $lead['notes'], 'Called' ), 'notes are appended, not overwritten' );

Leads::append_note( $lead_id, 'Second note.' );
$lead = Leads::get( $lead_id );
EstatTests::ok(
	false !== strpos( (string) $lead['notes'], 'Called' ) && false !== strpos( (string) $lead['notes'], 'Second note' ),
	'a second note keeps the first one'
);

/* --------------------------------------------------------------- Status */

EstatTests::group( 'Integration: invalid status is refused, not stored' );

Leads::update( $lead_id, array( 'status' => 'not-a-real-status' ) );
$lead = Leads::get( $lead_id );
EstatTests::is( 'contacted', (string) $lead['status'], 'an unknown status falls back instead of corrupting the record' );

/* ---------------------------------------------------------------- Visits */

EstatTests::group( 'Integration: site visits' );

$listing_id = (int) EstatOS\Data\Listings::save(
	array(
		'title'    => 'Flat for the visit test with a long enough headline',
		'offer'    => 'sale',
		'property_type' => 'apartment',
		'locality' => 'Testville',
		'price'    => 3000000,
		'status'   => 'publish',
	)
);

$when  = gmdate( 'Y-m-d H:i:s', time() + ( 3 * DAY_IN_SECONDS ) );
$visit = Visits::schedule(
	array(
		'lead_id'      => $lead_id,
		'listing_id'   => $listing_id,
		'agent_id'     => $agent_post,
		'scheduled_at' => $when,
	)
);
EstatTests::ok( ! is_wp_error( $visit ), 'a future visit is accepted' );

$lead = Leads::get( $lead_id );
EstatTests::is( 'visit_scheduled', (string) $lead['status'], 'booking a visit moves the enquiry status automatically' );

$dup_visit = Visits::schedule(
	array(
		'lead_id'      => $lead_id,
		'listing_id'   => $listing_id,
		'scheduled_at' => $when,
	)
);
EstatTests::is( (int) $visit, (int) $dup_visit, 'booking the identical visit twice does not create a duplicate' );

$past = Visits::schedule(
	array(
		'lead_id'      => $lead_id,
		'listing_id'   => $listing_id,
		'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() - ( 10 * DAY_IN_SECONDS ) ),
	)
);
EstatTests::ok( is_wp_error( $past ), 'a visit far in the past is refused with a friendly error' );

$no_target = Visits::schedule( array( 'scheduled_at' => $when ) );
EstatTests::ok( is_wp_error( $no_target ), 'a visit with neither enquiry nor property is refused' );

/* -------------------------------------------------------------- Outcomes */

EstatTests::group( 'Integration: visit outcomes' );

$updated = Visits::update( (int) $visit, array( 'outcome' => 'done' ) );
EstatTests::ok( ! is_wp_error( $updated ), 'an outcome can be recorded' );

$counts = Visits::counts();
EstatTests::ok( isset( $counts['today'], $counts['upcoming'] ), 'visit counts are available for the Today screen' );

/* ------------------------------------------------------------- Erasure */

EstatTests::group( 'Integration: erasing personal information' );

$erase_me = (int) Leads::create( array( 'name' => 'Erase Me', 'phone' => '+91 90000 99999', 'email' => 'erase@example.test' ) );
EstatTests::ok( Leads::erase( $erase_me ), 'erase reports success' );

$erased = Leads::get( $erase_me );
if ( is_array( $erased ) ) {
	EstatTests::ok( 1 === (int) $erased['erased'], 'the record is flagged as erased' );
	EstatTests::ok(
		false === strpos( (string) $erased['name'], 'Erase Me' )
		&& false === strpos( (string) $erased['phone'], '99999' )
		&& false === strpos( (string) $erased['email'], 'erase@example.test' ),
		'no personal detail survives erasure'
	);
} else {
	EstatTests::ok( true, 'the record was removed entirely on erasure' );
}

/* ---------------------------------------------------------- Audit trail */

EstatTests::group( 'Integration: audit logging' );

$audit = AuditLog::query( array( 'per_page' => 50 ) );
EstatTests::ok( ! empty( $audit['items'] ), 'actions were written to the audit log' );

$actions = array_column( $audit['items'], 'action' );
EstatTests::ok( in_array( 'lead.created', $actions, true ), 'creating an enquiry is audited' );
EstatTests::ok( in_array( 'listing.created', $actions, true ), 'creating a listing is audited' );
EstatTests::ok( in_array( 'visit.scheduled', $actions, true ), 'scheduling a visit is audited' );

foreach ( array_slice( $actions, 0, 10 ) as $action ) {
	EstatTests::ok( '' !== AuditLog::describe( (string) $action ), 'the audit action "' . $action . '" has a plain-language description' );
}

/* ---------------------------------------------------------------- Counts */

EstatTests::group( 'Integration: dashboard counts' );

$lead_counts = Leads::counts();
EstatTests::ok( isset( $lead_counts['new'] ), 'the new-enquiry count is available' );
EstatTests::ok( is_int( $lead_counts['new'] ), 'counts are integers' );
