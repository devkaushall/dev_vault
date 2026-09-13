<?php
/**
 * Reusable public HTML components shared by shortcodes and Elementor widgets.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Frontend;

use EstatOS\Data\Agents;
use EstatOS\Data\Highlights;
use EstatOS\Data\Listings;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Taxonomies;
use EstatOS\Search\Query;
use EstatOS\Settings\Settings;
use EstatOS\Support\Format;
use EstatOS\Support\Vocabulary;
use EstatOS\Support\Icon;

defined( 'ABSPATH' ) || exit;

/**
 * One implementation of every visible component. Elementor widgets and
 * shortcodes both call these, so the two can never drift apart.
 */
final class Components {

	/**
	 * A property card.
	 *
	 * @param int $listing_id Listing ID.
	 * @return string
	 */
	public static function card( int $listing_id ): string {
		$data = Listings::to_array( $listing_id, false );
		if ( ! $data ) {
			return '';
		}
		// Never show a visitor a property the office has not published. Staff
		// who may edit listings still see it, so previewing a draft works.
		if ( 'publish' !== (string) $data['status'] && ! current_user_can( 'estat_manage_listings' ) ) {
			return '';
		}
		wp_enqueue_style( 'estat-public' );
		wp_enqueue_script( 'estat-public' );

		$locality = ! empty( $data['locality'] ) ? (string) $data['locality'][0] : '';
		$beds     = (int) $data['bedrooms'];

		ob_start();
		?>
		<article class="estat-card" data-listing="<?php echo esc_attr( (string) $listing_id ); ?>">
			<a class="estat-card-media" href="<?php echo esc_url( (string) $data['url'] ); ?>">
				<?php if ( '' !== (string) $data['cover'] ) : ?>
					<?php
					/*
					 * A card image stays lazy - most of them are below the
					 * fold - but it still needs its dimensions, or every card
					 * in the grid resizes as its photo lands and the whole
					 * page shuffles under the reader's cursor.
					 *
					 * The CSS crops to 4:3 regardless; these numbers are only
					 * so the browser can reserve the space in advance.
					 */
					$card_w = (int) ( $data['cover_width'] ?? 0 );
					$card_h = (int) ( $data['cover_height'] ?? 0 );
					?>
					<img
						src="<?php echo esc_url( (string) $data['cover'] ); ?>"
						alt="<?php echo esc_attr( (string) $data['title'] ); ?>"
						<?php if ( $card_w > 0 && $card_h > 0 ) : ?>
							width="<?php echo esc_attr( (string) $card_w ); ?>"
							height="<?php echo esc_attr( (string) $card_h ); ?>"
						<?php endif; ?>
						loading="lazy"
						decoding="async"
					/>
				<?php else : ?>
					<span class="estat-card-noimage" aria-hidden="true"></span>
				<?php endif; ?>
				<?php if ( ! empty( $data['featured'] ) ) : ?>
					<span class="estat-badge estat-badge-featured"><?php esc_html_e( 'Featured', 'estat-os' ); ?></span>
				<?php endif; ?>
				<span class="estat-badge estat-badge-offer"><?php echo esc_html( Vocabulary::label( 'offers', (string) $data['offer'] ) ); ?></span>

				<?php
				/*
				 * The office already records whether it has checked a
				 * property, and until now that was stored and shown to
				 * nobody. On Indian portals the commonest complaint is not
				 * knowing which listing is real, so an office that does the
				 * checking should get the credit for it.
				 *
				 * Only 'office_verified' earns the badge. 'self_verified'
				 * means the owner said so, which is not the same thing and
				 * must not be dressed up as if it were.
				 */
				if ( 'office_verified' === (string) $data['verification'] ) :
					?>
					<span class="estat-badge estat-badge-verified">
						<?php echo Icon::render( 'verified', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php esc_html_e( 'Verified', 'estat-os' ); ?>
					</span>
				<?php endif; ?>
			</a>
			<div class="estat-card-body">
				<h3 class="estat-card-title"><a href="<?php echo esc_url( (string) $data['url'] ); ?>"><?php echo esc_html( (string) $data['title'] ); ?></a></h3>
				<?php if ( '' !== $locality ) : ?>
					<p class="estat-card-locality"><?php echo esc_html( $locality ); ?></p>
				<?php endif; ?>
				<p class="estat-card-price"><?php echo esc_html( (string) $data['price_display'] ); ?></p>

				<?php
				/*
				 * Only shout about it while it is genuinely new. A quiet
				 * "listed 240 days ago" on a card helps nobody and makes the
				 * office look like it cannot sell anything.
				 *
				 * The published check has to be separate: days_listed returns
				 * zero both for a listing put up an hour ago and for a draft
				 * that was never published, and those are opposite things. A
				 * listing published today is the freshest it will ever be, so
				 * testing "greater than zero" hid the flag on exactly the day
				 * it matters most.
				 */
				if ( 'publish' === (string) $data['status'] && (int) ( $data['days_listed'] ?? 0 ) < 7 ) :
					?>
					<p class="estat-card-fresh"><?php esc_html_e( 'Just listed', 'estat-os' ); ?></p>
				<?php endif; ?>
				<?php
				$chips = Highlights::for_card( $listing_id );
				if ( $chips ) :
					?>
					<ul class="estat-chips">
						<?php foreach ( $chips as $chip ) : ?>
							<li class="estat-chip">
								<?php if ( '' !== (string) $chip['icon'] ) : ?>
									<span class="estat-chip-icon"><?php echo Icon::render( (string) $chip['icon'], 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php endif; ?>
								<span class="estat-chip-text"><?php echo esc_html( (string) $chip['label'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<ul class="estat-card-facts">
					<li><?php echo esc_html( Vocabulary::label( 'property_types', (string) $data['property_type'] ) ); ?></li>
				</ul>
				<div class="estat-card-actions">
					<?php if ( Settings::get( 'enable_favorites' ) ) : ?>
						<button type="button" class="estat-fav" data-listing="<?php echo esc_attr( (string) $listing_id ); ?>" aria-pressed="false">
							<span class="screen-reader-text"><?php esc_html_e( 'Save this property', 'estat-os' ); ?></span>
							<span class="estat-fav-icon"><?php echo Icon::render( 'heart', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</button>
					<?php endif; ?>
					<?php if ( Settings::get( 'enable_compare' ) ) : ?>
						<button type="button" class="estat-compare" data-listing="<?php echo esc_attr( (string) $listing_id ); ?>" aria-pressed="false">
							<?php esc_html_e( 'Compare', 'estat-os' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * A property directory with pagination.
	 *
	 * @param array<string,mixed> $args Search filters.
	 * @return string
	 */
	public static function directory( array $args = array() ): string {
		wp_enqueue_style( 'estat-public' );
		$results = Query::search( $args );

		ob_start();
		echo '<div class="estat-directory">';
		if ( empty( $results['ids'] ) ) {
			echo '<p class="estat-empty">' . esc_html__( 'No properties match what you are looking for. Try widening your search.', 'estat-os' ) . '</p>';
		} else {
			echo '<div class="estat-grid">';
			foreach ( $results['ids'] as $id ) {
				echo self::card( (int) $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
			echo self::pagination( (int) $results['page'], (int) $results['pages'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Pagination links.
	 *
	 * @param int $page  Current page.
	 * @param int $pages Total pages.
	 * @return string
	 */
	public static function pagination( int $page, int $pages ): string {
		if ( $pages < 2 ) {
			return '';
		}
		$links = paginate_links(
			array(
				'base'      => add_query_arg( 'estat_page', '%#%' ),
				'format'    => '',
				'current'   => max( 1, $page ),
				'total'     => $pages,
				'type'      => 'array',
				'prev_text' => __( 'Previous', 'estat-os' ),
				'next_text' => __( 'Next', 'estat-os' ),
			)
		);
		if ( ! is_array( $links ) ) {
			return '';
		}
		$out = '<nav class="estat-pagination" aria-label="' . esc_attr__( 'Property pages', 'estat-os' ) . '"><ul>';
		foreach ( $links as $link ) {
			$out .= '<li>' . wp_kses_post( $link ) . '</li>';
		}
		return $out . '</ul></nav>';
	}

	/**
	 * The search form.
	 *
	 * @param array<string,mixed> $args Options: action.
	 * @return string
	 */
	public static function search_form( array $args = array() ): string {
		wp_enqueue_style( 'estat-public' );
		$action = isset( $args['action'] ) ? esc_url( (string) $args['action'] ) : esc_url( (string) get_post_type_archive_link( PostTypes::LISTING ) );

		$localities = get_terms(
			array(
				'taxonomy'   => Taxonomies::LOCALITY,
				'hide_empty' => true,
				'number'     => 200,
			)
		);
		$localities = is_wp_error( $localities ) ? array() : $localities;

		$get = static function ( string $key ): string {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ) : '';
		};

		ob_start();
		?>
		<form class="estat-search" method="get" action="<?php echo esc_url( $action ); ?>" role="search">
			<div class="estat-search-row">
				<div class="estat-search-field">
					<label for="estat-q"><?php esc_html_e( 'What are you looking for?', 'estat-os' ); ?></label>
					<input type="search" id="estat-q" name="keyword" value="<?php echo esc_attr( $get( 'keyword' ) ); ?>" placeholder="<?php esc_attr_e( 'For example: 3 BHK near the metro', 'estat-os' ); ?>" />
				</div>
				<div class="estat-search-field">
					<label for="estat-offer"><?php esc_html_e( 'Buy or rent', 'estat-os' ); ?></label>
					<select id="estat-offer" name="offer">
						<option value=""><?php esc_html_e( 'Any', 'estat-os' ); ?></option>
						<?php foreach ( Vocabulary::offers() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $get( 'offer' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="estat-search-field">
					<label for="estat-type"><?php esc_html_e( 'Property type', 'estat-os' ); ?></label>
					<select id="estat-type" name="property_type[]">
						<option value=""><?php esc_html_e( 'Any', 'estat-os' ); ?></option>
						<?php foreach ( Vocabulary::property_types() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $get( 'property_type' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php if ( $localities ) : ?>
					<div class="estat-search-field">
						<label for="estat-locality"><?php esc_html_e( 'Locality', 'estat-os' ); ?></label>
						<select id="estat-locality" name="locality[]">
							<option value=""><?php esc_html_e( 'Anywhere', 'estat-os' ); ?></option>
							<?php foreach ( $localities as $term ) : ?>
								<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( $get( 'locality' ), (string) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
				<div class="estat-search-field">
					<label for="estat-beds"><?php esc_html_e( 'Bedrooms (minimum)', 'estat-os' ); ?></label>
					<input type="number" id="estat-beds" name="bedrooms_min" min="0" max="20" value="<?php echo esc_attr( $get( 'bedrooms_min' ) ); ?>" />
				</div>
				<div class="estat-search-field">
					<label for="estat-price-max"><?php esc_html_e( 'Budget up to', 'estat-os' ); ?></label>
					<input type="number" id="estat-price-max" name="price_max" min="0" step="1000" value="<?php echo esc_attr( $get( 'price_max' ) ); ?>" placeholder="<?php esc_attr_e( 'For example: 9000000', 'estat-os' ); ?>" />
				</div>
			</div>
			<div class="estat-search-actions">
				<button type="submit" class="estat-btn estat-btn-primary"><?php esc_html_e( 'Search properties', 'estat-os' ); ?></button>
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * A monthly repayment estimate.
	 *
	 * Research on Indian property portals is consistent that this is the most
	 * used tool on a listing page: a buyer who works out that a flat is
	 * affordable is far likelier to enquire than one left guessing. It is also
	 * a reason to come back, which a static listing never is.
	 *
	 * The arithmetic runs entirely in the browser. Nothing is sent anywhere,
	 * nothing is stored, and no bank or API is involved - so this works whether
	 * or not the office has any integration, and a visitor playing with the
	 * numbers is not quietly generating a lead.
	 *
	 * The standard formula every Indian lender publishes:
	 *
	 *     EMI = P x R x (1+R)^N / ((1+R)^N - 1)
	 *
	 * where R is the monthly rate and N the number of months.
	 *
	 * @param float $price Property price, used as the starting figure.
	 * @return string
	 */
	public static function emi( float $price ): string {
		if ( $price <= 0 ) {
			return '';
		}

		wp_enqueue_style( 'estat-public' );
		wp_enqueue_script( 'estat-public' );

		$currency = (string) Settings::get( 'currency_symbol' );

		if ( '' === $currency ) {
			$currency = '&#8377;';
		}

		// Twenty per cent down is the usual assumption, and banks will not
		// normally lend beyond eighty per cent of the value anyway.
		$deposit = round( $price * 0.2 );
		$loan    = max( 0, $price - $deposit );

		ob_start();
		?>
		<section
			class="estat-emi"
			aria-label="<?php esc_attr_e( 'Work out the monthly payment', 'estat-os' ); ?>"
			data-price="<?php echo esc_attr( (string) (int) $price ); ?>"
		>
			<h2 class="estat-emi-title"><?php esc_html_e( 'What would this cost a month?', 'estat-os' ); ?></h2>

			<div class="estat-emi-controls">
				<p class="estat-emi-field">
					<label for="estat-emi-loan"><?php esc_html_e( 'Loan amount', 'estat-os' ); ?></label>
					<span class="estat-emi-input">
						<span class="estat-emi-currency" aria-hidden="true"><?php echo esc_html( $currency ); ?></span>
						<input type="number" id="estat-emi-loan" class="estat-emi-loan" value="<?php echo esc_attr( (string) (int) $loan ); ?>" min="0" step="10000" inputmode="numeric" />
					</span>
					<span class="estat-emi-note">
						<?php
						printf(
							/* translators: %s: the deposit amount, already formatted. */
							esc_html__( 'Assuming a %s deposit. Change it if yours is different.', 'estat-os' ),
							esc_html( Format::money( (float) $deposit ) )
						);
						?>
					</span>
				</p>

				<p class="estat-emi-field">
					<label for="estat-emi-rate"><?php esc_html_e( 'Interest rate', 'estat-os' ); ?></label>
					<span class="estat-emi-input">
						<input type="number" id="estat-emi-rate" class="estat-emi-rate" value="8.5" min="1" max="20" step="0.05" inputmode="decimal" />
						<span class="estat-emi-currency" aria-hidden="true">%</span>
					</span>
					<span class="estat-emi-note"><?php esc_html_e( 'Per year. Ask your bank for their current rate.', 'estat-os' ); ?></span>
				</p>

				<p class="estat-emi-field">
					<label for="estat-emi-years"><?php esc_html_e( 'Over how many years', 'estat-os' ); ?></label>
					<span class="estat-emi-input">
						<input type="number" id="estat-emi-years" class="estat-emi-years" value="20" min="1" max="30" step="1" inputmode="numeric" />
					</span>
					<span class="estat-emi-note"><?php esc_html_e( 'A longer loan means a smaller monthly payment, but more interest overall.', 'estat-os' ); ?></span>
				</p>
			</div>

			<?php
			/*
			 * aria-live so a screen reader hears the new figure when the
			 * numbers change. Without it the calculator updates silently and
			 * a blind visitor has no idea anything happened.
			 */
			?>
			<div class="estat-emi-result" role="status" aria-live="polite">
				<p class="estat-emi-amount">
					<span class="estat-emi-value">&mdash;</span>
					<span class="estat-emi-per"><?php esc_html_e( 'a month', 'estat-os' ); ?></span>
				</p>
				<p class="estat-emi-breakdown"><span class="estat-emi-total"></span></p>
			</div>

			<p class="estat-emi-caveat">
				<?php esc_html_e( 'This is an estimate to help you plan, not an offer. Your bank decides the real figure, and it will include fees and insurance this does not.', 'estat-os' ); ?>
			</p>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * A map, using OpenStreetMap by default so no paid key is needed.
	 *
	 * @param float  $lat   Latitude.
	 * @param float  $lng   Longitude.
	 * @param string $label Marker label.
	 * @return string
	 */
	public static function map( float $lat, float $lng, string $label = '' ): string {
		$provider = (string) Settings::get( 'map_provider', 'osm' );
		if ( 'none' === $provider || ( 0.0 === $lat && 0.0 === $lng ) ) {
			return '';
		}
		wp_enqueue_style( 'estat-public' );

		if ( 'google' === $provider && '' !== (string) Settings::get( 'google_maps_key' ) ) {
			$src = add_query_arg(
				array(
					'key' => rawurlencode( (string) Settings::get( 'google_maps_key' ) ),
					'q'   => rawurlencode( $lat . ',' . $lng ),
				),
				'https://www.google.com/maps/embed/v1/place'
			);
		} else {
			$delta = 0.01;
			$bbox  = ( $lng - $delta ) . ',' . ( $lat - $delta ) . ',' . ( $lng + $delta ) . ',' . ( $lat + $delta );
			$src   = add_query_arg(
				array( 'bbox' => $bbox, 'layer' => 'mapnik', 'marker' => $lat . ',' . $lng ),
				'https://www.openstreetmap.org/export/embed.html'
			);
		}

		return '<div class="estat-map"><iframe title="' . esc_attr( '' !== $label ? $label : __( 'Map', 'estat-os' ) ) . '" src="' . esc_url( $src ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>';
	}

	/**
	 * The team directory.
	 *
	 * @param int $limit Maximum team members.
	 * @return string
	 */
	public static function agents( int $limit = 12 ): string {
		wp_enqueue_style( 'estat-public' );
		$ids = get_posts(
			array(
				'post_type'      => PostTypes::AGENT,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, min( 100, $limit ) ),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( ! $ids ) {
			return '<p class="estat-empty">' . esc_html__( 'No team members have been added yet.', 'estat-os' ) . '</p>';
		}
		ob_start();
		echo '<div class="estat-grid estat-team">';
		foreach ( $ids as $id ) {
			$agent = Agents::to_array( (int) $id );
			?>
			<article class="estat-agent">
				<?php if ( '' !== (string) $agent['photo'] ) : ?>
					<?php
					// The stylesheet renders this at a fixed 88px circle, so
					// the size is known here without asking the database.
					?>
					<img
						class="estat-agent-photo"
						src="<?php echo esc_url( (string) $agent['photo'] ); ?>"
						alt="<?php echo esc_attr( (string) $agent['name'] ); ?>"
						width="88"
						height="88"
						loading="lazy"
						decoding="async"
					/>
				<?php endif; ?>
				<h3><a href="<?php echo esc_url( (string) $agent['url'] ); ?>"><?php echo esc_html( (string) $agent['name'] ); ?></a></h3>
				<?php if ( '' !== (string) $agent['role'] ) : ?>
					<p class="estat-agent-role"><?php echo esc_html( (string) $agent['role'] ); ?></p>
				<?php endif; ?>
				<p class="estat-agent-contact">
					<?php if ( '' !== (string) $agent['phone'] ) : ?>
						<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', (string) $agent['phone'] ) ); ?>"><?php esc_html_e( 'Call', 'estat-os' ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== (string) $agent['whatsapp'] ) : ?>
						<a href="https://wa.me/<?php echo esc_attr( preg_replace( '/\D/', '', (string) $agent['whatsapp'] ) ); ?>" rel="nofollow noopener"><?php esc_html_e( 'WhatsApp', 'estat-os' ); ?></a>
					<?php endif; ?>
				</p>
			</article>
			<?php
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * The comparison tray.
	 *
	 * @return string
	 */
	public static function compare(): string {
		if ( ! Settings::get( 'enable_compare' ) ) {
			return '';
		}
		wp_enqueue_style( 'estat-public' );
		wp_enqueue_script( 'estat-public' );
		return '<section class="estat-compare-panel" id="estat-compare-panel" aria-live="polite">'
			. '<h2>' . esc_html__( 'Compare properties', 'estat-os' ) . '</h2>'
			. '<p class="estat-empty">' . esc_html__( 'Choose "Compare" on a few properties and they will appear here side by side.', 'estat-os' ) . '</p>'
			. '<div class="estat-compare-table"></div></section>';
	}

	/**
	 * The saved-properties list.
	 *
	 * @return string
	 */
	public static function favorites(): string {
		if ( ! Settings::get( 'enable_favorites' ) ) {
			return '';
		}
		wp_enqueue_style( 'estat-public' );
		wp_enqueue_script( 'estat-public' );
		return '<section class="estat-favorites" id="estat-favorites" aria-live="polite">'
			. '<h2>' . esc_html__( 'Your saved properties', 'estat-os' ) . '</h2>'
			. '<p class="estat-empty">' . esc_html__( 'Nothing saved yet. Tap the heart on any property to keep it here.', 'estat-os' ) . '</p>'
			. '<div class="estat-grid"></div></section>';
	}
}
