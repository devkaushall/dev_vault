<?php
/**
 * Plugin bootstrap and module registry.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS;

defined( 'ABSPATH' ) || exit;

/**
 * Central container. Deliberately tiny: an explicit list of modules, each with
 * a register() method. No magic, no service container, no reflection.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Registered modules keyed by short name.
	 *
	 * @var array<string,object>
	 */
	private array $modules = array();

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Instance accessor.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 1 );
		add_action( 'plugins_loaded', array( $this, 'register_modules' ), 5 );
		add_action( 'init', array( Install\Migrator::class, 'maybe_upgrade' ), 1 );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'estat-os', false, dirname( ESTAT_BASENAME ) . '/languages' );
	}

	/**
	 * Instantiate and register every module.
	 *
	 * @return void
	 */
	public function register_modules(): void {
		$classes = array(
			'i18n'        => I18n\Language::class,
			'settings'    => Settings\Settings::class,
			'roles'       => Security\Roles::class,
			'post_types'  => Data\PostTypes::class,
			'taxonomies'  => Data\Taxonomies::class,
			'meta'        => Data\Meta::class,
			'listings'    => Data\Listings::class,
			'projects'    => Data\Projects::class,
			'agents'      => Data\Agents::class,
			'leads'       => Leads\Leads::class,
			'visits'      => Leads\Visits::class,
			'forms'       => Forms\Forms::class,
			'submissions' => Forms\Submissions::class,
			'search'      => Search\SearchIndex::class,
			'query'       => Search\Query::class,
			'audit'       => Audit\AuditLog::class,
			'requests'    => Field\Requests::class,
			'webhooks'    => Webhooks\Webhooks::class,
			'notify'      => Notifications\Notifier::class,
			'csv'         => Csv\Spreadsheet::class,
			'rest'        => Rest\RestApi::class,
			'frontend'    => Frontend\Frontend::class,
			'shortcodes'  => Frontend\Shortcodes::class,
			'seo'         => Frontend\Schema::class,
			'cron'        => Cron\Scheduler::class,
			'privacy'     => Privacy\Privacy::class,
			'practice'    => Support\PracticeMode::class,
			'elementor'   => Integrations\Elementor\Bridge::class,
		);

		if ( is_admin() ) {
			$classes['admin'] = Admin\Admin::class;
		}

		/**
		 * Filter the module class map before instantiation.
		 *
		 * @param array<string,string> $classes Module map.
		 */
		$classes = (array) apply_filters( 'estat_modules', $classes );

		foreach ( $classes as $key => $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				continue;
			}
			$module = new $class_name();
			if ( method_exists( $module, 'register' ) ) {
				$module->register();
			}
			$this->modules[ $key ] = $module;
		}

		/**
		 * Fires once all modules are registered.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'estat_loaded', $this );
	}

	/**
	 * Get a registered module.
	 *
	 * @param string $key Module key.
	 * @return object|null
	 */
	public function module( string $key ) {
		return $this->modules[ $key ] ?? null;
	}
}
