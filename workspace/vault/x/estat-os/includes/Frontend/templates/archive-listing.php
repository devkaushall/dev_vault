<?php
/**
 * Fallback template for the property directory.
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
	<header class="estat-archive-header">
		<h1><?php esc_html_e( 'Properties', 'estat-os' ); ?></h1>
	</header>
	<?php echo Components::search_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$filters = array(
		'keyword'       => isset( $_GET['keyword'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['keyword'] ) ) : '',
		'offer'         => isset( $_GET['offer'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['offer'] ) ) : '',
		'property_type' => isset( $_GET['property_type'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_GET['property_type'] ) ) : array(),
		'locality'      => isset( $_GET['locality'] ) ? array_map( 'absint', (array) wp_unslash( $_GET['locality'] ) ) : array(),
		'bedrooms_min'  => isset( $_GET['bedrooms_min'] ) ? absint( wp_unslash( $_GET['bedrooms_min'] ) ) : 0,
		'price_max'     => isset( $_GET['price_max'] ) ? (float) wp_unslash( $_GET['price_max'] ) : 0,
		'page'          => isset( $_GET['estat_page'] ) ? absint( wp_unslash( $_GET['estat_page'] ) ) : 1,
	);
	// phpcs:enable
	if ( is_tax( \EstatOS\Data\Taxonomies::LOCALITY ) ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$filters['locality'] = array( (int) $term->term_id );
		}
	}
	echo Components::directory( $filters ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</main>
<?php
get_footer();
