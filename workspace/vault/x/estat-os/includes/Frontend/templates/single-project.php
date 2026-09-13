<?php
/**
 * Fallback template for a single society or project.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Data\Projects;
use EstatOS\Frontend\Components;
use EstatOS\Settings\Settings;
use EstatOS\Support\Format;
use EstatOS\Support\Vocabulary;

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'estat-public' );

get_header();

while ( have_posts() ) :
	the_post();
	$project_id = (int) get_the_ID();
	$stats      = Projects::stats( $project_id );
	$project    = Projects::to_array( $project_id );
	?>
	<main class="estat-single" id="main">
		<h1><?php the_title(); ?></h1>
		<ul class="estat-single-facts">
			<li><strong><?php esc_html_e( 'Stage', 'estat-os' ); ?>:</strong> <?php echo esc_html( Vocabulary::label( 'project_statuses', (string) $project['stage'] ) ); ?></li>

			<?php
			/*
			 * Everything below was stored by the editor and shown to nobody
			 * until now. Each is skipped when empty, so a sparsely filled
			 * project does not render a list of blanks.
			 */
			if ( '' !== (string) $project['developer'] ) :
				?>
				<li><strong><?php esc_html_e( 'Built by', 'estat-os' ); ?>:</strong> <?php echo esc_html( (string) $project['developer'] ); ?></li>
			<?php endif; ?>

			<?php if ( $stats['price_min'] > 0 ) : ?>
				<li><strong><?php esc_html_e( 'Prices from', 'estat-os' ); ?>:</strong> <?php echo esc_html( Format::money( (float) $stats['price_min'] ) ); ?></li>
			<?php elseif ( (float) $project['price_min'] > 0 ) : ?>
				<li>
					<strong><?php esc_html_e( 'Prices from', 'estat-os' ); ?>:</strong>
					<?php echo esc_html( (string) $project['price_min_display'] ); ?>
					<?php if ( (float) $project['price_max'] > 0 ) : ?>
						&ndash; <?php echo esc_html( (string) $project['price_max_display'] ); ?>
					<?php endif; ?>
				</li>
			<?php endif; ?>

			<?php if ( '' !== (string) $project['unit_types'] ) : ?>
				<li><strong><?php esc_html_e( 'Unit types', 'estat-os' ); ?>:</strong> <?php echo esc_html( (string) $project['unit_types'] ); ?></li>
			<?php endif; ?>

			<?php if ( (int) $project['total_units'] > 0 ) : ?>
				<li>
					<strong><?php esc_html_e( 'Units', 'estat-os' ); ?>:</strong>
					<?php
					if ( (int) $project['available_units'] > 0 ) {
						printf(
							/* translators: 1: units still available, 2: total units. */
							esc_html__( '%1$d available of %2$d', 'estat-os' ),
							(int) $project['available_units'],
							(int) $project['total_units']
						);
					} else {
						echo esc_html( (string) (int) $project['total_units'] );
					}
					?>
				</li>
			<?php endif; ?>

			<?php if ( '' !== (string) $project['possession_date'] ) : ?>
				<li><strong><?php esc_html_e( 'Possession', 'estat-os' ); ?>:</strong> <?php echo esc_html( Format::date( (string) $project['possession_date'] ) ); ?></li>
			<?php endif; ?>

			<?php if ( '' !== (string) ( $project['regulatory_id'] ?? '' ) ) : ?>
				<li><strong><?php echo esc_html( (string) Settings::get( 'regulatory_label' ) ); ?>:</strong> <?php echo esc_html( (string) $project['regulatory_id'] ); ?></li>
			<?php endif; ?>

			<li><strong><?php esc_html_e( 'Properties listed', 'estat-os' ); ?>:</strong> <?php echo esc_html( (string) (int) $stats['listings'] ); ?></li>
		</ul>

		<?php if ( '' !== trim( (string) $project['highlights'] ) ) : ?>
			<h2><?php esc_html_e( 'Highlights', 'estat-os' ); ?></h2>
			<ul class="estat-single-highlights">
				<?php foreach ( array_filter( array_map( 'trim', explode( "\n", (string) $project['highlights'] ) ) ) as $point ) : ?>
					<li><?php echo esc_html( $point ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<div class="estat-single-description"><?php the_content(); ?></div>
		<h2><?php esc_html_e( 'Available here', 'estat-os' ); ?></h2>
		<?php echo Components::directory( array( 'project_id' => $project_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</main>
	<?php
endwhile;

get_footer();
