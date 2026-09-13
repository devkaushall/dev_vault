<?php
/**
 * Fallback template for the societies and projects directory.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'estat-public' );

get_header();
?>
<main class="estat-archive" id="main">
	<h1><?php esc_html_e( 'Societies and projects', 'estat-os' ); ?></h1>
	<?php if ( have_posts() ) : ?>
		<div class="estat-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article class="estat-card">
					<a class="estat-card-media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?></a>
					<div class="estat-card-body">
						<h2 class="estat-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
					</div>
				</article>
				<?php
			endwhile;
			?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p class="estat-empty"><?php esc_html_e( 'No societies or projects have been added yet.', 'estat-os' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
