<?php
/**
 * Fallback template for a single property.
 *
 * Themes may override this by adding single-estat_listing.php.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Data\Listings;
use EstatOS\Data\Agents;
use EstatOS\Forms\Forms;
use EstatOS\Forms\Renderer;
use EstatOS\Frontend\Components;
use EstatOS\Settings\Settings;
use EstatOS\Support\Format;
use EstatOS\Support\Icon;
use EstatOS\Support\Vocabulary;

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'estat-public' );
wp_enqueue_script( 'estat-public' );

get_header();

while ( have_posts() ) :
	the_post();
	$listing_id = (int) get_the_ID();
	$listing    = Listings::to_array( $listing_id, false );
	$gallery    = (array) get_post_meta( $listing_id, '_estat_gallery', true );
	$agent      = (int) $listing['agent_id'] > 0 ? Agents::to_array( (int) $listing['agent_id'] ) : array();
	$forms      = Forms::all( 1 );
	?>
	<main class="estat-single" id="main">
		<header class="estat-single-header">
			<h1><?php the_title(); ?></h1>
			<?php if ( ! empty( $listing['locality'] ) ) : ?>
				<p class="estat-single-locality"><?php echo esc_html( implode( ', ', (array) $listing['locality'] ) ); ?></p>
			<?php endif; ?>
			<p class="estat-single-price"><?php echo esc_html( (string) $listing['price_display'] ); ?></p>

			<?php
			/*
			 * Trust and freshness, side by side under the price. Both were
			 * already recorded and neither was shown, so a buyer had no way
			 * to tell a checked listing from an unchecked one, or a fresh
			 * listing from one that has sat for months.
			 */
			$verified  = 'office_verified' === (string) $listing['verification'];
			$days      = (int) ( $listing['days_listed'] ?? 0 );

			/*
			 * Whether it is live, not how old it is. days_listed returns zero
			 * for a listing published an hour ago and for a draft that was
			 * never published, so testing the number alone hides the line on
			 * the very first day - which is when "just listed" is worth most.
			 */
			$is_live = 'publish' === (string) $listing['status'];

			if ( $verified || $is_live ) :
				?>
				<p class="estat-single-signals">
					<?php if ( $verified ) : ?>
						<span class="estat-signal estat-signal-verified">
							<?php echo Icon::render( 'verified', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php esc_html_e( 'Checked by our office', 'estat-os' ); ?>
						</span>
					<?php endif; ?>

					<?php if ( $is_live ) : ?>
						<span class="estat-signal">
							<?php echo Icon::render( 'clock', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php
							if ( $days < 7 ) {
								esc_html_e( 'Just listed', 'estat-os' );
							} else {
								printf(
									/* translators: %d: number of days the property has been on the website. */
									esc_html( _n( 'On the website %d day', 'On the website %d days', $days, 'estat-os' ) ),
									(int) $days
								);
							}
							?>
						</span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</header>

		<?php if ( '' !== (string) $listing['cover'] ) : ?>
			<figure class="estat-single-cover">
				<?php
				/*
				 * This is the largest thing on the page, so it is what Google
				 * times the page load against. Three attributes matter here
				 * and all three were missing:
				 *
				 * - fetchpriority="high" tells the browser to fetch it before
				 *   the stylesheets and scripts queued ahead of it.
				 * - loading="eager" keeps it out of lazy loading. Lazy loading
				 *   the main image is actively harmful: the browser waits for
				 *   layout before it even asks for the file.
				 * - width and height let the browser reserve the right space,
				 *   so the rest of the page does not jump down when the photo
				 *   finally arrives.
				 */
				$cover_w = (int) ( $listing['cover_width'] ?? 0 );
				$cover_h = (int) ( $listing['cover_height'] ?? 0 );
				?>
				<img
					src="<?php echo esc_url( (string) $listing['cover'] ); ?>"
					alt="<?php echo esc_attr( get_the_title() ); ?>"
					<?php if ( $cover_w > 0 && $cover_h > 0 ) : ?>
						width="<?php echo esc_attr( (string) $cover_w ); ?>"
						height="<?php echo esc_attr( (string) $cover_h ); ?>"
					<?php endif; ?>
					fetchpriority="high"
					loading="eager"
					decoding="async"
				/>
			</figure>
		<?php endif; ?>

		<section class="estat-single-facts" aria-label="<?php esc_attr_e( 'Property information', 'estat-os' ); ?>">
			<ul>
				<li><strong><?php esc_html_e( 'Type', 'estat-os' ); ?>:</strong> <?php echo esc_html( Vocabulary::label( 'property_types', (string) $listing['property_type'] ) ); ?></li>
				<li><strong><?php esc_html_e( 'Offer', 'estat-os' ); ?>:</strong> <?php echo esc_html( Vocabulary::label( 'offers', (string) $listing['offer'] ) ); ?></li>
				<?php if ( (float) $listing['area'] > 0 ) : ?>
					<li><strong><?php esc_html_e( 'Size', 'estat-os' ); ?>:</strong> <?php echo esc_html( Format::area( (float) $listing['area'], (string) $listing['area_unit'] ) ); ?></li>
				<?php endif; ?>
				<?php if ( (int) $listing['bedrooms'] > 0 ) : ?>
					<li><strong><?php esc_html_e( 'Bedrooms', 'estat-os' ); ?>:</strong> <?php echo esc_html( (string) (int) $listing['bedrooms'] ); ?></li>
				<?php endif; ?>
				<?php if ( (int) $listing['bathrooms'] > 0 ) : ?>
					<li><strong><?php esc_html_e( 'Bathrooms', 'estat-os' ); ?>:</strong> <?php echo esc_html( (string) (int) $listing['bathrooms'] ); ?></li>
				<?php endif; ?>
				<?php if ( (int) $listing['balconies'] > 0 ) : ?>
					<li><strong><?php esc_html_e( 'Balconies', 'estat-os' ); ?>:</strong> <?php echo esc_html( (string) (int) $listing['balconies'] ); ?></li>
				<?php endif; ?>
				<?php if ( (int) $listing['floor'] > 0 || (int) $listing['total_floors'] > 0 ) : ?>
					<li>
						<strong><?php esc_html_e( 'Floor', 'estat-os' ); ?>:</strong>
						<?php
						if ( (int) $listing['floor'] > 0 && (int) $listing['total_floors'] > 0 ) {
							printf(
								/* translators: 1: this floor, 2: floors in the building. */
								esc_html__( '%1$d of %2$d', 'estat-os' ),
								(int) $listing['floor'],
								(int) $listing['total_floors']
							);
						} else {
							echo esc_html( (string) max( (int) $listing['floor'], (int) $listing['total_floors'] ) );
						}
						?>
					</li>
				<?php endif; ?>
				<?php if ( '' !== (string) $listing['facing'] ) : ?>
					<li><strong><?php esc_html_e( 'Facing', 'estat-os' ); ?>:</strong> <?php echo esc_html( Vocabulary::label( 'facing', (string) $listing['facing'] ) ); ?></li>
				<?php endif; ?>
				<?php if ( (int) $listing['age'] > 0 ) : ?>
					<li>
						<strong><?php esc_html_e( 'Age', 'estat-os' ); ?>:</strong>
						<?php
						printf(
							/* translators: %d: age of the property in years. */
							esc_html( _n( '%d year', '%d years', (int) $listing['age'], 'estat-os' ) ),
							(int) $listing['age']
						);
						?>
					</li>
				<?php endif; ?>
				<li><strong><?php esc_html_e( 'Availability', 'estat-os' ); ?>:</strong> <?php echo esc_html( Vocabulary::label( 'availability', (string) $listing['availability'] ) ); ?></li>
				<li><strong><?php esc_html_e( 'Stage', 'estat-os' ); ?>:</strong> <?php echo esc_html( Vocabulary::label( 'construction', (string) $listing['construction'] ) ); ?></li>
				<?php if ( '' !== (string) $listing['possession_date'] ) : ?>
					<li><strong><?php esc_html_e( 'Possession', 'estat-os' ); ?>:</strong> <?php echo esc_html( Format::date( (string) $listing['possession_date'] ) ); ?></li>
				<?php endif; ?>
				<?php if ( '' !== (string) $listing['developer'] ) : ?>
					<li><strong><?php esc_html_e( 'Built by', 'estat-os' ); ?>:</strong> <?php echo esc_html( (string) $listing['developer'] ); ?></li>
				<?php endif; ?>
				<?php if ( '' !== (string) ( $listing['regulatory_id'] ?? '' ) ) : ?>
					<li><strong><?php echo esc_html( (string) Settings::get( 'regulatory_label' ) ); ?>:</strong> <?php echo esc_html( (string) $listing['regulatory_id'] ); ?></li>
				<?php endif; ?>
			</ul>
		</section>

		<?php
		/*
		 * Rent costs beyond the rent itself. A tenant needs these before they
		 * enquire, and until now the office typed them into a field nobody
		 * could read.
		 */
		if ( (float) $listing['deposit'] > 0 || (float) $listing['maintenance'] > 0 ) :
			?>
			<section class="estat-single-costs" aria-label="<?php esc_attr_e( 'Other costs', 'estat-os' ); ?>">
				<h2><?php esc_html_e( 'Other costs', 'estat-os' ); ?></h2>
				<ul>
					<?php if ( (float) $listing['deposit'] > 0 ) : ?>
						<li><strong><?php esc_html_e( 'Security deposit', 'estat-os' ); ?>:</strong> <?php echo esc_html( Format::money( (float) $listing['deposit'] ) ); ?></li>
					<?php endif; ?>
					<?php if ( (float) $listing['maintenance'] > 0 ) : ?>
						<li><strong><?php esc_html_e( 'Monthly maintenance', 'estat-os' ); ?>:</strong> <?php echo esc_html( Format::money( (float) $listing['maintenance'] ) ); ?></li>
					<?php endif; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php
		// A video or a virtual tour is often the strongest thing a listing has.
		$video = (string) $listing['video_url'];
		$tour  = (string) $listing['tour_url'];

		if ( '' !== $video || '' !== $tour ) :
			?>
			<section class="estat-single-media" aria-label="<?php esc_attr_e( 'Video and tour', 'estat-os' ); ?>">
				<h2><?php esc_html_e( 'See more', 'estat-os' ); ?></h2>
				<p class="estat-single-media-links">
					<?php if ( '' !== $video ) : ?>
						<a class="estat-btn" href="<?php echo esc_url( $video ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Watch the video', 'estat-os' ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $tour ) : ?>
						<a class="estat-btn" href="<?php echo esc_url( $tour ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Take the 360 tour', 'estat-os' ); ?></a>
					<?php endif; ?>
				</p>
			</section>
		<?php endif; ?>

		<?php
		// Floor plans and brochures are attachments, so they need a link each.
		$documents = array_merge( (array) $listing['floor_plans'], (array) $listing['brochures'] );

		if ( $documents ) :
			?>
			<section class="estat-single-documents" aria-label="<?php esc_attr_e( 'Floor plans and brochures', 'estat-os' ); ?>">
				<h2><?php esc_html_e( 'Floor plans and brochures', 'estat-os' ); ?></h2>
				<ul>
					<?php
					foreach ( $documents as $attachment_id ) :
						$href = wp_get_attachment_url( (int) $attachment_id );

						if ( ! $href ) {
							continue;
						}

						$name = get_the_title( (int) $attachment_id );
						?>
						<li>
							<a href="<?php echo esc_url( $href ); ?>" target="_blank" rel="noopener">
								<?php echo esc_html( '' !== $name ? $name : __( 'Download', 'estat-os' ) ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php
		/*
		 * Placed straight after the facts, which is the moment a buyer has
		 * seen the price and is silently asking whether it is within reach.
		 * Only shown when there is a real price to work from: a rental or a
		 * price-on-request listing has nothing to calculate.
		 */
		if ( 'sale' === (string) $listing['offer'] && (float) $listing['price'] > 0 ) {
			echo Components::emi( (float) $listing['price'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>

		<section class="estat-single-description">
			<?php the_content(); ?>
		</section>

		<?php if ( $gallery ) : ?>
			<section class="estat-gallery" aria-label="<?php esc_attr_e( 'Photos', 'estat-os' ); ?>">
				<?php foreach ( array_slice( $gallery, 0, 30 ) as $attachment_id ) : ?>
					<?php echo wp_get_attachment_image( (int) $attachment_id, 'medium_large', false, array( 'loading' => 'lazy' ) ); ?>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<?php
		if ( '' !== (string) $listing['latitude'] && '' !== (string) $listing['longitude'] ) {
			echo Components::map( (float) $listing['latitude'], (float) $listing['longitude'], get_the_title() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>

		<?php if ( $agent ) : ?>
			<section class="estat-single-agent">
				<h2><?php esc_html_e( 'Who to speak to', 'estat-os' ); ?></h2>
				<p><?php echo esc_html( (string) $agent['name'] ); ?><?php echo '' !== (string) $agent['role'] ? ' — ' . esc_html( (string) $agent['role'] ) : ''; ?></p>
				<?php if ( '' !== (string) $agent['phone'] ) : ?>
					<p><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', (string) $agent['phone'] ) ); ?>"><?php esc_html_e( 'Call now', 'estat-os' ); ?></a></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $forms ) : ?>
			<section class="estat-single-enquiry">
				<h2><?php esc_html_e( 'Ask about this property', 'estat-os' ); ?></h2>
				<?php echo Renderer::render( (int) $forms[0]['id'], array( 'listing_id' => $listing_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php
				/*
				 * The loudest complaint about Indian property portals is that
				 * one enquiry produces dozens of broker calls, because the
				 * number is sold on. This plugin never does that - an enquiry
				 * goes into the office's own database and nowhere else.
				 *
				 * That is a genuine difference and it was going unsaid, which
				 * helps nobody. Saying it plainly is the strongest trust
				 * signal on the page and costs nothing.
				 */
				?>
				<p class="estat-single-privacy">
					<?php echo Icon::render( 'lock', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php esc_html_e( 'Your details go straight to our office and nowhere else. We do not pass your number to other agents.', 'estat-os' ); ?>
				</p>
			</section>
		<?php endif; ?>
	</main>
	<?php
endwhile;

get_footer();
