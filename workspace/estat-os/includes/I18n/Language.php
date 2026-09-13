<?php
/**
 * Language resolution for the admin and the public website.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\I18n;

use EstatOS\Settings\Settings;
use EstatOS\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin ships real gettext translations, one .mo per language. Nothing is
 * string-replaced at runtime and no language has its own code path, so adding a
 * language means adding a translation file and one entry in available().
 *
 * Resolution order: explicit user choice, then office preference, then the
 * detected locale, then English.
 */
final class Language {

	/**
	 * Cookie used to remember a visitor's choice.
	 */
	public const COOKIE = 'estat_lang';

	/**
	 * User meta key for a signed-in person's choice.
	 */
	public const USER_META = 'estat_language';

	/**
	 * Resolved locale for this request.
	 *
	 * @var string|null
	 */
	private static ?string $resolved = null;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'plugin_locale', array( __CLASS__, 'filter_locale' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'maybe_store_choice' ), 2 );
		add_action( 'show_user_profile', array( __CLASS__, 'user_field' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'user_field' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_field' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_field' ) );
	}

	/**
	 * Languages the plugin can present.
	 *
	 * @return array<string,string> Locale => human label.
	 */
	public static function available(): array {
		$languages = array(
			'en_US'          => __( 'English', 'estat-os' ),
			'en_IN_hinglish' => 'Hinglish',
		);

		/**
		 * Filter the list of interface languages.
		 *
		 * @param array<string,string> $languages Locale => label.
		 */
		return (array) apply_filters( 'estat_available_languages', $languages );
	}

	/**
	 * Choices offered in settings, including automatic detection.
	 *
	 * @return array<string,string>
	 */
	public static function choices(): array {
		return array( 'auto' => __( 'Choose automatically', 'estat-os' ) ) + self::available();
	}

	/**
	 * Resolve the locale for the current request.
	 *
	 * @return string
	 */
	public static function resolve(): string {
		if ( null !== self::$resolved ) {
			return self::$resolved;
		}
		$available = self::available();

		// 1. Explicit personal choice.
		$personal = self::personal_choice();
		if ( '' !== $personal && isset( $available[ $personal ] ) ) {
			return self::$resolved = $personal;
		}

		// 2. Office preference (separate for admin and website).
		$office = is_admin() ? (string) Settings::get( 'language', 'auto' ) : (string) Settings::get( 'frontend_language', 'auto' );
		if ( 'auto' !== $office && isset( $available[ $office ] ) ) {
			return self::$resolved = $office;
		}

		// 3. Detected locale.
		$detected = self::detect();
		if ( '' !== $detected && isset( $available[ $detected ] ) ) {
			return self::$resolved = $detected;
		}

		// 4. Safe fallback.
		return self::$resolved = 'en_US';
	}

	/**
	 * The signed-in user's or visitor's stored choice.
	 *
	 * @return string
	 */
	private static function personal_choice(): string {
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			$stored = (string) get_user_meta( $user_id, self::USER_META, true );
			if ( '' !== $stored && 'auto' !== $stored ) {
				return $stored;
			}
		}
		if ( ! Settings::get( 'allow_visitor_lang' ) ) {
			return '';
		}
		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			$cookie = Sanitize::text( wp_unslash( $_COOKIE[ self::COOKIE ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return isset( self::available()[ $cookie ] ) ? $cookie : '';
		}
		return '';
	}

	/**
	 * Detect a suitable language from the site locale, then the browser.
	 *
	 * @return string
	 */
	private static function detect(): string {
		$site = (string) get_locale();
		if ( isset( self::available()[ $site ] ) ) {
			return $site;
		}
		// Indian English sites default to Hinglish only when it is available.
		if ( in_array( $site, array( 'en_IN', 'hi_IN' ), true ) && isset( self::available()['en_IN_hinglish'] ) ) {
			return 'en_IN_hinglish';
		}
		if ( ! is_admin() && isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			$header = Sanitize::text( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( preg_match( '/\b(hi|en-IN)\b/i', $header ) && isset( self::available()['en_IN_hinglish'] ) ) {
				return 'en_IN_hinglish';
			}
		}
		return '';
	}

	/**
	 * Apply the resolved locale to this plugin's translations only.
	 *
	 * @param string $locale Locale.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public static function filter_locale( $locale, $domain ) {
		if ( 'estat-os' !== $domain ) {
			return $locale;
		}
		return self::resolve();
	}

	/**
	 * Handle ?estat_lang=… from the language selector.
	 *
	 * @return void
	 */
	public static function maybe_store_choice(): void {
		if ( ! isset( $_GET['estat_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$requested = Sanitize::text( wp_unslash( $_GET['estat_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
		if ( ! isset( self::available()[ $requested ] ) ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, self::USER_META, $requested );
		}
		if ( ! headers_sent() ) {
			setcookie( self::COOKIE, $requested, time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		}
		self::$resolved = $requested;
	}

	/**
	 * Public language selector markup.
	 *
	 * @return string
	 */
	public static function selector(): string {
		if ( ! Settings::get( 'allow_visitor_lang' ) ) {
			return '';
		}
		$current = self::resolve();
		$out     = '<nav class="estat-lang" aria-label="' . esc_attr__( 'Choose a language', 'estat-os' ) . '">';
		$links   = array();
		foreach ( self::available() as $locale => $label ) {
			$url     = add_query_arg( 'estat_lang', rawurlencode( $locale ) );
			$current_class = $locale === $current ? ' class="is-current" aria-current="true"' : '';
			$links[] = '<a href="' . esc_url( $url ) . '" rel="nofollow"' . $current_class . '>' . esc_html( $label ) . '</a>';
		}
		return $out . implode( '<span aria-hidden="true"> | </span>', $links ) . '</nav>';
	}

	/**
	 * Language field on the user profile screen.
	 *
	 * @param \WP_User $user User.
	 * @return void
	 */
	public static function user_field( $user ): void {
		$current = (string) get_user_meta( (int) $user->ID, self::USER_META, true );
		?>
		<h2><?php esc_html_e( 'Office language', 'estat-os' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="estat_language"><?php esc_html_e( 'Language for the office screens', 'estat-os' ); ?></label></th>
				<td>
					<?php wp_nonce_field( 'estat_save_user_language', 'estat_language_nonce' ); ?>
					<select name="estat_language" id="estat_language">
						<?php foreach ( self::choices() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current === $value || ( '' === $current && 'auto' === $value ) ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'This only changes the words you see in the office screens. Your property information is never translated.', 'estat-os' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the profile language field.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function save_user_field( int $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( ! isset( $_POST['estat_language_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['estat_language_nonce'] ) ), 'estat_save_user_language' ) ) {
			return;
		}
		$value = isset( $_POST['estat_language'] ) ? Sanitize::text( wp_unslash( $_POST['estat_language'] ) ) : 'auto';
		if ( 'auto' === $value ) {
			delete_user_meta( $user_id, self::USER_META );
			return;
		}
		if ( isset( self::available()[ $value ] ) ) {
			update_user_meta( $user_id, self::USER_META, $value );
		}
	}
}
