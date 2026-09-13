<?php
/**
 * Shared admin UI pieces: header, cards, help, notices, fields.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Data\Highlights;
use EstatOS\Settings\Settings;
use EstatOS\Support\Icon;
use EstatOS\Admin\Actions;
use EstatOS\Data\Undo;

defined( 'ABSPATH' ) || exit;

/**
 * Small, explicit helpers. Everything is escaped at the point of output.
 */
final class Partials {

	/**
	 * Screen header with title, version and help.
	 *
	 * @param string $page Current page slug.
	 * @return void
	 */
	public static function header( string $page, bool $wide = false ): void {
		$titles = array(
			'estat-setup'       => array( __( 'Welcome to your office', 'estat-os' ), __( 'Four short questions, and your website will be ready for visitors.', 'estat-os' ) ),
			'estat-today'       => array( __( 'Today', 'estat-os' ), __( 'What needs your attention right now.', 'estat-os' ) ),
			'estat-add-home'    => array( __( 'Add a Home', 'estat-os' ), __( 'Just the basics. You can add more later.', 'estat-os' ) ),
			'estat-listings'    => array( __( 'Listings', 'estat-os' ), __( 'Every property your office is handling.', 'estat-os' ) ),
			'estat-enquiries'   => array( __( 'Enquiries', 'estat-os' ), __( 'People who have asked about your properties.', 'estat-os' ) ),
			'estat-visits'      => array( __( 'Site Visits', 'estat-os' ), __( 'Visits your team has arranged.', 'estat-os' ) ),
			'estat-projects'    => array( __( 'Societies & Projects', 'estat-os' ), __( 'Buildings and developments you work with.', 'estat-os' ) ),
			'estat-team'        => array( __( 'Team', 'estat-os' ), __( 'The people in your office.', 'estat-os' ) ),
			'estat-forms'       => array( __( 'Forms', 'estat-os' ), __( 'Design the forms visitors fill in on your website.', 'estat-os' ) ),
			'estat-spreadsheet' => array( __( 'Spreadsheet', 'estat-os' ), __( 'Bring information in from a spreadsheet, or take it out.', 'estat-os' ) ),
			'estat-reports'     => array( __( 'Reports', 'estat-os' ), __( 'How your office is doing.', 'estat-os' ) ),
			'estat-settings'    => array( __( 'Office Settings', 'estat-os' ), __( 'Your office details and how the system behaves.', 'estat-os' ) ),
		);
		$current = $titles[ $page ] ?? $titles['estat-today'];
		?>
		<div class="estat-page-head<?php echo $wide ? ' estat-wrap-wide' : ''; ?>">
			<div>
				<h1 class="wp-heading-inline"><?php echo esc_html( $current[0] ); ?></h1>
				<p class="estat-subtitle"><?php echo esc_html( $current[1] ); ?></p>
			</div>
			<div class="estat-page-head-actions">
				<?php if ( 'estat-add-home' !== $page && current_user_can( 'estat_manage_listings' ) ) : ?>
					<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=estat-add-home' ) ); ?>"><?php esc_html_e( 'Add a Home', 'estat-os' ); ?></a>
				<?php endif; ?>
				<button type="button" class="button estat-help-toggle" aria-expanded="false" aria-controls="estat-help"><?php esc_html_e( 'Need help?', 'estat-os' ); ?></button>
				<span class="estat-shortcut-hint"><?php esc_html_e( 'Press ? for shortcuts', 'estat-os' ); ?></span>
			</div>
		</div>
		<?php
		self::help_panel( $page );
		self::notices();
	}

