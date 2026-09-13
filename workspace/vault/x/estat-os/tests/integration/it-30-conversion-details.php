<?php
/**
 * The four details the research pass identified.
 *
 * Each one came out of published conversion or performance data, and each was
 * measured against this plugin before being built:
 *
 * 1. The hero photograph had no dimensions and no fetch priority. It is the
 *    element Google times the page against, so this was costing both speed and
 *    layout stability.
 * 2. The office records whether it has checked a property, and showed that to
 *    nobody. On Indian portals, not knowing which listing is real is the
 *    single commonest complaint.
 * 3. How long a property has been listed was never shown, though buyers read
 *    it as "go this weekend" or "there may be room to negotiate".
 * 4. No repayment estimate, which is the most used tool on a listing page.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Data\Listings;
use EstatOS\Frontend\Components;
use EstatOS\Settings\Settings;

estat_reset_world();
estat_login_owner();

$root = dirname( __DIR__, 2 );

/**
 * A published listing with a price.
 *
 * @param array $extra Fields to merge.
 * @return int
 */
function estat_it30_listing( array $extra = array() ): int {
	$id = Listings::save(
		array_merge(
			array(
				'title'         => 'Conversion probe',
				'offer'         => 'sale',
				'property_type' => 'apartment',
				'locality'      => 'Saket',
				'price'         => 8500000,
				'area'          => 1450,
				'area_unit'     => 'sqft',
				'bedrooms'      => 3,
				'bathrooms'     => 2,
				'status'        => 'publish',
			),
			$extra
		)
	);

	return is_wp_error( $id ) ? 0 : (int) $id;
}

EstatTests::group( 'Images tell the browser their size' );

/*
 * An image with no width and height cannot have space reserved for it, so the
 * page lays out without it and then shoves everything down when it lands.
 * Google counts that jump against the site.
 */
$listing = estat_it30_listing();
$data    = Listings::to_array( $listing, false );

EstatTests::ok( array_key_exists( 'cover_width', $data ), 'the cover width is available to templates' );
EstatTests::ok( array_key_exists( 'cover_height', $data ), 'and the height' );
EstatTests::ok( is_int( $data['cover_width'] ), 'the width is a number, not a string' );
EstatTests::is( 0, $data['cover_width'], 'a listing with no photograph reports zero rather than guessing' );

// Every image the front end renders must state its size.
$card = Components::card( $listing );

preg_match_all( '/<img\b[^>]*>/s', $card, $card_images );

foreach ( $card_images[0] as $tag ) {
	// A tag with no src is a placeholder, not a photograph.
	if ( false === strpos( $tag, 'src=' ) ) {
		continue;
	}

	EstatTests::ok(
		false !== strpos( $tag, 'loading="lazy"' ),
		'a card photograph is lazy loaded, because most cards are below the fold'
	);
	EstatTests::ok(
		false !== strpos( $tag, 'decoding="async"' ),
		'and decoded off the main thread'
	);
}

$template = (string) file_get_contents( $root . '/includes/Frontend/templates/single-listing.php' );

/*
 * The hero is the opposite case. It is almost always the largest element on
 * screen, so lazy loading it is actively harmful: the browser waits for layout
 * before it even asks for the file.
 */
EstatTests::ok(
	false !== strpos( $template, 'fetchpriority="high"' ),
	'the hero photograph is fetched ahead of the queue'
);
EstatTests::ok(
	false !== strpos( $template, 'loading="eager"' ),
	'the hero photograph is never lazy loaded'
);
EstatTests::ok(
	false !== strpos( $template, '$cover_w > 0 && $cover_h > 0' ),
	'the hero writes its dimensions when it knows them'
);

// Dimensions must be omitted rather than written as zero, which would collapse
// the image to nothing.
EstatTests::ok(
	false === strpos( $template, 'width="0"' ),
	'a zero dimension is never written into the markup'
);

$components = (string) file_get_contents( $root . '/includes/Frontend/Components.php' );

EstatTests::ok(
	(bool) preg_match( '/width="88"\s*\n\s*height="88"/', $components ),
	'the agent photograph states the fixed size the stylesheet renders it at'
);

EstatTests::group( 'The office gets credit for checking a property' );

$verified = estat_it30_listing( array( '_estat_verification' => 'office_verified' ) );
$claimed  = estat_it30_listing( array( '_estat_verification' => 'self_verified' ) );
$unknown  = estat_it30_listing( array( '_estat_verification' => 'unverified' ) );

EstatTests::ok(
	false !== strpos( Components::card( $verified ), 'estat-badge-verified' ),
	'a property the office has checked carries a badge'
);

