<?php
/** Conditional Elementor and Elementor Pro integration entry point. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Elementor;

use Mayfair\RealEstatePlatform\Fields\FieldRegistry;
use Mayfair\RealEstatePlatform\Profiles\ProfileService;
use Mayfair\RealEstatePlatform\Requests\RequestService;
use Mayfair\RealEstatePlatform\Search\SearchRequest;
use Mayfair\RealEstatePlatform\Security\PublicSubmissionRateLimiter;

final class ElementorIntegration {
	private bool $booted            = false;
	private ?PublicContext $context = null;
	private ?QueryAdapter $queries  = null;

	public function __construct( private FieldRegistry $fields, private ProfileService $profiles, private SearchRequest $search, private RequestService $requests, private PublicSubmissionRateLimiter $rate ) {}

	public function register(): void {
		if ( self::available() ) {
			$this->boot();
			return;
		}
		add_action( 'elementor/loaded', array( $this, 'boot' ), 20 );
	}

	public function boot(): void {
		if ( $this->booted || ! self::available() ) {
			return;
		}
		$this->booted  = true;
		$this->context = new PublicContext( $this->fields, $this->profiles, new \Mayfair\RealEstatePlatform\Geo\CoordinatePrivacy() );
		$this->queries = new QueryAdapter( $this->search );
		add_action( 'elementor/dynamic_tags/register', array( $this, 'registerDynamicTags' ), 10, 1 );
		$this->queries->register();
		add_action( 'elementor_pro/forms/actions/register', array( $this, 'registerProFormAction' ), 10, 1 );
	}

	public function registerDynamicTags( mixed $manager ): void {
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'register' ) || ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) || ! $this->context instanceof PublicContext ) {
			return;
		}
		if ( method_exists( $manager, 'register_group' ) ) {
			$manager->register_group( 'realestate-platform', array( 'title' => __( 'RealEstate Platform', 'realestate-platform' ) ) );
		}
		foreach ( TagCatalog::definitions() as $definition ) {
			$manager->register( new PublicFieldTag( $this->context, $definition ) );
		}
	}

	public function registerProFormAction( mixed $registrar ): void {
		if ( ! is_object( $registrar ) || ! method_exists( $registrar, 'register' ) || ! class_exists( '\ElementorPro\Modules\Forms\Classes\Action_Base' ) ) {
			return;
		}
		$registrar->register( new LeadFormAction( $this->requests, $this->rate ) );
	}

	public static function available(): bool {
		return ( function_exists( 'did_action' ) && did_action( 'elementor/loaded' ) > 0 ) || class_exists( '\Elementor\Plugin' );
	}

	public static function proAvailable(): bool {
		return defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\ElementorPro\Plugin' );
	}

	/** @return array{elementor:bool,elementor_pro:bool,acf:bool} */
	public static function availability(): array {
		return array(
			'elementor'     => self::available(),
			'elementor_pro' => self::proAvailable(),
			'acf'           => class_exists( 'ACF' ) || function_exists( 'get_field' ),
		);
	}

	public function isBooted(): bool {
		return $this->booted;
	}
}
