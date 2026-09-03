<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Core;

use Mayfair\RealEstatePlatform\Admin\AdminModule;
use Mayfair\RealEstatePlatform\Capabilities\CapabilityManager;
use Mayfair\RealEstatePlatform\Capabilities\ContentCapabilityManager;
use Mayfair\RealEstatePlatform\Classification\TaxonomyRegistry;
use Mayfair\RealEstatePlatform\Compatibility\CompatibilityDetector;
use Mayfair\RealEstatePlatform\Content\ContentModule;
use Mayfair\RealEstatePlatform\Content\ContentRegistrar;
use Mayfair\RealEstatePlatform\Fields\FieldRegistry;
use Mayfair\RealEstatePlatform\Diagnostics\DiagnosticsRunner;
use Mayfair\RealEstatePlatform\Logging\OptionLogger;
use Mayfair\RealEstatePlatform\Privacy\PrivacyFoundation;
use Mayfair\RealEstatePlatform\REST\StatusController;
use Mayfair\RealEstatePlatform\Settings\SettingsManager;
use Mayfair\RealEstatePlatform\Search\SearchIndexConsistency;
use Mayfair\RealEstatePlatform\Search\SearchIndexRebuilder;
use Mayfair\RealEstatePlatform\Search\SearchIndexSynchronizer;
use Mayfair\RealEstatePlatform\Search\SearchIndexWriter;
use Mayfair\RealEstatePlatform\Search\DatabaseSearchProvider;
use Mayfair\RealEstatePlatform\Search\SearchAjaxController;
use Mayfair\RealEstatePlatform\Search\SearchEngine;
use Mayfair\RealEstatePlatform\Search\SearchRequest;
use Mayfair\RealEstatePlatform\REST\PropertySearchController;
use Mayfair\RealEstatePlatform\REST\PropertyMapController;
use Mayfair\RealEstatePlatform\Geo\CoordinatePrivacy;
use Mayfair\RealEstatePlatform\Geo\MapSearchRequest;
use Mayfair\RealEstatePlatform\Geo\MarkerFactory;
use Mayfair\RealEstatePlatform\Diagnostics\SearchIndexCheck;
use Mayfair\RealEstatePlatform\Diagnostics\GeoCheck;
use Mayfair\RealEstatePlatform\Diagnostics\UserFeaturesCheck;
use Mayfair\RealEstatePlatform\Diagnostics\LeadWorkflowCheck;
use Mayfair\RealEstatePlatform\Elementor\ElementorIntegration;
use Mayfair\RealEstatePlatform\Leads\LeadNotificationScheduler;
use Mayfair\RealEstatePlatform\Leads\LeadNotificationService;
use Mayfair\RealEstatePlatform\Leads\LeadService;
use Mayfair\RealEstatePlatform\Leads\WordPressLeadNotificationProvider;
use Mayfair\RealEstatePlatform\Forms\SubmissionValidator;
use Mayfair\RealEstatePlatform\Requests\RequestService;
use Mayfair\RealEstatePlatform\REST\Phase7WorkflowController;
use Mayfair\RealEstatePlatform\Security\PublicSubmissionRateLimiter;
use Mayfair\RealEstatePlatform\SiteVisits\SiteVisitService;
use Mayfair\RealEstatePlatform\UserFeatures\FavoritesService;
use Mayfair\RealEstatePlatform\UserFeatures\SavedSearchService;
use Mayfair\RealEstatePlatform\UserFeatures\CompareService;
use Mayfair\RealEstatePlatform\UserFeatures\AlertService;
use Mayfair\RealEstatePlatform\UserFeatures\AlertEvaluator;
use Mayfair\RealEstatePlatform\UserFeatures\AlertScheduler;
use Mayfair\RealEstatePlatform\UserFeatures\MutationRateLimiter;
use Mayfair\RealEstatePlatform\UserFeatures\WordPressMailNotificationProvider;
use Mayfair\RealEstatePlatform\UserFeatures\UserFeaturesAjaxController;
use Mayfair\RealEstatePlatform\REST\UserFeaturesController;
use Mayfair\RealEstatePlatform\REST\ProfilesController;
use Mayfair\RealEstatePlatform\Profiles\ProfileService;
use Mayfair\RealEstatePlatform\ImportExport\ExportService;
use Mayfair\RealEstatePlatform\ImportExport\ImportService;
use Mayfair\RealEstatePlatform\ImportExport\RemoteMediaImporter;
use Mayfair\RealEstatePlatform\ImportExport\SchemaCatalog;
use Mayfair\RealEstatePlatform\ImportExport\SourceParser;
use Mayfair\RealEstatePlatform\Media\MediaService;
final class Bootstrap {
	private static ?self $instance = null;
	private bool $registered       = false;
	private ServiceRegistry $services;
	private function __construct() {
		$this->services = new ServiceRegistry();
	} public static function instance(): self {
		return self::$instance ??= new self();
	} public function register(): void {
		if ( $this->registered ) {
			return;
		} $this->registered = true;
		$this->services->set( 'logger', new OptionLogger() );
		$this->services->set( 'settings', new SettingsManager() );
		$capabilities = new CapabilityManager();
		$capabilities->register();
		$this->services->set( 'capabilities', $capabilities );
		( new ContentCapabilityManager() )->register();
		$this->services->set( 'compatibility', new CompatibilityDetector() );
		$fields = new FieldRegistry();
		$this->services->set( 'fields', $fields );
		$writer = new SearchIndexWriter( $fields );
		$this->services->set( 'search_index_writer', $writer );
		$this->services->set( 'search_index_rebuilder', new SearchIndexRebuilder( $writer ) );
		$this->services->set( 'search_index_consistency', new SearchIndexConsistency() );
		$search_engine  = new SearchEngine( new DatabaseSearchProvider() );
		$search_request = new SearchRequest( $search_engine );
		$this->services->set( 'search_engine', $search_engine );
		$this->services->set( 'search_request', $search_request );
		$this->services->set( 'map_search_request', new MapSearchRequest( $search_request, new MarkerFactory( new CoordinatePrivacy() ), new SettingsManager() ) );
		$profiles = new ProfileService();
		$this->services->set( 'profiles', $profiles );
		$taxonomies = new TaxonomyRegistry();
		$schema     = new SchemaCatalog( $fields, $taxonomies );
		$imports    = new ImportService( new SourceParser(), $schema, $profiles, new MediaService(), new RemoteMediaImporter() );
		$exports    = new ExportService( $schema );
		$this->services->set( 'imports', $imports );
		$this->services->set( 'exports', $exports );
		add_action( 'before_delete_post', array( $profiles, 'cleanupProfile' ) );
		$favorites = new FavoritesService();
		$saved     = new SavedSearchService();
		$this->services->set( 'favorites', $favorites );
		$this->services->set( 'saved_searches', $saved );
		$compare   = new CompareService();
		$alerts    = new AlertService();
		$evaluator = new AlertEvaluator( $search_engine, new WordPressMailNotificationProvider() );
		$scheduler = new AlertScheduler( $evaluator );
		$this->services->set( 'compare', $compare );
		$this->services->set( 'alerts', $alerts );
		$this->services->set( 'alert_evaluator', $evaluator );
		$this->services->set( 'alert_scheduler', $scheduler );
		$scheduler->register();
		$lead_notifications   = new LeadNotificationService( new WordPressLeadNotificationProvider() );
		$lead_service         = new LeadService( $lead_notifications );
		$submission_validator = new SubmissionValidator();
		$request_service      = new RequestService( $lead_service, $submission_validator );
		$site_visits          = new SiteVisitService( $lead_service, $lead_notifications, $submission_validator );
		$this->services->set( 'lead_notifications', $lead_notifications );
		$this->services->set( 'leads', $lead_service );
		$this->services->set( 'requests', $request_service );
		$this->services->set( 'site_visits', $site_visits );
		$elementor = new ElementorIntegration( $fields, $profiles, $search_request, $request_service, new PublicSubmissionRateLimiter() );
		$this->services->set( 'elementor', $elementor );
		$elementor->register();
		( new LeadNotificationScheduler( $lead_notifications ) )->register();
		add_action( 'deleted_user', array( $lead_service, 'cleanupUser' ) );
		add_action( 'deleted_user', array( $site_visits, 'cleanupUser' ) );
		add_action( 'before_delete_post', array( $lead_service, 'cleanupProfile' ) );
		add_action( 'before_delete_post', array( $site_visits, 'cleanupProfile' ) );
		add_action( 'before_delete_post', array( $lead_service, 'cleanupProperty' ) );
		add_action( 'before_delete_post', array( $site_visits, 'cleanupProperty' ) );
		( new UserFeaturesAjaxController( $favorites, $saved, $alerts, new MutationRateLimiter() ) )->register();
		add_action( 'deleted_user', array( $favorites, 'cleanupUser' ) );
		add_action( 'deleted_user', array( $saved, 'cleanupUser' ) );
		add_action( 'before_delete_post', array( $favorites, 'cleanupProperty' ) );
		( new SearchIndexSynchronizer( $writer ) )->register();
		( new SearchAjaxController( $search_request ) )->register();
		$this->services->set( 'content', new ContentModule( new ContentRegistrar( $fields ), $taxonomies, new SettingsManager() ) );
		$content = $this->services->get( 'content' );
		assert( $content instanceof ContentModule );
		$content->register();
		$this->services->set(
			'diagnostics',
			function ( ServiceRegistry $c ): DiagnosticsRunner {
				$detector = $c->get( 'compatibility' );
				assert( $detector instanceof CompatibilityDetector );
				$consistency = $c->get( 'search_index_consistency' );
				assert( $consistency instanceof SearchIndexConsistency );
				$runner = DiagnosticsRunner::withFoundationChecks( $detector );
				$runner->register( new SearchIndexCheck( $consistency ) );
				$runner->register( new GeoCheck( new SettingsManager() ) );
				$runner->register( new UserFeaturesCheck() );
				$runner->register( new LeadWorkflowCheck() );
				return $runner;
			}
		);
		add_action( 'plugins_loaded', array( $this, 'loadTextDomain' ) );
		add_action( 'init', array( $this, 'initialize' ) );
		add_action(
			'rest_api_init',
			function (): void {
				$diagnostics = $this->services->get( 'diagnostics' );
				$detector    = $this->services->get( 'compatibility' );
				assert( $diagnostics instanceof DiagnosticsRunner );
				assert( $detector instanceof CompatibilityDetector );
				( new StatusController( $diagnostics, $detector ) )->registerRoutes();
				$search = $this->services->get( 'search_request' );
				assert( $search instanceof SearchRequest );
				$property_search = new PropertySearchController( $search );
				$property_search->registerRoutes();
				$map = $this->services->get( 'map_search_request' );
				assert( $map instanceof MapSearchRequest );
				( new PropertyMapController( $map, $property_search->getCollectionParams() ) )->registerRoutes();
				$favorites = $this->services->get( 'favorites' );
				$compare   = $this->services->get( 'compare' );
				$saved     = $this->services->get( 'saved_searches' );
				$alerts    = $this->services->get( 'alerts' );
				assert( $favorites instanceof FavoritesService && $compare instanceof CompareService && $saved instanceof SavedSearchService && $alerts instanceof AlertService );
				( new UserFeaturesController( $favorites, $compare, $saved, $alerts, new MutationRateLimiter() ) )->registerRoutes();
				$profiles = $this->services->get( 'profiles' );
				assert( $profiles instanceof ProfileService );
				( new ProfilesController( $profiles ) )->registerRoutes();
				$leads    = $this->services->get( 'leads' );
				$requests = $this->services->get( 'requests' );
				$visits   = $this->services->get( 'site_visits' );
				assert( $leads instanceof LeadService && $requests instanceof RequestService && $visits instanceof SiteVisitService );
				( new Phase7WorkflowController( $leads, $requests, $visits, new PublicSubmissionRateLimiter() ) )->registerRoutes();
			}
		);
		( new PrivacyFoundation( $lead_service, $site_visits ) )->register();
		if ( is_admin() ) {
			( new AdminModule( $this->services ) )->register();
		} if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\Mayfair\RealEstatePlatform\CLI\Commands::register( $this->services );
		} } public function initialize(): void {
		$errors = Environment::errors();
		if ( $errors && is_admin() ) {
			add_action(
				'admin_notices',
				static function () use ( $errors ) {
					echo '<div class="notice notice-error"><p>' . esc_html( implode( ' ', $errors ) ) . '</p></div>';
				}
			);
		} } public function loadTextDomain(): void {
			load_plugin_textdomain( 'realestate-platform', false, dirname( plugin_basename( REALESTATE_PLATFORM_FILE ) ) . '/languages' );
		} public function services(): ServiceRegistry {
			return $this->services;}
}
