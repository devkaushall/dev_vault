<?php
/**
 * "Add a Home": the short, beginner-friendly way to create a listing.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Data\Taxonomies;
use EstatOS\Settings\Settings;
use EstatOS\Support\Vocabulary;

defined( 'ABSPATH' ) || exit;

/**
 * Nine questions, plain language, then Save or Publish. Nothing here mentions
 * post types, meta fields or the WordPress editor.
 */
final class AddHome {

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'estat_manage_listings' ) ) {
			wp_die( esc_html__( 'You do not have permission to add listings.', 'estat-os' ) );
		}

		$localities = get_terms( array( 'taxonomy' => Taxonomies::LOCALITY, 'hide_empty' => false, 'number' => 300 ) );
		$localities = is_wp_error( $localities ) ? array() : $localities;
		$symbol     = (string) Settings::get( 'currency_symbol', '₹' );

		$steps = array(
			array(
				'key'   => 'basics',
				'label' => __( 'The basics', 'estat-os' ),
				'hint'  => __( 'What it is and where.', 'estat-os' ),
			),
			array(
				'key'   => 'price',
				'label' => __( 'Price and size', 'estat-os' ),
				'hint'  => __( 'What it costs and how big it is.', 'estat-os' ),
			),
			array(
				'key'   => 'photo',
				'label' => __( 'Photo', 'estat-os' ),
				'hint'  => __( 'One good picture is enough to start.', 'estat-os' ),
			),
			array(
				'key'   => 'highlights',
				'label' => __( 'Highlights', 'estat-os' ),
				'hint'  => __( 'What makes it worth buying.', 'estat-os' ),
			),
		);

		Partials::card_open(
			__( 'Add a home', 'estat-os' ),
			__( 'Four short steps. Nothing is published until you choose to, and you can go back at any point.', 'estat-os' )
		);
		?>
		<form method="post" class="estat-form-admin estat-wizard" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-add-home' ) ); ?>">
			<?php wp_nonce_field( 'estat_add_home', 'estat_nonce' ); ?>
			<input type="hidden" name="estat_action" value="add_home" />

			<ol class="estat-steps" aria-label="<?php esc_attr_e( 'Steps', 'estat-os' ); ?>">
				<?php foreach ( $steps as $index => $step ) : ?>
					<li class="estat-step<?php echo 0 === $index ? ' is-current' : ''; ?>" data-step="<?php echo esc_attr( $step['key'] ); ?>">
						<span class="estat-step-number"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
						<span class="estat-step-text">
							<span class="estat-step-label"><?php echo esc_html( $step['label'] ); ?></span>
							<span class="estat-step-hint"><?php echo esc_html( $step['hint'] ); ?></span>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>

			<section class="estat-wizard-panel is-current" data-step="basics">
			<?php
			Partials::field(
				array(
					'name'     => 'title',
					'label'    => __( 'Give it a short headline', 'estat-os' ),
					'help'     => __( 'This is the first thing a buyer sees. Say what it is and where.', 'estat-os' ),
					'example'  => __( '3 BHK apartment with a park view', 'estat-os' ),
					'required' => true,
				)
			);

			Partials::field(
				array(
					'name'     => 'offer',
					'type'     => 'select',
					'label'    => __( 'Is it for sale, rent or lease?', 'estat-os' ),
					'options'  => Vocabulary::offers(),
					'required' => true,
				)
			);

			Partials::field(
				array(
					'name'     => 'property_type',
					'type'     => 'select',
					'label'    => __( 'What kind of property is it?', 'estat-os' ),
					'options'  => Vocabulary::property_types(),
					'required' => true,
				)
			);

			?>

			<div class="estat-field-row">
				<label class="estat-field-label" for="estat-locality">
					<?php esc_html_e( 'Which locality is it in?', 'estat-os' ); ?>
					<span class="estat-required"><?php esc_html_e( 'required', 'estat-os' ); ?></span>
				</label>
				<input list="estat-locality-list" id="estat-locality" name="locality" required placeholder="<?php esc_attr_e( 'Start typing, or add a new one', 'estat-os' ); ?>" />
				<datalist id="estat-locality-list">
					<?php foreach ( $localities as $term ) : ?>
						<option value="<?php echo esc_attr( $term->name ); ?>"></option>
					<?php endforeach; ?>
				</datalist>
				<p class="description"><?php esc_html_e( 'If the locality is not in the list, just type it and we will add it.', 'estat-os' ); ?></p>
			</div>
			</section>

			<section class="estat-wizard-panel" data-step="price" hidden>
			<?php
			Partials::field(
				array(
					'name'    => 'price',
					'type'    => 'number',
					'label'   => sprintf( /* translators: %s: currency symbol */ __( 'Price (%s)', 'estat-os' ), $symbol ),
					'help'    => __( 'Type only numbers, no commas or symbols. We will show it neatly on the website.', 'estat-os' ),
					'example' => '8500000',
					'min'     => 0,
					'step'    => 1000,
				)
			);

			Partials::field(
				array(
					'name'           => 'price_on_request',
					'type'           => 'checkbox',
					'label'          => __( 'Hide the price?', 'estat-os' ),
					'checkbox_label' => __( 'Show "Price on request" instead of the number', 'estat-os' ),
					'help'           => __( 'The number stays in your office records but visitors will not see it.', 'estat-os' ),
				)
			);

			Partials::field(
				array(
					'name'    => 'area',
					'type'    => 'number',
					'label'   => __( 'How big is it?', 'estat-os' ),
					'help'    => __( 'Type only the number.', 'estat-os' ),
					'example' => '1250',
					'min'     => 0,
					'step'    => 1,
				)
			);

			Partials::field(
				array(
					'name'    => 'area_unit',
					'type'    => 'select',
					'label'   => __( 'Measured in', 'estat-os' ),
					'options' => Vocabulary::area_units(),
					'value'   => Settings::get( 'default_area_unit', 'sqft' ),
				)
			);

			Partials::field(
				array(
					'name'    => 'bedrooms',
					'type'    => 'number',
					'label'   => __( 'How many bedrooms?', 'estat-os' ),
					'help'    => __( 'Leave as 0 for a plot, shop or office.', 'estat-os' ),
					'example' => '3',
					'min'     => 0,
					'max'     => 20,
				)
			);
			?>

			</section>

			<section class="estat-wizard-panel" data-step="photo" hidden>

			<?php
			Partials::field(
				array(
					'name'  => 'cover_id',
					'type'  => 'image',
					'label' => __( 'Main photo', 'estat-os' ),
					'help'  => __( 'One good photo makes a big difference. You can add more later.', 'estat-os' ),
				)
			);
			?>

			</section>

			<section class="estat-wizard-panel" data-step="highlights" hidden>
				<?php Partials::highlights(); ?>

			</section>

			<div class="estat-wizard-nav">
				<button type="button" class="button estat-wizard-back" hidden><?php esc_html_e( 'Back', 'estat-os' ); ?></button>
				<button type="button" class="button button-primary estat-wizard-next"><?php esc_html_e( 'Next', 'estat-os' ); ?></button>
			</div>

			<div class="estat-form-actions-admin estat-wizard-finish" hidden>
				<button type="submit" name="estat_save_mode" value="draft" class="button button-secondary button-hero"><?php esc_html_e( 'Save as draft', 'estat-os' ); ?></button>
				<?php if ( current_user_can( 'estat_publish_listings' ) ) : ?>
					<button type="submit" name="estat_save_mode" value="publish" class="button button-primary button-hero"><?php esc_html_e( 'Put it on the website', 'estat-os' ); ?></button>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'A draft is only visible to your office. Nothing is published until you choose to.', 'estat-os' ); ?></p>
			</div>
		</form>
		<?php
		Partials::card_close();
	}
}
