<?php
/**
 * The first-run setup: four short questions, then a working website.
 *
 * A new office has no idea which of thirteen menus to open first, and no way to
 * know that adding properties does nothing until a page exists to show them on.
 * This screen answers both problems at once. It asks only what cannot be
 * guessed, builds the office's public pages, and hands back a short list of
 * what to do next.
 *
 * Nothing here is compulsory. Everything it sets can be changed later in Office
 * Settings, and skipping it leaves the plugin working exactly as before.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Data\Listings;
use EstatOS\Data\PostTypes;
use EstatOS\Install\Pages;
use EstatOS\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the welcome and setup screen.
 */
final class SetupScreen {

	/**
	 * Screen slug.
	 *
	 * @var string
	 */
	public const SLUG = 'estat-setup';

	/**
	 * Should the office be nudged to run setup?
	 *
	 * True until they finish it or dismiss it. We do not nag an office that
	 * already has pages in place, because they clearly did not need us.
	 *
	 * @return bool
	 */
	public static function needed(): bool {
		if ( (bool) Settings::get( 'setup_done', false ) ) {
			return false;
		}
		return array() === Pages::existing();
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'estat_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to set up the office.', 'estat-os' ) );
		}

		$settings = Settings::all();
		$pages    = Pages::existing();
		$done     = array() !== $pages;

		Partials::header( self::SLUG );
		Partials::notices();

		echo '<div class="estat-setup">';

		if ( $done ) {
			self::finished( $pages );
		} else {
			self::form( $settings );
		}

		echo '</div>';
	}

	/**
	 * The setup questions.
	 *
	 * @param array<string,mixed> $settings Current settings.
	 * @return void
	 */
	private static function form( array $settings ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="estat-form">
			<input type="hidden" name="estat_action" value="run_setup" />
			<?php wp_nonce_field( 'estat_run_setup', 'estat_nonce' ); ?>

			<?php
			Partials::card_open(
				__( '1. Who are you?', 'estat-os' ),
				__( 'This is the name and number visitors will see on your website.', 'estat-os' )
			);

			Partials::field(
				array(
					'name'     => 'office_name',
					'label'    => __( 'Office name', 'estat-os' ),
					'value'    => (string) $settings['office_name'],
					'required' => true,
					'example'  => __( 'Sharma Properties', 'estat-os' ),
					'help'     => __( 'The name your customers know you by.', 'estat-os' ),
				)
			);

			Partials::field(
				array(
					'name'     => 'phone',
					'label'    => __( 'Phone number', 'estat-os' ),
					'value'    => (string) $settings['phone'],
					'required' => true,
					'example'  => '+91 98765 43210',
					'help'     => __( 'Where enquiries should call you.', 'estat-os' ),
				)
			);

			Partials::field(
				array(
					'name'    => 'email',
					'type'    => 'email',
					'label'   => __( 'Email address', 'estat-os' ),
					'value'   => (string) $settings['email'],
					'help'    => __( 'We will send you a note here whenever a new enquiry arrives.', 'estat-os' ),
					'example' => 'office@example.com',
				)
			);

			Partials::card_close();

			Partials::card_open(
				__( '2. Where do you work?', 'estat-os' ),
				__( 'So prices and areas are shown the way your customers expect.', 'estat-os' )
			);

			Partials::field(
				array(
					'name'    => 'cities',
					'label'   => __( 'Main areas you cover', 'estat-os' ),
					'value'   => is_array( $settings['cities'] ) ? implode( ', ', $settings['cities'] ) : (string) $settings['cities'],
					'example' => __( 'Delhi, Gurgaon, Noida', 'estat-os' ),
					'help'    => __( 'Separate them with commas. You can add more any time.', 'estat-os' ),
				)
			);

			Partials::field(
				array(
					'name'    => 'currency_symbol',
					'label'   => __( 'Currency sign', 'estat-os' ),
					'value'   => (string) $settings['currency_symbol'],
					'example' => '₹',
					'help'    => __( 'The sign shown in front of every price.', 'estat-os' ),
				)
			);

			Partials::field(
				array(
					'name'    => 'default_area_unit',
					'type'    => 'select',
					'label'   => __( 'How you usually measure size', 'estat-os' ),
					'value'   => (string) $settings['default_area_unit'],
					'options' => array(
						'sqft' => __( 'Square feet', 'estat-os' ),
						'sqyd' => __( 'Square yards', 'estat-os' ),
						'sqm'  => __( 'Square metres', 'estat-os' ),
					),
					'help'    => __( 'You can still enter a property in any unit; we convert it for you.', 'estat-os' ),
				)
			);

			Partials::card_close();

			Partials::card_open(
				__( '3. Which pages should we build for you?', 'estat-os' ),
				__( 'Right now your properties have nowhere to appear. Tick what you want and we will create the pages, ready to use.', 'estat-os' )
			);

			echo '<div class="estat-setup-pages">';
			foreach ( Pages::catalogue() as $key => $page ) {
				printf(
					'<label class="estat-setup-page"><input type="checkbox" name="pages[]" value="%1$s" checked="checked" /> <span><strong>%2$s</strong><span class="estat-setup-page-note">%3$s</span></span></label>',
					esc_attr( $key ),
					esc_html( $page['title'] ),
					esc_html( $page['description'] )
				);
			}
			echo '</div>';

			Partials::card_close();

			Partials::card_open(
				__( '4. How should those pages look?', 'estat-os' ),
				__( 'Either way you can redesign them later, in Elementor or anywhere else.', 'estat-os' )
			);

			?>
			<div class="estat-setup-choice">
				<label class="estat-setup-option">
					<input type="radio" name="page_style" value="ready" checked="checked" />
					<span>
						<strong><?php esc_html_e( 'Ready to use', 'estat-os' ); ?></strong>
						<span class="estat-setup-page-note"><?php esc_html_e( 'Each page comes with a heading, a short introduction and your properties already laid out. Best if you want something working today.', 'estat-os' ); ?></span>
					</span>
				</label>
				<label class="estat-setup-option">
					<input type="radio" name="page_style" value="blank" />
					<span>
						<strong><?php esc_html_e( 'Just the bare page', 'estat-os' ); ?></strong>
						<span class="estat-setup-page-note"><?php esc_html_e( 'An empty page with only the property list dropped in, so you can design the rest yourself in Elementor.', 'estat-os' ); ?></span>
					</span>
				</label>
			</div>
			<?php
			Partials::card_close();
			?>

			<p class="estat-form-actions">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Set up my office', 'estat-os' ); ?></button>
				<a class="button button-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?estat_action=skip_setup' ), 'estat_skip_setup', 'estat_nonce' ) ); ?>"><?php esc_html_e( 'Skip this, I will do it myself', 'estat-os' ); ?></a>
			</p>
		</form>
		<?php
	}

	/**
	 * What the office sees once setup has run.
	 *
	 * @param array<string,int> $pages Pages in place.
	 * @return void
	 */
	private static function finished( array $pages ): void {
		$catalogue = Pages::catalogue();

		Partials::card_open(
			__( 'Your website is ready', 'estat-os' ),
			__( 'These pages are live now. Click any of them to see what a visitor sees.', 'estat-os' )
		);

		echo '<ul class="estat-setup-list">';
		foreach ( $pages as $key => $id ) {
			$title = isset( $catalogue[ $key ] ) ? $catalogue[ $key ]['title'] : get_the_title( $id );
			printf(
				'<li><a href="%1$s" target="_blank" rel="noopener">%2$s</a> <a class="estat-setup-edit" href="%3$s">%4$s</a></li>',
				esc_url( (string) get_permalink( $id ) ),
				esc_html( (string) $title ),
				esc_url( (string) get_edit_post_link( $id, 'url' ) ),
				esc_html__( 'edit this page', 'estat-os' )
			);
		}
		echo '</ul>';

		Partials::card_close();

		self::next_steps();
	}

	/**
	 * A short, honest list of what still needs doing.
	 *
	 * Each item is only shown while it is actually outstanding, so the list
	 * shrinks to nothing as the office gets going rather than nagging forever.
	 *
	 * @return void
	 */
	public static function next_steps(): void {
		$listings = wp_count_posts( PostTypes::LISTING );
		$live     = isset( $listings->publish ) ? (int) $listings->publish : 0;
		$drafts   = isset( $listings->draft ) ? (int) $listings->draft : 0;
		$team     = (int) wp_count_posts( PostTypes::AGENT )->publish;

		$steps = array();

		if ( 0 === $live + $drafts ) {
			$steps[] = array(
				'title' => __( 'Add your first property', 'estat-os' ),
				'note'  => __( 'It takes about two minutes. You only need a name, a price and where it is.', 'estat-os' ),
				'url'   => admin_url( 'admin.php?page=estat-add-home' ),
				'label' => __( 'Add a Home', 'estat-os' ),
			);
		} elseif ( 0 === $live ) {
			$steps[] = array(
				'title' => __( 'Put a property on your website', 'estat-os' ),
				'note'  => __( 'You have properties saved as drafts. Nobody can see a draft until you publish it.', 'estat-os' ),
				'url'   => admin_url( 'admin.php?page=estat-listings&status=draft' ),
				'label' => __( 'See my drafts', 'estat-os' ),
			);
		}

		if ( 0 === $team ) {
			$steps[] = array(
				'title' => __( 'Add the people in your office', 'estat-os' ),
				'note'  => __( 'Enquiries can then be handed to whoever is dealing with them.', 'estat-os' ),
				'url'   => admin_url( 'admin.php?page=estat-team' ),
				'label' => __( 'Add someone', 'estat-os' ),
			);
		}

		if ( '' === (string) Settings::get( 'email', '' ) ) {
			$steps[] = array(
				'title' => __( 'Tell us where to send new enquiries', 'estat-os' ),
				'note'  => __( 'Without an email address you will have to check this screen to spot new enquiries.', 'estat-os' ),
				'url'   => admin_url( 'admin.php?page=estat-settings' ),
				'label' => __( 'Add an email address', 'estat-os' ),
			);
		}

		if ( array() === $steps ) {
			return;
		}

		Partials::card_open(
			__( 'What to do next', 'estat-os' ),
			__( 'This list gets shorter on its own as you go.', 'estat-os' )
		);

		echo '<ol class="estat-next-steps">';
		foreach ( $steps as $step ) {
			printf(
				'<li><strong>%1$s</strong><span>%2$s</span><a class="button button-small" href="%3$s">%4$s</a></li>',
				esc_html( (string) $step['title'] ),
				esc_html( (string) $step['note'] ),
				esc_url( (string) $step['url'] ),
				esc_html( (string) $step['label'] )
			);
		}
		echo '</ol>';

		Partials::card_close();
	}
}