	/**
	 * Contextual help drawer.
	 *
	 * @param string $page Page slug.
	 * @return void
	 */
	private static function help_panel( string $page ): void {
		/**
		 * Plain-language help for every screen.
		 *
		 * Each entry answers the same three questions a person actually has:
		 * what is this screen for, what should I do here, and what catches
		 * people out. No jargon, and never a reference to a manual the office
		 * has not got open.
		 */
		$help = array(
			'estat-setup'       => array(
				__( 'This is the quickest way to get started. Answer the questions and we will build your website pages for you.', 'estat-os' ),
				__( 'Nothing here is permanent. Every answer can be changed later in Office Settings, and the pages are ordinary pages you can edit or delete.', 'estat-os' ),
				__( 'If you would rather build everything yourself, click "Skip this" — the plugin works exactly the same either way.', 'estat-os' ),
			),
			'estat-today'       => array(
				__( 'This screen shows what happened recently and what is waiting for you.', 'estat-os' ),
				__( 'Start your day here, then click through to whatever needs attention.', 'estat-os' ),
				__( 'The list at the top is in order of urgency: overdue follow-ups first, then today\'s visits, then new enquiries.', 'estat-os' ),
			),
			'estat-add-home'    => array(
				__( 'Fill in a few basics and save. Nothing goes on the website until you choose "Put it on the website".', 'estat-os' ),
				__( 'A common mistake is typing the price as text. Type only numbers, for example 8500000.', 'estat-os' ),
				__( 'You only need four things to start: what it is, the price, the size and the locality. Everything else can wait.', 'estat-os' ),
			),
			'estat-listings'    => array(
				__( 'Every property your office is handling, whether it is on the website or not.', 'estat-os' ),
				__( '"On the website" means visitors can see it. "Draft" means only your office can. Use the filter at the top to switch between them.', 'estat-os' ),
				__( 'Remove puts a property in the bin and takes it off your website. Nothing is destroyed — you can put it back from the "In the bin" filter.', 'estat-os' ),
				__( 'The readiness score is a hint, not a rule. A listing with photos, a price and a locality gets far more enquiries than one without.', 'estat-os' ),
			),
			'estat-enquiries'   => array(
				__( 'Everyone who has contacted you through the website, newest first.', 'estat-os' ),
				__( 'Give each enquiry to a team member so it does not get forgotten, and set a follow-up date — it will then appear on Today when it is due.', 'estat-os' ),
				__( 'If the same person asks about the same property twice in a day we keep it as one enquiry and add their second message as a note, so you do not chase them twice.', 'estat-os' ),
				__( '"Erase personal details" permanently removes the name, phone and email if someone asks you to. The enquiry itself stays in your records.', 'estat-os' ),
			),
			'estat-visits'      => array(
				__( 'Site visits your team has arranged, so nobody turns up at the wrong time.', 'estat-os' ),
				__( 'A visit booked for today appears on the Today screen automatically.', 'estat-os' ),
				__( 'Mark a visit as done afterwards. That is what makes your reports meaningful.', 'estat-os' ),
			),
			'estat-projects'    => array(
				__( 'Societies, apartment complexes and builder projects that several of your listings belong to.', 'estat-os' ),
				__( 'Add the society once here, then pick it on each property. The shared details are then only typed once.', 'estat-os' ),
				__( 'This is optional. A standalone house does not need to belong to anything.', 'estat-os' ),
			),
			'estat-team'        => array(
				__( 'The people in your office. Their name and phone number can be shown on your website.', 'estat-os' ),
				__( 'Adding someone here does not give them a login. That is done in the normal WordPress Users screen.', 'estat-os' ),
				__( 'Once you have added people you can hand each enquiry to whoever is dealing with it.', 'estat-os' ),
			),
			'estat-forms'       => array(
				__( 'Build a form once here, then place it on any page — including pages you design with Elementor.', 'estat-os' ),
				__( 'Changing the look of a form never changes what it does with the information.', 'estat-os' ),
				__( 'Start from one of the ready-made forms. They already ask for the right things and are set up correctly.', 'estat-os' ),
				__( 'Every form sends what it collects to your Enquiries screen. You never have to check a separate inbox.', 'estat-os' ),
			),
			'estat-gallery'     => array(
				__( 'Every photo and document you have uploaded, in one place.', 'estat-os' ),
				__( 'Drag files straight onto the page to upload them. Sort them by kind using the tabs.', 'estat-os' ),
				__( 'Deleting a file here removes it for good, and any property using it will lose that photo. Check before you delete.', 'estat-os' ),
			),
			'estat-insights'    => array(
				__( 'Blogs, articles and market insights — three kinds of writing, all on this one screen.', 'estat-os' ),
				__( 'Use the tabs to switch between them. Each kind can have its own topics.', 'estat-os' ),
				__( 'Writing about your area is one of the cheapest ways to get found on Google.', 'estat-os' ),
			),
			'estat-spreadsheet' => array(
				__( 'Always run a test first. It checks your file without changing anything.', 'estat-os' ),
				__( 'Give each row a reference number so importing the same file twice does not create copies.', 'estat-os' ),
				__( 'Imported properties always arrive as drafts, so you can check them before anything appears on your website.', 'estat-os' ),
				__( 'Download a sample file first and fill that in. It already has the right column names.', 'estat-os' ),
			),
			'estat-reports'     => array(
				__( 'How your office is doing: enquiries coming in, visits arranged, and which team member is handling what.', 'estat-os' ),
				__( 'These numbers only reflect what has been recorded. If visits are not marked as done, they will not show here.', 'estat-os' ),
			),
			'estat-settings'    => array(
				__( 'Fill in your office details first: name, phone and currency.', 'estat-os' ),
				__( 'Nothing here is deleted when you deactivate the plugin.', 'estat-os' ),
				__( 'The Tools tab has the buttons for fixing things: rebuilding the property search, clearing stored copies, and creating practice records to experiment with.', 'estat-os' ),
				__( 'Your data is only ever deleted if you explicitly tick the box on the Tools tab. By default, uninstalling keeps everything.', 'estat-os' ),
			),
		);
		$items = $help[ $page ] ?? array( __( 'Every field on this screen has a short explanation below it.', 'estat-os' ) );
		?>
		<div class="estat-help" id="estat-help" hidden>
			<h2><?php esc_html_e( 'About this screen', 'estat-os' ); ?></h2>
			<ul>
				<?php foreach ( $items as $item ) : ?>
					<li><?php echo esc_html( $item ); ?></li>
				<?php endforeach; ?>
			</ul>
			<h3><?php esc_html_e( 'Information for support', 'estat-os' ); ?></h3>
			<p class="description"><?php esc_html_e( 'If you ask for help, copy this. It contains no passwords or personal information.', 'estat-os' ); ?></p>
			<textarea class="estat-diagnostics" rows="5" readonly><?php echo esc_textarea( self::diagnostics() ); ?></textarea>
			<button type="button" class="button estat-copy-diagnostics"><?php esc_html_e( 'Copy', 'estat-os' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Safe diagnostics text (never includes secrets).
	 *
	 * @return string
	 */
	public static function diagnostics(): string {
		global $wp_version;
		$index = \EstatOS\Search\SearchIndex::status();
		return implode(
			"\n",
			array(
				'Estat.OS: ' . ESTAT_VERSION,
				'Data version: ' . (string) get_option( 'estat_db_version', '0' ),
				'WordPress: ' . (string) $wp_version,
				'PHP: ' . PHP_VERSION,
				'Elementor: ' . ( \EstatOS\Integrations\Elementor\Bridge::active() ? 'active' : 'not active' ),
				'Listings: ' . (string) $index['listings'],
				'Indexed: ' . (string) $index['indexed'],
				'Language: ' . \EstatOS\I18n\Language::resolve(),
				'Practice mode: ' . ( Settings::get( 'practice_mode' ) ? 'on' : 'off' ),
			)
		);
	}

	/**
	 * Show a message passed back after an action.
	 *
	 * @return void
	 */
	public static function notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$notice = isset( $_GET['estat_notice'] ) ? sanitize_key( wp_unslash( $_GET['estat_notice'] ) ) : '';
		$detail = isset( $_GET['estat_detail'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['estat_detail'] ) ) : '';
		// phpcs:enable
		if ( '' === $notice ) {
			return;
		}
		$messages = array(
			// Say what was saved and what happens next, not just "Saved".
			'saved'            => array( 'success', __( 'Saved. Your changes are stored.', 'estat-os' ) ),
			'published'        => array( 'success', __( 'Published. This property is now live on your website for anyone to see.', 'estat-os' ) ),
			'draft'            => array( 'success', __( 'Saved as a draft. Only your office can see it — nobody on the website can.', 'estat-os' ) ),
			'deleted'          => array( 'success', __( 'Removed. It is no longer in your office records.', 'estat-os' ) ),
			'trashed'          => array( 'success', __( 'Moved to the bin. It is off your website, and you can put it back any time.', 'estat-os' ) ),
			'restored'         => array( 'success', __( 'Put back. It is saved as a draft, so check it over before you publish it again.', 'estat-os' ) ),
			'undone'           => array( 'success', __( 'Undone. The details are back to how they were before the last save. Whether it is on the website was left as it is.', 'estat-os' ) ),
			'setup_done'       => array( 'success', __( 'All set. Your website pages are live — have a look at them below.', 'estat-os' ) ),
			'assigned'         => array( 'success', __( 'Done. That team member is now responsible for this enquiry.', 'estat-os' ) ),
			'visit_scheduled'  => array( 'success', __( 'Site visit booked. It will appear on your Today screen on the day.', 'estat-os' ) ),
			'erased'           => array( 'success', __( 'The name, phone and email have been erased for good. The record of the enquiry itself is kept.', 'estat-os' ) ),
			'imported'         => array( 'success', __( 'Import finished. The new properties were saved as drafts so you can check them before publishing.', 'estat-os' ) ),
			'practice_seeded'  => array( 'success', __( 'Practice records have been created for you to try.', 'estat-os' ) ),
			'practice_cleared' => array( 'success', __( 'All practice records have been removed.', 'estat-os' ) ),
			'index_rebuilt'    => array( 'success', __( 'The property search has been refreshed.', 'estat-os' ) ),
			'error'            => array( 'error', '' !== $detail ? $detail : __( 'Something went wrong. Please check and try again.', 'estat-os' ) ),
			'forbidden'        => array( 'error', __( 'You do not have permission to do that.', 'estat-os' ) ),
		);
		$message = $messages[ $notice ] ?? null;
		if ( ! $message ) {
			return;
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $message[0] ),
			esc_html( $message[1] )
		);
	}

