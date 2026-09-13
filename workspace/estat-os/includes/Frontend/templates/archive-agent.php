<?php
/**
 * Fallback template for the team directory.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Frontend\Components;

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'estat-public' );

get_header();
?>
<main class="estat-archive" id="main">
	<h1><?php esc_html_e( 'Our team', 'estat-os' ); ?></h1>
	<?php echo Components::agents( 60 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>
<?php
get_footer();
