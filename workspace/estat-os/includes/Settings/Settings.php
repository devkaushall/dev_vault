<?php
/**
 * Office settings: schema, defaults, sanitising and access.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Settings;

use EstatOS\Support\Sanitize;
use EstatOS\Support\Vocabulary;

defined( 'ABSPATH' ) || exit;

/**
 * All office configuration lives in one option row, sanitised through one
 * function so that corrupt or partial data can never break the plugin.
 */
final class Settings {

	/**
	 * Option key. Part of the public data contract.
	 */
	public const OPTION = 'estat_settings';

	/**
	 * Runtime cache.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Office identity.
			'office_name'        => '',
			'tagline'            => '',
			'contact_person'     => '',
			'phone'              => '',
			'whatsapp'           => '',
			'email'              => '',
			'address'            => '',
			'office_hours'       => '',
			'logo_id'            => 0,

			// Locale and money.
			'country'            => 'IN',
			'currency'           => 'INR',
			'currency_symbol'    => '₹',
			'price_format'       => 'indian',
			'default_area_unit'  => 'sqft',
			'cities'             => array(),

			// Maps.
			'map_provider'       => 'osm',
			'google_maps_key'    => '',
			'map_default_lat'    => '',
			'map_default_lng'    => '',
			'map_default_zoom'   => 11,

			// Regulatory.
			'regulatory_label'   => 'RERA',
			'show_regulatory'    => true,

			// Language.
			'language'           => 'auto',
			'frontend_language'  => 'auto',
			'allow_visitor_lang' => true,

			// Operations.
			'verification_mode'  => 'optional',
			'listing_expiry_days'=> 0,
			'retention_days'     => 0,
			'practice_mode'      => false,
			'admin_theme'        => 'light',
			'inbound_secret'  => '',
			'multi_agency'       => false,

			// Website pages the plugin built for this office, as key => post ID.
			'pages'              => array(),
			'setup_done'         => false,

			// Website features.
			'enable_favorites'   => true,
			'enable_compare'     => true,
			'enable_schema'      => true,
			'listings_per_page'  => 12,

			// Integrations.
			'webhook_enabled'    => false,
			'webhook_url'        => '',
			'webhook_secret'     => '',
			'webhook_events'     => array( 'lead.created' ),

			// Notifications.
			'notify_email'       => '',
			'notify_new_lead'    => true,
			'notify_new_visit'   => true,
			'notify_change_request' => true,
			'notify_followup'    => true,
			'notify_expiry'      => true,
			'notify_import'      => true,
			'notify_webhook_fail'=> true,

			// Danger zone.
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * Get all settings merged over defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, array() );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			self::$cache = self::sanitize( array_merge( self::defaults(), $stored ) );
		}
		return self::$cache;
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when unknown.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist a partial update (merged over current values).
	 *
	 * @param array<string,mixed> $values Values to update.
	 * @return array<string,mixed> Saved settings.
	 */
	public static function update( array $values ): array {
		$merged = self::sanitize( array_merge( self::all(), $values ) );
		update_option( self::OPTION, $merged, false );
		self::$cache = $merged;
		return $merged;
	}