	/**
	 * Open a card.
	 *
	 * @param string $title    Card title.
	 * @param string $subtitle Optional subtitle.
	 * @return void
	 */
	/**
	 * Begin a fold-away group of fields nobody needs on an ordinary day.
	 *
	 * Most properties are added with a name, a price and a locality. The rest
	 * of the fields exist for the one listing in twenty that needs them, and
	 * showing all of them at once is what makes the screen feel heavy. This
	 * tucks them behind a single line the office can open when they want it.
	 *
	 * @param string $title Plain-language summary of what is inside.
	 * @param string $note  Optional reassurance that skipping it is fine.
	 * @return void
	 */
	public static function extras_open( string $title, string $note = '', bool $open = false ): void {
		if ( '' === $note ) {
			$note = __( 'You can leave all of this empty. It only changes what shows on the property page.', 'estat-os' );
		}
		printf(
			'<details class="estat-extras"%1$s><summary><span class="estat-extras-title">%2$s</span><span class="estat-extras-note">%3$s</span></summary><div class="estat-extras-body">',
			$open ? ' open' : '',
			esc_html( $title ),
			esc_html( $note )
		);
	}

	/**
	 * Is any of these fields already filled in?
	 *
	 * A group that holds real information must open by itself, otherwise the
	 * office reopens a listing, sees a short form, and reasonably concludes
	 * that the details they typed have been lost.
	 *
	 * @param array<int,mixed> $values Current values.
	 * @return bool
	 */
	public static function has_content( array $values ): bool {
		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				if ( array() !== $value ) {
					return true;
				}
				continue;
			}
			$value = (string) $value;
			if ( '' !== $value && '0' !== $value ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Close a fold-away group.
	 *
	 * @return void
	 */
	public static function extras_close(): void {
		echo '</div></details>';
	}

	public static function card_open( string $title = '', string $subtitle = '' ): void {
		echo '<section class="estat-card-panel">';
		if ( '' !== $title ) {
			echo '<h2>' . esc_html( $title ) . '</h2>';
		}
		if ( '' !== $subtitle ) {
			echo '<p class="description">' . esc_html( $subtitle ) . '</p>';
		}
	}

	/**
	 * Close a card.
	 *
	 * @return void
	 */
	public static function card_close(): void {
		echo '</section>';
	}

	/**
	 * A statistic tile.
	 *
	 * @param string $label    Label.
	 * @param string $value    Value.
	 * @param string $url      Optional link.
	 * @param string $tone     good|warn|plain.
	 * @return void
	 */
	public static function stat( string $label, string $value, string $url = '', string $tone = 'plain' ): void {
		$inner = '<span class="estat-stat-value">' . esc_html( $value ) . '</span><span class="estat-stat-label">' . esc_html( $label ) . '</span>';
		if ( '' !== $url ) {
			echo '<a class="estat-stat estat-stat-' . esc_attr( $tone ) . '" href="' . esc_url( $url ) . '">' . $inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		echo '<div class="estat-stat estat-stat-' . esc_attr( $tone ) . '">' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * A labelled form field with help text and validation slot.
	 *
	 * @param array<string,mixed> $args name, label, type, value, help, example, required, options, rows.
	 * @return void
	 */
	public static function field( array $args ): void {
		$name     = (string) ( $args['name'] ?? '' );
		$id       = 'estat-' . sanitize_html_class( $name );
		$type     = (string) ( $args['type'] ?? 'text' );
		$value    = $args['value'] ?? '';
		$required = ! empty( $args['required'] );
		$help     = (string) ( $args['help'] ?? '' );
		$example  = (string) ( $args['example'] ?? '' );
		$options  = (array) ( $args['options'] ?? array() );
		?>
		<div class="estat-field-row">
			<label class="estat-field-label" for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( (string) ( $args['label'] ?? '' ) ); ?>
				<?php if ( $required ) : ?>
					<span class="estat-required"><?php esc_html_e( 'required', 'estat-os' ); ?></span>
				<?php else : ?>
					<span class="estat-optional"><?php esc_html_e( 'optional', 'estat-os' ); ?></span>
				<?php endif; ?>
			</label>
			<?php
			switch ( $type ) {
				case 'textarea':
					?>
					<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="<?php echo esc_attr( (string) (int) ( $args['rows'] ?? 4 ) ); ?>" <?php echo $required ? 'required' : ''; ?> placeholder="<?php echo esc_attr( $example ); ?>"><?php echo esc_textarea( (string) $value ); ?></textarea>
					<?php
					break;
				case 'select':
					?>
					<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php echo $required ? 'required' : ''; ?>>
						<?php foreach ( $options as $option_value => $option_label ) : ?>
							<option value="<?php echo esc_attr( (string) $option_value ); ?>" <?php selected( (string) $value, (string) $option_value ); ?>><?php echo esc_html( (string) $option_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php
					break;
				case 'checkbox':
					?>
					<label class="estat-inline-check">
						<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( (bool) $value ); ?> />
						<span><?php echo esc_html( (string) ( $args['checkbox_label'] ?? __( 'Yes', 'estat-os' ) ) ); ?></span>
					</label>
					<?php
					break;
				case 'image':
					$attachment_id = (int) $value;
					?>
					<?php $target = '#' . $id; ?>
					<div class="estat-image-picker" data-target="<?php echo esc_attr( $target ); ?>">
						<button
							type="button"
							class="estat-image-preview estat-pick-image"
							data-target="<?php echo esc_attr( $target ); ?>"
							aria-label="<?php esc_attr_e( 'Choose a photo', 'estat-os' ); ?>"
						>
							<?php if ( $attachment_id > 0 ) : ?>
								<?php echo wp_get_attachment_image( $attachment_id, 'medium' ); ?>
							<?php else : ?>
								<span class="estat-image-empty"><?php esc_html_e( 'Tap to choose a photo', 'estat-os' ); ?></span>
							<?php endif; ?>
						</button>
						<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $attachment_id ); ?>" />
						<button type="button" class="button estat-pick-image" data-target="<?php echo esc_attr( $target ); ?>"><?php esc_html_e( 'Choose a photo', 'estat-os' ); ?></button>
						<button type="button" class="button-link estat-clear-image" data-target="<?php echo esc_attr( $target ); ?>"><?php esc_html_e( 'Remove', 'estat-os' ); ?></button>
					</div>
					<?php
					break;
				case 'gallery':
					$ids = array_map( 'absint', (array) $value );
					?>
					<?php
					$target    = '#' . $id;
					$list_id   = $id . '-list';
					$list_href = '#' . $list_id;
					?>
					<div class="estat-gallery-picker" data-target="<?php echo esc_attr( $target ); ?>" data-list="<?php echo esc_attr( $list_href ); ?>">
						<ul class="estat-gallery-list" id="<?php echo esc_attr( $list_id ); ?>" data-target="<?php echo esc_attr( $target ); ?>">
							<?php foreach ( $ids as $attachment_id ) : ?>
								<li data-id="<?php echo esc_attr( (string) $attachment_id ); ?>" draggable="true" tabindex="0">
									<?php echo wp_get_attachment_image( (int) $attachment_id, 'thumbnail' ); ?>
									<button type="button" class="estat-gallery-remove" aria-label="<?php esc_attr_e( 'Remove this photo', 'estat-os' ); ?>">×</button>
								</li>
							<?php endforeach; ?>
						</ul>
						<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>" />
						<button type="button" class="button estat-pick-gallery" data-list="<?php echo esc_attr( $list_href ); ?>" data-target="<?php echo esc_attr( $target ); ?>"><?php esc_html_e( 'Add photos', 'estat-os' ); ?></button>
						<p class="description"><?php esc_html_e( 'Drag the photos to change their order. The first one is used as the main photo if you have not chosen one.', 'estat-os' ); ?></p>
					</div>
					<?php
					break;
				default:
					?>
					<input
						type="<?php echo esc_attr( $type ); ?>"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( (string) $value ); ?>"
						placeholder="<?php echo esc_attr( $example ); ?>"
						<?php echo $required ? 'required' : ''; ?>
						<?php echo isset( $args['min'] ) ? 'min="' . esc_attr( (string) $args['min'] ) . '"' : ''; ?>
						<?php echo isset( $args['max'] ) ? 'max="' . esc_attr( (string) $args['max'] ) . '"' : ''; ?>
						<?php echo isset( $args['step'] ) ? 'step="' . esc_attr( (string) $args['step'] ) . '"' : ''; ?>
					/>
					<?php
					break;
			}
			?>
			<?php if ( '' !== $help ) : ?>
				<p class="description"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $example && in_array( $type, array( 'select', 'checkbox' ), true ) ) : ?>
				<p class="description"><?php echo esc_html( sprintf( /* translators: %s: example value */ __( 'For example: %s', 'estat-os' ), $example ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * The highlight tick-boxes, grouped, with a live preview of what will end
	 * up on the property card.
	 *
	 * @param string[] $selected Slugs already ticked.
	 * @return void
	 */
	public static function highlights( array $selected = array() ): void {
		$groups = Highlights::by_group();
		$labels = Highlights::groups();
		?>
		<div class="estat-highlights" data-card-limit="<?php echo esc_attr( (string) Highlights::CARD_LIMIT ); ?>">
			<div class="estat-highlights-intro">
				<h3><?php esc_html_e( 'Highlights', 'estat-os' ); ?></h3>
				<p class="description">
					<?php
					printf(
						/* translators: %d: how many highlights appear on a card. */
						esc_html__( 'Tick whatever is true for this property. The first %d appear as small tags on the property card, so tick the strongest selling points first.', 'estat-os' ),
						(int) Highlights::CARD_LIMIT
					);
					?>
				</p>
			</div>

			<div class="estat-highlights-preview" aria-live="polite">
				<span class="estat-highlights-preview-label"><?php esc_html_e( 'On the card:', 'estat-os' ); ?></span>
				<span class="estat-highlights-preview-chips"></span>
			</div>

			<?php foreach ( $groups as $group => $items ) : ?>
				<fieldset class="estat-highlight-group">
					<legend><?php echo esc_html( $labels[ $group ] ?? ucfirst( $group ) ); ?></legend>
					<div class="estat-highlight-grid">
						<?php foreach ( $items as $slug => $item ) : ?>
							<label class="estat-highlight<?php echo in_array( $slug, $selected, true ) ? ' is-on' : ''; ?>">
								<input
									type="checkbox"
									name="highlights[]"
									value="<?php echo esc_attr( $slug ); ?>"
									data-label="<?php echo esc_attr( (string) $item['label'] ); ?>"
									data-icon="<?php echo esc_attr( (string) $item['icon'] ); ?>"
									<?php checked( in_array( $slug, $selected, true ) ); ?>
								/>
								<span class="estat-highlight-icon"><?php echo Icon::render( (string) $item['icon'], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span class="estat-highlight-text"><?php echo esc_html( (string) $item['label'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Empty state block.
	 *
	 * @param string $message Message.
	 * @param string $action  Optional action URL.
	 * @param string $label   Action label.
	 * @return void
	 */
	/**
	 * The standard search-and-filter bar used by every list screen.
	 *
	 * Screens used to hand-write this row, which is why Listings, Enquiries and
	 * Site Visits all looked slightly different. They now describe what they
	 * need and get an identical bar.
	 *
	 * @param array<string,mixed> $args page, search, search_label, filters, count, action, action_url.
	 * @return void
	 */
	/**
	 * Open a form that wraps a list so its tick boxes can be acted on.
	 *
	 * @param string $page Page slug to return to.
	 * @return void
	 */
	public static function bulk_open( string $page ): void {
		?>
		<form method="post" class="estat-bulk-form" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $page ) ); ?>">
			<?php wp_nonce_field( 'estat_bulk_records', 'estat_nonce' ); ?>
			<input type="hidden" name="estat_action" value="bulk_records" />
			<input type="hidden" name="back_page" value="<?php echo esc_attr( $page ); ?>" />
		<?php
	}

	/**
	 * The bar of bulk choices, and the close of the wrapping form.
	 *
	 * It sits below the table rather than above it, because until something is
	 * ticked there is nothing to act on, and it stays hidden until then.
	 *
	 * @return void
	 */
	public static function bulk_close(): void {
		?>
			<div class="estat-bulk-bar" hidden>
				<span class="estat-bulk-count" role="status" aria-live="polite"></span>

				<label class="screen-reader-text" for="estat-bulk-action"><?php esc_html_e( 'What to do with the ticked ones', 'estat-os' ); ?></label>
				<select id="estat-bulk-action" name="bulk_action">
					<option value=""><?php esc_html_e( 'Choose an action…', 'estat-os' ); ?></option>
					<?php foreach ( Actions::bulk_operations() as $key => $operation ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $operation['label'] ); ?></option>
					<?php endforeach; ?>
				</select>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Do it', 'estat-os' ); ?></button>
				<button type="button" class="button-link estat-bulk-clear"><?php esc_html_e( 'Clear the ticks', 'estat-os' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * The tick box in a table heading that selects every row.
	 *
	 * @return void
	 */
	public static function bulk_header(): void {
		?>
		<th scope="col" class="estat-bulk-cell">
			<label class="screen-reader-text" for="estat-bulk-all"><?php esc_html_e( 'Tick every row', 'estat-os' ); ?></label>
			<input type="checkbox" id="estat-bulk-all" class="estat-bulk-all" />
		</th>
		<?php
	}

	/**
	 * The tick box for one row.
	 *
	 * @param int    $record_id Record ID.
	 * @param string $title     Record title, for the accessible name.
	 * @return void
	 */
	public static function bulk_cell( int $record_id, string $title ): void {
		$id = 'estat-tick-' . $record_id;
		?>
		<td class="estat-bulk-cell" data-label="<?php esc_attr_e( 'Tick', 'estat-os' ); ?>">
			<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>">
				<?php
				printf(
					/* translators: %s: the name of the record. */
					esc_html__( 'Tick %s', 'estat-os' ),
					esc_html( '' !== trim( $title ) ? $title : (string) $record_id )
				);
				?>
			</label>
			<input
				type="checkbox"
				id="<?php echo esc_attr( $id ); ?>"
				class="estat-bulk-tick"
				name="record_ids[]"
				value="<?php echo esc_attr( (string) $record_id ); ?>"
			/>
		</td>
		<?php
	}

	/**
	 * Page links under a list.
	 *
	 * Projects, Team and Spreadsheet each capped their list at a fixed number
	 * and showed nothing beyond it. An office with sixty projects saw fifty
	 * and had no way to reach the rest, and nothing on screen said so.
	 *
	 * @param int    $pages   Total number of pages.
	 * @param int    $current Current page.
	 * @param string $label   Accessible name for the navigation.
	 * @return void
	 */
	public static function pagination( int $pages, int $current, string $label ): void {
		if ( $pages < 2 ) {
			return;
		}

		$links = paginate_links(
			array(
				'total'   => $pages,
				'current' => max( 1, $current ),
				'type'    => 'array',
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
			)
		);

		if ( ! is_array( $links ) ) {
			return;
		}

		echo '<nav class="estat-pagination" aria-label="' . esc_attr( $label ) . '"><ul>';
		foreach ( $links as $link ) {
			echo '<li>' . wp_kses_post( $link ) . '</li>';
		}
		echo '</ul></nav>';
	}

	public static function toolbar( array $args ): void {
		$page         = isset( $args['page'] ) ? (string) $args['page'] : '';
		$search       = isset( $args['search'] ) ? (string) $args['search'] : '';
		$search_label = isset( $args['search_label'] ) ? (string) $args['search_label'] : __( 'Search', 'estat-os' );
		$filters      = isset( $args['filters'] ) && is_array( $args['filters'] ) ? $args['filters'] : array();
		$hidden       = isset( $args['hidden'] ) && is_array( $args['hidden'] ) ? $args['hidden'] : array();
		$count        = isset( $args['count'] ) ? (int) $args['count'] : -1;
		$count_label  = isset( $args['count_label'] ) ? (string) $args['count_label'] : '';
		$action       = isset( $args['action'] ) ? (string) $args['action'] : '';
		$action_url   = isset( $args['action_url'] ) ? (string) $args['action_url'] : '';

		$id = 'estat-toolbar-' . sanitize_key( $page );
		?>
		<form method="get" class="estat-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />
			<?php foreach ( $hidden as $key => $value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( (string) $key ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" />
			<?php endforeach; ?>

			<?php if ( false !== ( $args['show_search'] ?? true ) ) : ?>
				<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-s"><?php echo esc_html( $search_label ); ?></label>
				<input
					type="search"
					id="<?php echo esc_attr( $id ); ?>-s"
					name="s"
					value="<?php echo esc_attr( $search ); ?>"
					placeholder="<?php echo esc_attr( $search_label ); ?>"
				/>
			<?php endif; ?>

			<?php foreach ( $filters as $filter ) : ?>
				<?php
				$name    = (string) ( $filter['name'] ?? '' );
				$options = is_array( $filter['options'] ?? null ) ? $filter['options'] : array();
				$value   = (string) ( $filter['value'] ?? '' );
				$label   = (string) ( $filter['label'] ?? $name );

				if ( '' === $name || ! $options ) {
					continue;
				}
				?>
				<label class="screen-reader-text" for="<?php echo esc_attr( $id . '-' . $name ); ?>"><?php echo esc_html( $label ); ?></label>
				<select id="<?php echo esc_attr( $id . '-' . $name ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<?php foreach ( $options as $option_value => $option_label ) : ?>
						<option value="<?php echo esc_attr( (string) $option_value ); ?>" <?php selected( $value, (string) $option_value ); ?>>
							<?php echo esc_html( (string) $option_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endforeach; ?>

			<button type="submit" class="button"><?php esc_html_e( 'Show', 'estat-os' ); ?></button>

			<?php if ( '' !== $action && '' !== $action_url ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $action ); ?></a>
			<?php endif; ?>

			<?php if ( $count >= 0 ) : ?>
				<span class="estat-filter-count">
					<?php
					echo esc_html(
						'' !== $count_label
							? $count_label
							: sprintf(
								/* translators: %s: number of results. */
								_n( '%s result', '%s results', $count, 'estat-os' ),
								number_format_i18n( $count )
							)
					);
					?>
				</span>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * The remove / put-back buttons for one record row.
	 *
	 * Live records only ever offer "Remove", which moves them to the bin. A
	 * record already in the bin offers "Put back" and a permanent delete that
	 * asks the office to type DELETE first. Nothing here can destroy anything
	 * in a single click.
	 *
	 * @param int    $record_id Record ID.
	 * @param string $status    Current post status.
	 * @return void
	 */
	public static function record_actions( int $record_id, string $status ): void {
		if ( 'trash' === $status ) {
			$restore = wp_nonce_url(
				admin_url( 'admin.php?estat_action=restore_record&record_id=' . $record_id ),
				'estat_restore_record_' . $record_id,
				'estat_nonce'
			);
			$delete  = wp_nonce_url(
				admin_url( 'admin.php?estat_action=delete_record&record_id=' . $record_id . '&confirm=DELETE' ),
				'estat_delete_record_' . $record_id,
				'estat_nonce'
			);
			printf(
				'<a class="button button-small" href="%1$s">%2$s</a> <a class="button button-small estat-button-danger" href="%3$s" data-estat-confirm="%4$s">%5$s</a>',
				esc_url( $restore ),
				esc_html__( 'Put back', 'estat-os' ),
				esc_url( $delete ),
				esc_attr__( 'This deletes it for good and cannot be undone. Are you sure?', 'estat-os' ),
				esc_html__( 'Delete for good', 'estat-os' )
			);
			return;
		}

		// Only offered when there is genuinely something to go back to, so the
		// button never promises something it cannot do.
		if ( Undo::available( $record_id ) ) {
			$undo = wp_nonce_url(
				admin_url( 'admin.php?estat_action=undo_record&record_id=' . $record_id ),
				'estat_undo_record_' . $record_id,
				'estat_nonce'
			);
			printf(
				'<a class="button button-small" href="%1$s" data-estat-confirm="%2$s">%3$s</a> ',
				esc_url( $undo ),
				esc_attr__( 'This puts the details back to how they were before the last save. Carry on?', 'estat-os' ),
				esc_html__( 'Undo last edit', 'estat-os' )
			);
		}

		$trash = wp_nonce_url(
			admin_url( 'admin.php?estat_action=trash_record&record_id=' . $record_id ),
			'estat_trash_record_' . $record_id,
			'estat_nonce'
		);
		printf(
			'<a class="button button-small" href="%1$s" data-estat-confirm="%2$s">%3$s</a>',
			esc_url( $trash ),
			esc_attr__( 'This moves it to the bin and takes it off your website. You can put it back later.', 'estat-os' ),
			esc_html__( 'Remove', 'estat-os' )
		);
	}

	public static function empty_state( $message, string $action = '', string $label = '' ): void {
		// Screens may pass a plain sentence (the original form) or a fuller
		// description. Both are supported so no screen had to change.
		$args = is_array( $message ) ? $message : array( 'message' => (string) $message );

		$title   = isset( $args['title'] ) ? (string) $args['title'] : '';
		$text    = isset( $args['message'] ) ? (string) $args['message'] : '';
		$icon    = isset( $args['icon'] ) ? (string) $args['icon'] : '';
		$action  = isset( $args['action'] ) ? (string) $args['action'] : $action;
		$label   = isset( $args['label'] ) ? (string) $args['label'] : $label;
		$hint    = isset( $args['hint'] ) ? (string) $args['hint'] : '';
		$steps   = isset( $args['steps'] ) && is_array( $args['steps'] ) ? $args['steps'] : array();
		$is_fine = ! empty( $args['reassuring'] );

		echo '<div class="estat-empty-state' . ( $is_fine ? ' is-reassuring' : '' ) . '">';

		if ( '' !== $icon ) {
			echo '<span class="estat-empty-icon">' . Icon::render( $icon, 40 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( '' !== $title ) {
			echo '<p class="estat-empty-title">' . esc_html( $title ) . '</p>';
		}

		if ( '' !== $text ) {
			echo '<p class="estat-empty-text">' . esc_html( $text ) . '</p>';
		}

		// A short numbered path out of the empty screen beats a dead end.
		if ( $steps ) {
			echo '<ol class="estat-empty-steps">';
			foreach ( $steps as $step ) {
				echo '<li>' . esc_html( (string) $step ) . '</li>';
			}
			echo '</ol>';
		}

		if ( '' !== $action && '' !== $label ) {
			echo '<a class="button button-primary" href="' . esc_url( $action ) . '">' . esc_html( $label ) . '</a>';
		}

		if ( '' !== $hint ) {
			echo '<p class="estat-empty-hint">' . esc_html( $hint ) . '</p>';
		}

		echo '</div>';
	}
}