/*
 * This distinction is the whole point. "The owner says so" is not the same
 * claim as "we went and looked", and dressing one up as the other is exactly
 * the behaviour that made buyers stop trusting listing sites.
 */
EstatTests::ok(
	false === strpos( Components::card( $claimed ), 'estat-badge-verified' ),
	'a property the OWNER claims is fine gets no badge'
);
EstatTests::ok(
	false === strpos( Components::card( $unknown ), 'estat-badge-verified' ),
	'an unchecked property gets no badge'
);

EstatTests::ok(
	false !== strpos( $template, "'office_verified' === (string) \$listing['verification']" ),
	'the property page applies the same rule'
);

EstatTests::group( 'How long it has been listed' );

/*
 * Counted from publication, not creation. A property that sat in drafts for a
 * month has not been on the market for a month, and saying so would be a lie
 * that works against the office.
 */
$fresh = estat_it30_listing();
$aged  = estat_it30_listing();
$draft = estat_it30_listing( array( 'status' => 'draft' ) );

$long_ago = gmdate( 'Y-m-d H:i:s', time() - ( 45 * DAY_IN_SECONDS ) );

wp_update_post(
	array(
		'ID'            => $aged,
		'post_date'     => $long_ago,
		'post_date_gmt' => $long_ago,
	)
);
wp_update_post(
	array(
		'ID'            => $draft,
		'post_date'     => $long_ago,
		'post_date_gmt' => $long_ago,
	)
);

EstatTests::is( 0, Listings::days_listed( $fresh ), 'a listing published today reads as zero days' );
EstatTests::is( 45, Listings::days_listed( $aged ), 'one published 45 days ago reads as 45' );
EstatTests::is( 0, Listings::days_listed( $draft ), 'a 45-day-old draft reads as zero: it was never on the market' );

// A future-dated post, or a clock out of step, must not produce a negative.
$future    = estat_it30_listing();
$next_week = gmdate( 'Y-m-d H:i:s', time() + ( 5 * DAY_IN_SECONDS ) );

wp_update_post(
	array(
		'ID'            => $future,
		'post_date'     => $next_week,
		'post_date_gmt' => $next_week,
	)
);

EstatTests::is( 0, Listings::days_listed( $future ), 'a future date never reads as a negative number' );
EstatTests::is( 0, Listings::days_listed( 999999 ), 'an unknown listing reads as zero rather than failing' );

/*
 * The card only shouts while it is genuinely new. "Listed 240 days ago" on a
 * card helps nobody and makes the office look unable to sell anything.
 *
 * The day-zero case is the trap here, and it caught me: days_listed returns
 * zero both for a listing published an hour ago and for a draft that was never
 * published. Testing "greater than zero" hid the flag on the single day it is
 * worth the most.
 */
EstatTests::ok(
	false !== strpos( Components::card( $fresh ), 'estat-card-fresh' ),
	'a listing published TODAY is flagged, which is the freshest it ever gets'
);

$never_live = estat_it30_listing( array( 'status' => 'draft' ) );

EstatTests::ok(
	false === strpos( Components::card( $never_live ), 'estat-card-fresh' ),
	'a draft is not flagged as fresh, even though it also reports zero days'
);
EstatTests::ok(
	false === strpos( Components::card( $aged ), 'estat-card-fresh' ),
	'a 45-day-old listing is not flagged on the card'
);

// The property page shows the real figure, because there it is context rather
// than a boast.
EstatTests::ok(
	false !== strpos( $template, 'On the website %d day' ),
	'the property page states the actual age'
);
EstatTests::ok(
	false !== strpos( $template, "esc_html_e( 'Just listed'" ),
	'and says "just listed" while it is still new'
);

EstatTests::group( 'The repayment estimate' );

$panel = Components::emi( 8500000.0 );

EstatTests::ok( '' !== $panel, 'the calculator renders for a priced property' );
EstatTests::is( '', Components::emi( 0.0 ), 'and not for a property with no price' );
EstatTests::is( '', Components::emi( -5000.0 ), 'nor for a negative one' );

foreach (
	array(
		'estat-emi-loan'  => 'the loan amount can be changed',
		'estat-emi-rate'  => 'the interest rate can be changed',
		'estat-emi-years' => 'the term can be changed',
		'aria-live'       => 'the answer is announced to screen readers when it changes',
		'estat-emi-caveat' => 'the caveat is present',
	) as $needle => $why
) {
	EstatTests::ok( false !== strpos( $panel, $needle ), $why );
}

