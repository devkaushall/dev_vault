<?php
/**
 * Fallback template for a team member profile.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Data\Agents;
use EstatOS\Frontend\Components;

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'estat-public' );

get_header();

while ( have_posts() ) :
	the_post();
	$agent_id = (int) get_the_ID();
	$agent    = Agents::to_array( $agent_id );
	?>
	<main class="estat-single" id="main">
		<h1><?php the_title(); ?></h1>
		<?php if ( '' !== (string) $agent['role'] ) : ?>
			<p class="estat-agent-role"><?php echo esc_html( (string) $agent['role'] ); ?></p>
		<?php endif; ?>
		<div class="estat-single-description"><?php the_content(); ?></div>
		<p class="estat-agent-contact">
			<?php if ( '' !== (string) $agent['phone'] ) : ?>
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', (string) $agent['phone'] ) ); ?>"><?php esc_html_e( 'Call', 'estat-os' ); ?></a>
			<?php endif; ?>
			<?php if ( '' !== (string) $agent['email'] ) : ?>
				<a href="mailto:<?php echo esc_attr( (string) $agent['email'] ); ?>"><?php esc_html_e( 'Email', 'estat-os' ); ?></a>
			<?php endif; ?>
		</p>
		<h2><?php esc_html_e( 'Properties handled', 'estat-os' ); ?></h2>
		<?php echo Components::directory( array( 'agent_id' => $agent_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</main>
	<?php
endwhile;

get_footer();