	/**
	 * Flush the runtime cache.
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * Sanitize a full settings array. Unknown keys are dropped.
	 *
	 * @param array<string,mixed> $input Raw values.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ): array {
		$d   = self::defaults();
		$out = $d;

		$text_keys = array( 'office_name', 'tagline', 'contact_person', 'office_hours', 'currency', 'currency_symbol', 'regulatory_label', 'country' );
		foreach ( $text_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = Sanitize::text( $input[ $key ] );
			}
		}

		$out['phone']    = isset( $input['phone'] ) ? Sanitize::phone( $input['phone'] ) : $d['phone'];
		$out['whatsapp'] = isset( $input['whatsapp'] ) ? Sanitize::phone( $input['whatsapp'] ) : $d['whatsapp'];
		$out['email']    = isset( $input['email'] ) ? Sanitize::email( $input['email'] ) : $d['email'];
		$out['address']  = isset( $input['address'] ) ? Sanitize::textarea( $input['address'] ) : $d['address'];
		$out['logo_id']  = isset( $input['logo_id'] ) ? Sanitize::int( $input['logo_id'] ) : 0;

		$out['price_format']      = Sanitize::choice( $input['price_format'] ?? '', array( 'indian', 'international' ), $d['price_format'] );
		$out['default_area_unit'] = Sanitize::choice( $input['default_area_unit'] ?? '', Vocabulary::keys( 'area_units' ), $d['default_area_unit'] );
		$out['map_provider']      = Sanitize::choice( $input['map_provider'] ?? '', array( 'osm', 'google', 'none' ), $d['map_provider'] );
		$out['verification_mode'] = Sanitize::choice( $input['verification_mode'] ?? '', array( 'optional', 'required' ), $d['verification_mode'] );
		$out['language']          = Sanitize::choice( $input['language'] ?? '', array( 'auto', 'en_US', 'en_IN_hinglish' ), $d['language'] );
		$out['frontend_language'] = Sanitize::choice( $input['frontend_language'] ?? '', array( 'auto', 'en_US', 'en_IN_hinglish' ), $d['frontend_language'] );

		if ( isset( $input['cities'] ) ) {
			$cities = is_string( $input['cities'] ) ? preg_split( '/[\r\n,]+/', $input['cities'] ) : $input['cities'];
			$cities = is_array( $cities ) ? $cities : array();
			$cities = array_values( array_filter( array_map( array( Sanitize::class, 'text' ), $cities ) ) );
			$out['cities'] = array_slice( $cities, 0, 200 );
		}

		$out['google_maps_key']  = isset( $input['google_maps_key'] ) ? Sanitize::text( $input['google_maps_key'] ) : $d['google_maps_key'];
		$out['map_default_lat']  = isset( $input['map_default_lat'] ) ? Sanitize::latitude( $input['map_default_lat'] ) : '';
		$out['map_default_lng']  = isset( $input['map_default_lng'] ) ? Sanitize::longitude( $input['map_default_lng'] ) : '';
		$out['map_default_zoom'] = Sanitize::int( $input['map_default_zoom'] ?? $d['map_default_zoom'], 1, 20 );

		$out['admin_theme'] = Sanitize::choice(
			$input['admin_theme'] ?? $d['admin_theme'],
			array( 'light', 'dark', 'auto' ),
			'light'
		);

		// The shared key that lets an outside form tool post an enquiry in.
		$out['inbound_secret'] = Sanitize::text( $input['inbound_secret'] ?? $d['inbound_secret'] );

		$out['listing_expiry_days'] = Sanitize::int( $input['listing_expiry_days'] ?? 0, 0, 3650 );
		$out['retention_days']      = Sanitize::int( $input['retention_days'] ?? 0, 0, 3650 );
		$out['listings_per_page']   = Sanitize::int( $input['listings_per_page'] ?? $d['listings_per_page'], 1, 60 );

		$bools = array(
			'show_regulatory', 'allow_visitor_lang', 'practice_mode', 'multi_agency',
			'enable_favorites', 'enable_compare', 'enable_schema', 'webhook_enabled',
			'notify_new_lead', 'notify_new_visit', 'notify_change_request', 'notify_followup', 'notify_expiry',
			'notify_import', 'notify_webhook_fail', 'delete_data_on_uninstall',
		);
		foreach ( $bools as $key ) {
			$out[ $key ] = array_key_exists( $key, $input ) ? Sanitize::bool( $input[ $key ] ) : (bool) $d[ $key ];
		}

		// The office's website pages: a small map of known key => post ID.
		$pages     = isset( $input['pages'] ) && is_array( $input['pages'] ) ? $input['pages'] : array();
		$out['pages'] = array();
		foreach ( $pages as $page_key => $page_id ) {
			$page_key = sanitize_key( (string) $page_key );
			$page_id  = Sanitize::int( $page_id );
			if ( '' !== $page_key && $page_id > 0 ) {
				$out['pages'][ $page_key ] = $page_id;
			}
		}
		$out['setup_done'] = array_key_exists( 'setup_done', $input ) ? Sanitize::bool( $input['setup_done'] ) : (bool) $d['setup_done'];

		$out['webhook_url']    = isset( $input['webhook_url'] ) ? Sanitize::url( $input['webhook_url'] ) : '';
		$out['webhook_secret'] = isset( $input['webhook_secret'] ) ? Sanitize::text( $input['webhook_secret'] ) : '';
		if ( 0 === strpos( $out['webhook_url'], 'http://' ) ) {
			// Refuse plaintext transport for lead data.
			$out['webhook_url'] = '';
		}

		$allowed_events        = array( 'lead.created', 'lead.updated', 'visit.scheduled', 'visit.completed', 'listing.published' );
		$events                = isset( $input['webhook_events'] ) && is_array( $input['webhook_events'] ) ? $input['webhook_events'] : $d['webhook_events'];
		$out['webhook_events'] = array_values( array_intersect( array_map( array( Sanitize::class, 'text' ), $events ), $allowed_events ) );

		$out['notify_email'] = isset( $input['notify_email'] ) ? Sanitize::email( $input['notify_email'] ) : '';

		/**
		 * Filter sanitised settings, e.g. to add settings from an add-on.
		 *
		 * @param array<string,mixed> $out   Sanitised values.
		 * @param array<string,mixed> $input Raw values.
		 */
		return (array) apply_filters( 'estat_sanitize_settings', $out, $input );
	}

	/**
	 * The notification recipient, falling back to the site admin.
	 *
	 * @return string
	 */
	public static function notification_email(): string {
		$email = (string) self::get( 'notify_email', '' );
		if ( '' === $email ) {
			$email = (string) get_option( 'admin_email', '' );
		}
		return $email;
	}

	/**
	 * Office display name with a safe fallback.
	 *
	 * @return string
	 */
	public static function office_name(): string {
		$name = (string) self::get( 'office_name', '' );
		return '' !== $name ? $name : (string) get_bloginfo( 'name' );
	}
}