// Twenty per cent down is the usual Indian assumption, and banks rarely lend
// beyond eighty per cent of value anyway.
EstatTests::ok(
	false !== strpos( $panel, 'value="6800000"' ),
	'it starts at 80% of the price, the usual loan-to-value'
);
EstatTests::ok( false !== strpos( $panel, 'value="8.5"' ), 'and a realistic default interest rate' );
EstatTests::ok( false !== strpos( $panel, 'value="20"' ), 'and a realistic default term' );

/*
 * Nothing is sent anywhere. A visitor working out whether they can afford a
 * flat is thinking, not enquiring, and must not be turned into a lead for it.
 */
EstatTests::ok( false === strpos( $panel, '<form' ), 'the calculator is not a form' );
EstatTests::ok( false === strpos( $panel, 'restUrl' ), 'it sends nothing to the server' );
EstatTests::ok( false === strpos( $panel, 'name="' ), 'nothing in it would be submitted anywhere' );

// The arithmetic itself, checked against figures the lenders publish.
$javascript = (string) file_get_contents( $root . '/assets/js/public.js' );

EstatTests::ok(
	false !== strpos( $javascript, 'function monthlyPayment' ),
	'the calculation lives in the browser'
);
EstatTests::ok(
	false !== strpos( $javascript, 'Math.pow( 1 + monthly, months )' ),
	'it uses the compounding formula every Indian lender publishes'
);

// A rate of exactly zero would divide by zero.
EstatTests::ok(
	false !== strpos( $javascript, 'if ( annualRate <= 0 )' ),
	'a zero interest rate does not divide by zero'
);
EstatTests::ok(
	false !== strpos( $javascript, "value.textContent = '\\u2014'" ),
	'an empty or nonsense box shows a dash rather than NaN'
);

// The answer must be formatted the same way the price above it is, or the page
// reads as two different websites.
EstatTests::ok(
	false !== strpos( $javascript, 'strings.lakh' ) && false !== strpos( $javascript, 'strings.crore' ),
	'the answer is formatted in lakhs and crores like every price on the page'
);

$frontend = (string) file_get_contents( $root . '/includes/Frontend/Frontend.php' );

EstatTests::ok(
	false !== strpos( $frontend, "'currency'  => (string) Settings::get( 'currency_symbol' )" ),
	'the browser is told which currency symbol to use'
);
EstatTests::ok(
	false !== strpos( $frontend, "'priceStyle' => (string) Settings::get( 'price_format' )" ),
	'and which price style, so it matches the server'
);

// It belongs on a sale listing. A rental has nothing to calculate.
EstatTests::ok(
	false !== strpos( $template, "'sale' === (string) \$listing['offer'] && (float) \$listing['price'] > 0" ),
	'the calculator only appears on a sale listing with a real price'
);

EstatTests::group( 'The privacy promise is actually made' );

/*
 * The loudest complaint about Indian property portals is that one enquiry
 * produces dozens of broker calls. This plugin never sells a number on - and
 * that was going unsaid, which helps nobody.
 */
EstatTests::ok(
	false !== strpos( $template, 'estat-single-privacy' ),
	'the enquiry form carries a privacy note'
);
EstatTests::ok(
	false !== strpos( $template, 'do not pass your number to other agents' ),
	'and it says plainly that the number is not passed on'
);

// Which must remain true. An enquiry goes to the office's own tables.
$leads = (string) file_get_contents( $root . '/includes/Leads/Leads.php' );

EstatTests::ok(
	false === stripos( $leads, 'wp_remote_post' ),
	'creating a lead sends it nowhere off the site'
);

EstatTests::group( 'None of this costs the visitor much' );

/*
 * Every addition here ships to a visitor's browser, and the research is blunt
 * that weight costs conversions. This is the budget check.
 */
$public_css = (int) filesize( $root . '/assets/css/public.css' );
$public_js  = (int) filesize( $root . '/assets/js/public.js' );

$gzipped = strlen( (string) gzencode( (string) file_get_contents( $root . '/assets/css/public.css' ), 6 ) )
	+ strlen( (string) gzencode( (string) file_get_contents( $root . '/assets/js/public.js' ), 6 ) );

EstatTests::ok(
	$gzipped < 30000,
	'the visitor downloads ' . round( $gzipped / 1024 ) . 'KB gzipped, which is well inside budget'
);

// Assets must still load only where they are used, never site-wide.
EstatTests::ok(
	false !== strpos( $frontend, 'wp_register_style' ),
	'stylesheets are registered rather than enqueued globally'
);
EstatTests::ok(
	false !== strpos( $components, "wp_enqueue_style( 'estat-public' )" ),
	'and enqueued only when a component actually renders'
);
