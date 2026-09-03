<?php
/** Adapter-level Elementor contract tests without a real Elementor runtime. @package RealEstatePlatform */
declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\Tests\Unit;

use Mayfair\RealEstatePlatform\Elementor\ElementorIntegration;
use Mayfair\RealEstatePlatform\Elementor\QueryAdapter;
use Mayfair\RealEstatePlatform\Elementor\TagCatalog;
use Mayfair\RealEstatePlatform\Fields\FieldRegistry;
use Mayfair\RealEstatePlatform\Forms\SubmissionValidator;
use Mayfair\RealEstatePlatform\Leads\LeadNotificationProviderInterface;
use Mayfair\RealEstatePlatform\Leads\LeadNotificationService;
use Mayfair\RealEstatePlatform\Leads\LeadService;
use Mayfair\RealEstatePlatform\Profiles\ProfileService;
use Mayfair\RealEstatePlatform\Requests\RequestService;
use Mayfair\RealEstatePlatform\Search\SearchEngine;
use Mayfair\RealEstatePlatform\Search\SearchProvider;
use Mayfair\RealEstatePlatform\Search\SearchRequest;
use Mayfair\RealEstatePlatform\Security\PublicSubmissionRateLimiter;
use PHPUnit\Framework\TestCase;

final class ElementorAdapterTest extends TestCase {
	public function test_elementor_is_optional_when_runtime_is_absent(): void {
		self::assertFalse( ElementorIntegration::available() );
		self::assertFalse( ElementorIntegration::proAvailable() );
		$availability = ElementorIntegration::availability();
		self::assertFalse( $availability['elementor'] );
		self::assertFalse( $availability['elementor_pro'] );

		$provider    = $this->createMock( LeadNotificationProviderInterface::class );
		$leads       = new LeadService( new LeadNotificationService( $provider ) );
		$requests    = new RequestService( $leads, new SubmissionValidator() );
		$search      = new SearchRequest( new SearchEngine( $this->createMock( SearchProvider::class ) ) );
		$integration = new ElementorIntegration( new FieldRegistry(), new ProfileService(), $search, $requests, new PublicSubmissionRateLimiter() );
		self::assertFalse( $integration->isBooted() );
	}

	public function test_catalog_has_stable_public_tags_only(): void {
		$definitions = TagCatalog::definitions();
		$ids         = array_column( $definitions, 'id' );
		self::assertCount( count( array_unique( $ids ) ), $ids );
		foreach ( $definitions as $definition ) {
			self::assertMatchesRegularExpression( '/^rep_[a-z][a-z0-9_]+$/', $definition['id'] );
			self::assertContains( $definition['entity'], array( 'property', 'project', 'agent', 'agency', 'insight' ) );
			self::assertContains( $definition['type'], array( 'text', 'url' ) );
			self::assertNotContains( $definition['field'], array( 'private_notes', 'lead_id', 'user_id', 'status_history', 'notification_state' ) );
		}
	}

	public function test_property_query_input_is_allowlisted_and_bounded(): void {
		$input = QueryAdapter::inputFromVars(
			array(
				'rep_keyword'       => 'Delhi',
				'rep_per_page'      => 1000,
				'rep_page'          => 2,
				'rep_orderby'       => 'price_desc',
				'rep_property_type' => array( 3, 5 ),
				'rep_private_notes' => 'should-not-pass',
				'rep_lead_id'       => 99,
			)
		);
		self::assertSame( 'Delhi', $input['keyword'] );
		self::assertSame( 100, $input['per_page'] );
		self::assertSame( 2, $input['page'] );
		self::assertSame( 'price_desc', $input['orderby'] );
		self::assertSame( array( 3, 5 ), $input['property_type'] );
		self::assertArrayNotHasKey( 'private_notes', $input );
		self::assertArrayNotHasKey( 'lead_id', $input );
	}
}
