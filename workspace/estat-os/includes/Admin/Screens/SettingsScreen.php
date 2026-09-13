<?php
/**
 * Office settings.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\I18n\Language;
use EstatOS\Search\SearchIndex;
use EstatOS\Settings\Settings;
use EstatOS\Support\Vocabulary;
use EstatOS\Webhooks\Webhooks;

defined( 'ABSPATH' ) || exit;

/**
 * Everything about the office in one place, grouped into plain-language tabs.
 */
final class SettingsScreen {

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'estat_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to change office settings.', 'estat-os' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'office';
		$tabs = array(
			'office'    => __( 'Your office', 'estat-os' ),
			'website'   => __( 'Website', 'estat-os' ),
			'language'  => __( 'Language', 'estat-os' ),
			'emails'    => __( 'Emails', 'estat-os' ),
			'advanced'  => __( 'Connections', 'estat-os' ),
			'tools'     => __( 'Tools', 'estat-os' ),
		);
		$tab = isset( $tabs[ $tab ] ) ? $tab : 'office';
		$s   = Settings::all();

		echo '<nav class="nav-tab-wrapper estat-tabs">';
		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a class="nav-tab %1$s" href="%2$s">%3$s</a>',
				esc_attr( $slug === $tab ? 'nav-tab-active' : '' ),
				esc_url( admin_url( 'admin.php?page=estat-settings&tab=' . $slug ) ),
				esc_html( (string) $label )
			);
		}
		echo '</nav>';

		if ( 'tools' === $tab ) {
			self::tools();
			return;
		}
		?>
		<form method="post" class="estat-form-admin" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-settings&tab=' . $tab ) ); ?>">
			<?php
			wp_nonce_field( 'estat_save_settings', 'estat_nonce' );
			echo '<input type="hidden" name="estat_action" value="save_settings" />';
			echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />';

			switch ( $tab ) {
				case 'website':
					self::website( $s );
					break;
				case 'language':
					self::language( $s );
					break;
				case 'emails':
					self::emails( $s );
					break;
				case 'advanced':
					self::advanced( $s );
					break;
				default:
					self::office( $s );
			}
			?>
			<div class="estat-form-actions-admin estat-sticky-actions">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save settings', 'estat-os' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Office identity fields.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return void
	 */
	private static function office( array $s ): void {
		Partials::card_open( __( 'Who you are', 'estat-os' ), __( 'This appears on your website and in the emails you send.', 'estat-os' ) );
		Partials::field( array( 'name' => 'settings[office_name]', 'label' => __( 'Office name', 'estat-os' ), 'value' => $s['office_name'], 'example' => __( 'Sharma Properties', 'estat-os' ) ) );
		Partials::field( array( 'name' => 'settings[tagline]', 'label' => __( 'One line about the office', 'estat-os' ), 'value' => $s['tagline'] ) );
		Partials::field( array( 'name' => 'settings[contact_person]', 'label' => __( 'Main contact person', 'estat-os' ), 'value' => $s['contact_person'] ) );
		Partials::field( array( 'name' => 'settings[phone]', 'label' => __( 'Phone number', 'estat-os' ), 'value' => $s['phone'], 'example' => '+91 98765 43210' ) );
		Partials::field( array( 'name' => 'settings[whatsapp]', 'label' => __( 'WhatsApp number', 'estat-os' ), 'value' => $s['whatsapp'] ) );
		Partials::field( array( 'name' => 'settings[email]', 'type' => 'email', 'label' => __( 'Email address', 'estat-os' ), 'value' => $s['email'] ) );
		Partials::field( array( 'name' => 'settings[address]', 'type' => 'textarea', 'rows' => 3, 'label' => __( 'Office address', 'estat-os' ), 'value' => $s['address'] ) );
		Partials::field( array( 'name' => 'settings[office_hours]', 'label' => __( 'Opening hours', 'estat-os' ), 'value' => $s['office_hours'], 'example' => __( 'Mon to Sat, 10am to 7pm', 'estat-os' ) ) );
		Partials::field( array( 'name' => 'settings[logo_id]', 'type' => 'image', 'label' => __( 'Office logo', 'estat-os' ), 'value' => $s['logo_id'] ) );
		Partials::card_close();

		Partials::card_open(
			__( 'How the office screens look', 'estat-os' ),
			__( 'This only changes what you and your team see while working. Your website is not affected.', 'estat-os' )
		);
		Partials::field(
			array(
				'name'    => 'settings[admin_theme]',
				'type'    => 'select',
				'label'   => __( 'Screen colours', 'estat-os' ),
				'value'   => $s['admin_theme'],
				'options' => array(
					'light' => __( 'Light — bright background', 'estat-os' ),
					'dark'  => __( 'Dark — easier at night', 'estat-os' ),
					'auto'  => __( 'Automatic — follow this computer', 'estat-os' ),
				),
				'help'    => __( 'Automatic switches with your computer, so it goes dark in the evening on its own.', 'estat-os' ),
			)
		);
		Partials::card_close();

		Partials::card_open( __( 'Money and measurements', 'estat-os' ) );
		Partials::field( array( 'name' => 'settings[currency_symbol]', 'label' => __( 'Currency symbol', 'estat-os' ), 'value' => $s['currency_symbol'] ) );
		Partials::field( array( 'name' => 'settings[currency]', 'label' => __( 'Currency code', 'estat-os' ), 'value' => $s['currency'], 'example' => 'INR' ) );
		Partials::field(
			array(
				'name'    => 'settings[price_format]',
				'type'    => 'select',
				'label'   => __( 'How prices should read', 'estat-os' ),
				'value'   => $s['price_format'],
				'options' => array(
					'indian'    => __( 'Indian style: ₹8.5 Cr, ₹45 Lakh', 'estat-os' ),
					'compact'   => __( 'Short style: ₹8.5M, ₹450K', 'estat-os' ),
					'plain'     => __( 'Full number: ₹85,000,000', 'estat-os' ),
				),
				'help'    => __( 'You always type the plain number. This only changes how visitors see it.', 'estat-os' ),
			)
		);
		Partials::field( array( 'name' => 'settings[default_area_unit]', 'type' => 'select', 'label' => __( 'Area unit you use most', 'estat-os' ), 'value' => $s['default_area_unit'], 'options' => Vocabulary::area_units(), 'help' => __( 'Everything is stored in square feet behind the scenes, so you can mix units safely.', 'estat-os' ) ) );
		Partials::field( array( 'name' => 'settings[regulatory_label]', 'label' => __( 'Name of your registration authority', 'estat-os' ), 'value' => $s['regulatory_label'], 'example' => 'RERA' ) );
		Partials::field( array( 'name' => 'settings[show_regulatory]', 'type' => 'checkbox', 'label' => __( 'Registration numbers', 'estat-os' ), 'checkbox_label' => __( 'Show them publicly on each property', 'estat-os' ), 'value' => (bool) $s['show_regulatory'] ) );
		Partials::card_close();
	}

	/**
	 * Website behaviour fields.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return void
	 */
	private static function website( array $s ): void {
		Partials::card_open( __( 'How your property pages behave', 'estat-os' ) );
		Partials::field( array( 'name' => 'settings[listings_per_page]', 'type' => 'number', 'label' => __( 'Properties shown per page', 'estat-os' ), 'value' => (int) $s['listings_per_page'], 'min' => 3, 'max' => 60 ) );
		Partials::field( array( 'name' => 'settings[enable_favorites]', 'type' => 'checkbox', 'label' => __( 'Saved properties', 'estat-os' ), 'checkbox_label' => __( 'Let visitors save properties they like', 'estat-os' ), 'value' => (bool) $s['enable_favorites'] ) );
		Partials::field( array( 'name' => 'settings[enable_compare]', 'type' => 'checkbox', 'label' => __( 'Side by side', 'estat-os' ), 'checkbox_label' => __( 'Let visitors compare up to four properties', 'estat-os' ), 'value' => (bool) $s['enable_compare'] ) );
		Partials::field( array( 'name' => 'settings[enable_schema]', 'type' => 'checkbox', 'label' => __( 'Search engines', 'estat-os' ), 'checkbox_label' => __( 'Help Google understand your listings', 'estat-os' ), 'value' => (bool) $s['enable_schema'], 'help' => __( 'Recommended. This adds hidden information that search engines read.', 'estat-os' ) ) );
		Partials::field( array( 'name' => 'settings[verification_mode]', 'type' => 'select', 'label' => __( 'Checking listings before they go live', 'estat-os' ), 'value' => $s['verification_mode'], 'options' => array( 'optional' => __( 'Anyone can publish', 'estat-os' ), 'required' => __( 'Only verified listings appear on the website', 'estat-os' ) ) ) );
		Partials::field( array( 'name' => 'settings[listing_expiry_days]', 'type' => 'number', 'label' => __( 'Retire listings after this many days', 'estat-os' ), 'value' => (int) $s['listing_expiry_days'], 'min' => 0, 'max' => 3650, 'help' => __( 'Zero means never. Retired listings are moved to drafts, never deleted.', 'estat-os' ) ) );
		Partials::card_close();

		Partials::card_open( __( 'Maps', 'estat-os' ), __( 'Maps work out of the box with free OpenStreetMap. A Google key is only needed if you prefer Google maps.', 'estat-os' ) );
		Partials::field( array( 'name' => 'settings[map_provider]', 'type' => 'select', 'label' => __( 'Map style', 'estat-os' ), 'value' => $s['map_provider'], 'options' => array( 'osm' => __( 'OpenStreetMap (free, no setup)', 'estat-os' ), 'google' => __( 'Google Maps (needs a key)', 'estat-os' ), 'none' => __( 'Do not show maps', 'estat-os' ) ) ) );
		Partials::field( array( 'name' => 'settings[google_maps_key]', 'label' => __( 'Google Maps key', 'estat-os' ), 'value' => $s['google_maps_key'] ) );
		Partials::field( array( 'name' => 'settings[map_default_lat]', 'label' => __( 'Map starts at this latitude', 'estat-os' ), 'value' => $s['map_default_lat'], 'example' => '28.6139' ) );
		Partials::field( array( 'name' => 'settings[map_default_lng]', 'label' => __( 'Map starts at this longitude', 'estat-os' ), 'value' => $s['map_default_lng'], 'example' => '77.2090' ) );
		Partials::field( array( 'name' => 'settings[map_default_zoom]', 'type' => 'number', 'label' => __( 'How close the map starts', 'estat-os' ), 'value' => (int) $s['map_default_zoom'], 'min' => 1, 'max' => 20 ) );
		Partials::card_close();
	}

	/**
	 * Language fields.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return void
	 */
	private static function language( array $s ): void {
		$choices = Language::available();

		Partials::card_open( __( 'Language', 'estat-os' ), __( 'Hinglish is written in normal English letters, the way most offices actually speak.', 'estat-os' ) );
		Partials::field( array( 'name' => 'settings[language]', 'type' => 'select', 'label' => __( 'Language inside the office screens', 'estat-os' ), 'value' => $s['language'], 'options' => array_merge( array( 'auto' => __( 'Follow the website language', 'estat-os' ) ), $choices ) ) );
		Partials::field( array( 'name' => 'settings[frontend_language]', 'type' => 'select', 'label' => __( 'Language on the public website', 'estat-os' ), 'value' => $s['frontend_language'], 'options' => array_merge( array( 'auto' => __( 'Follow the website language', 'estat-os' ) ), $choices ) ) );
		Partials::field( array( 'name' => 'settings[allow_visitor_lang]', 'type' => 'checkbox', 'label' => __( 'Visitor choice', 'estat-os' ), 'checkbox_label' => __( 'Let visitors switch language themselves', 'estat-os' ), 'value' => (bool) $s['allow_visitor_lang'] ) );
		Partials::card_close();

		Partials::card_open( __( 'Adding another language', 'estat-os' ) );
		echo '<p>' . esc_html__( 'Copy the translation file from the plugin\'s languages folder, translate it with a free tool such as Poedit, and save it into wp-content/languages/plugins. It will appear in the list above automatically.', 'estat-os' ) . '</p>';
		Partials::card_close();
	}

	/**
	 * Notification fields.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return void
	 */
	private static function emails( array $s ): void {
		Partials::card_open( __( 'When should we email you?', 'estat-os' ) );
		Partials::field( array( 'name' => 'settings[notify_email]', 'type' => 'email', 'label' => __( 'Send office emails to', 'estat-os' ), 'value' => $s['notify_email'], 'help' => __( 'Leave empty to use the website administrator address.', 'estat-os' ) ) );
		Partials::field( array( 'name' => 'settings[notify_new_lead]', 'type' => 'checkbox', 'label' => __( 'New enquiry', 'estat-os' ), 'checkbox_label' => __( 'Email me when someone enquires', 'estat-os' ), 'value' => (bool) $s['notify_new_lead'] ) );
		Partials::field( array( 'name' => 'settings[notify_new_visit]', 'type' => 'checkbox', 'label' => __( 'Site visit booked', 'estat-os' ), 'checkbox_label' => __( 'Email me when a visit is arranged', 'estat-os' ), 'value' => (bool) $s['notify_new_visit'] ) );
		Partials::field( array( 'name' => 'settings[notify_change_request]', 'type' => 'checkbox', 'label' => __( 'A change asked for from the field', 'estat-os' ), 'checkbox_label' => __( 'Email me when someone proposes a change to a property', 'estat-os' ), 'value' => (bool) $s['notify_change_request'] ) );
		Partials::field( array( 'name' => 'settings[notify_followup]', 'type' => 'checkbox', 'label' => __( 'Follow-up reminders', 'estat-os' ), 'checkbox_label' => __( 'Remind me each morning about follow-ups due today', 'estat-os' ), 'value' => (bool) $s['notify_followup'] ) );
		Partials::field( array( 'name' => 'settings[notify_expiry]', 'type' => 'checkbox', 'label' => __( 'Retired listings', 'estat-os' ), 'checkbox_label' => __( 'Tell me when listings are retired automatically', 'estat-os' ), 'value' => (bool) $s['notify_expiry'] ) );
		Partials::field( array( 'name' => 'settings[notify_import]', 'type' => 'checkbox', 'label' => __( 'Finished imports', 'estat-os' ), 'checkbox_label' => __( 'Tell me when a spreadsheet import finishes', 'estat-os' ), 'value' => (bool) $s['notify_import'] ) );
		Partials::field( array( 'name' => 'settings[notify_webhook_fail]', 'type' => 'checkbox', 'label' => __( 'Connection problems', 'estat-os' ), 'checkbox_label' => __( 'Tell me if a connected app stops receiving data', 'estat-os' ), 'value' => (bool) $s['notify_webhook_fail'] ) );
		Partials::card_close();
	}

	/**
	 * Webhook fields.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return void
	 */
	private static function advanced( array $s ): void {
		$events   = is_array( $s['webhook_events'] ) ? $s['webhook_events'] : array();
		$selected = array_map( 'strval', $events );

		Partials::card_open( __( 'Send your data to another app', 'estat-os' ), __( 'Useful for tools like Zapier, Make or your own CRM. If you do not know what this is, you can safely leave it off.', 'estat-os' ) );
		Partials::field( array( 'name' => 'settings[webhook_enabled]', 'type' => 'checkbox', 'label' => __( 'Connection', 'estat-os' ), 'checkbox_label' => __( 'Turn this on', 'estat-os' ), 'value' => (bool) $s['webhook_enabled'] ) );
		Partials::field( array( 'name' => 'settings[webhook_url]', 'type' => 'url', 'label' => __( 'Web address to send to', 'estat-os' ), 'value' => $s['webhook_url'], 'example' => 'https://hooks.example.com/estat' ) );
		Partials::field( array( 'name' => 'settings[webhook_secret]', 'label' => __( 'Shared secret', 'estat-os' ), 'value' => $s['webhook_secret'], 'help' => __( 'The other app uses this to check the message really came from you. Any long random text works.', 'estat-os' ) ) );

		echo '<div class="estat-field-row"><span class="estat-field-label">' . esc_html__( 'What to send', 'estat-os' ) . '</span>';
		foreach ( Webhooks::events() as $event => $label ) {
			printf(
				'<label class="estat-inline-check"><input type="checkbox" name="settings[webhook_events][]" value="%1$s" %2$s /> %3$s</label>',
				esc_attr( (string) $event ),
				checked( in_array( (string) $event, $selected, true ), true, false ),
				esc_html( (string) $label )
			);
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Every message is signed so the receiving app can confirm it is genuine.', 'estat-os' ) . '</p>';
		Partials::card_close();

		Partials::card_open( __( 'Access for other software', 'estat-os' ) );
		echo '<p>' . esc_html__( 'Developers can read and write your office data through this address, using a normal WordPress application password:', 'estat-os' ) . '</p>';
		echo '<code class="estat-copyable">' . esc_html( rest_url( 'estat/v1/' ) ) . '</code>';
		Partials::card_close();

		Partials::card_open(
			__( 'Let another form tool send you enquiries', 'estat-os' ),
			__( 'Only needed if you already build forms with Elementor Pro or another plugin and want those to land in your Enquiries inbox.', 'estat-os' )
		);

		Partials::field(
			array(
				'name'  => 'inbound_secret',
				'label' => __( 'Secret key', 'estat-os' ),
				'value' => (string) $s['inbound_secret'],
				'help'  => __( 'Make up a long phrase and paste it into the other tool. Leave this empty to keep incoming enquiries switched off.', 'estat-os' ),
			)
		);

		echo '<p>' . esc_html__( 'In Elementor Pro: open your form, go to Actions After Submit, add "Webhook", and paste this address:', 'estat-os' ) . '</p>';
		echo '<code class="estat-copyable">' . esc_html( rest_url( 'estat/v1/enquiries' ) ) . '</code>';
		echo '<p class="description">' . esc_html__( 'Then add a hidden field called "secret" holding the key above. Name your other boxes name, phone, email and message so we know what they are.', 'estat-os' ) . '</p>';

		Partials::card_close();
	}

	/**
	 * Maintenance tools and the danger zone.
	 *
	 * @return void
	 */
	private static function tools(): void {
		$s      = Settings::all();
		$status = SearchIndex::status();
		$url    = admin_url( 'admin.php?page=estat-settings&tab=tools' );

		Partials::card_open( __( 'Keep things running smoothly', 'estat-os' ) );
		echo '<div class="estat-stats">';
		Partials::stat( __( 'Properties', 'estat-os' ), (string) (int) $status['listings'], '', 'plain' );
		Partials::stat( __( 'Ready for searching', 'estat-os' ), (string) (int) $status['indexed'], '', $status['in_sync'] ? 'good' : 'warn' );
		echo '</div>';
		echo '<p>' . esc_html__( 'If search results look wrong or out of date, rebuilding the search list usually fixes it. It is completely safe and changes nothing about your properties.', 'estat-os' ) . '</p>';
		echo '<p class="estat-quick-actions">';
		self::tool_button( 'rebuild_index', __( 'Rebuild the search list', 'estat-os' ), $url );
		self::tool_button( 'repair_tables', __( 'Repair the office database', 'estat-os' ), $url );
		self::tool_button( 'clear_cache', __( 'Clear temporary data', 'estat-os' ), $url );
		self::tool_button( 'run_housekeeping', __( 'Run the daily jobs now', 'estat-os' ), $url );
		echo '</p>';
		Partials::card_close();

		Partials::card_open( __( 'Practice mode', 'estat-os' ), __( 'Fill the office with realistic sample properties so you can learn safely. Everything created this way is clearly marked and can be removed in one click.', 'estat-os' ) );
		echo '<p>';
		echo $s['practice_mode'] ? esc_html__( 'Practice mode is on.', 'estat-os' ) : esc_html__( 'Practice mode is off.', 'estat-os' );
		echo '</p>';
		echo '<p class="estat-quick-actions">';
		self::tool_button( 'seed_practice', __( 'Add sample data', 'estat-os' ), $url );
		self::tool_button( 'clear_practice', __( 'Remove all sample data', 'estat-os' ), $url );
		echo '</p>';
		Partials::card_close();

		Partials::card_open( __( 'If you ever remove this plugin', 'estat-os' ) );
		?>
		<form method="post" class="estat-form-admin" action="<?php echo esc_url( $url ); ?>">
			<?php wp_nonce_field( 'estat_save_settings', 'estat_nonce' ); ?>
			<input type="hidden" name="estat_action" value="save_settings" />
			<input type="hidden" name="tab" value="tools" />
			<p><?php esc_html_e( 'By default your properties, enquiries and visits stay exactly where they are, even if the plugin is deleted. Nothing is ever thrown away without you asking.', 'estat-os' ); ?></p>
			<?php
			Partials::field(
				array(
					'name'           => 'settings[delete_data_on_uninstall]',
					'type'           => 'checkbox',
					'label'          => __( 'Deleting the plugin', 'estat-os' ),
					'checkbox_label' => __( 'Also erase all office data when the plugin is deleted', 'estat-os' ),
					'value'          => (bool) $s['delete_data_on_uninstall'],
					'help'           => __( 'This cannot be undone. Only tick this if you have exported anything you want to keep.', 'estat-os' ),
				)
			);
			?>
			<button type="submit" class="button"><?php esc_html_e( 'Save this choice', 'estat-os' ); ?></button>
		</form>
		<?php
		Partials::card_close();

		Partials::diagnostics();
	}

	/**
	 * A single nonce-protected maintenance button.
	 *
	 * @param string $action Action key.
	 * @param string $label  Button label.
	 * @param string $url    Return URL.
	 * @return void
	 */
	private static function tool_button( string $action, string $label, string $url ): void {
		?>
		<form method="post" class="estat-inline-form" action="<?php echo esc_url( $url ); ?>">
			<?php wp_nonce_field( 'estat_tool_' . $action, 'estat_nonce' ); ?>
			<input type="hidden" name="estat_action" value="<?php echo esc_attr( $action ); ?>" />
			<button type="submit" class="button"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}
}
