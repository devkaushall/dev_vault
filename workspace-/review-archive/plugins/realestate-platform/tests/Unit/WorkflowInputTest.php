<?php
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Tests\Unit;

use Mayfair\RealEstatePlatform\Forms\Submission;
use Mayfair\RealEstatePlatform\Leads\LeadService;
use Mayfair\RealEstatePlatform\SiteVisits\SiteVisitRequest;
use Mayfair\RealEstatePlatform\SiteVisits\SiteVisitService;
use PHPUnit\Framework\TestCase;

final class WorkflowInputTest extends TestCase {
	public function testValidSubmissionIsAValidatedDto(): void {
		$submission = Submission::fromArray(
			array(
				'name'            => 'Buyer',
				'email'           => 'buyer@example.test',
				'phone'           => '+91 99999 00000',
				'message'         => 'Interested',
				'property_id'     => '12',
				'consent'         => true,
				'source'          => 'rest',
				'idempotency_key' => 'abc-1',
			)
		);
		self::assertInstanceOf( Submission::class, $submission );
		self::assertSame( 12, $submission->property_id );
		self::assertTrue( $submission->consent );
	}

	public function testInvalidSubmissionValuesAreRejectedWithoutCoercion(): void {
		$invalid_id = Submission::fromArray(
			array(
				'name'        => 'Buyer',
				'email'       => 'buyer@example.test',
				'property_id' => '1.2',
				'consent'     => true,
			)
		);
		$no_consent = Submission::fromArray(
			array(
				'name'    => 'Buyer',
				'email'   => 'buyer@example.test',
				'consent' => false,
			)
		);
		$spam       = Submission::fromArray(
			array(
				'name'        => 'Buyer',
				'email'       => 'buyer@example.test',
				'consent'     => true,
				'website_url' => 'bot',
			)
		);
		self::assertInstanceOf( \WP_Error::class, $invalid_id );
		self::assertInstanceOf( \WP_Error::class, $no_consent );
		self::assertInstanceOf( \WP_Error::class, $spam );
	}

	public function testVisitWindowIsStrictAndBounded(): void {
		$valid = SiteVisitRequest::parseWindow( '2099-01-01 10:00:00', '2099-01-01 11:00:00' );
		$long  = SiteVisitRequest::parseWindow( '2099-01-01 10:00:00', '2099-01-02 10:00:00' );
		self::assertIsArray( $valid );
		self::assertSame( '2099-01-01 10:00:00', $valid['start'] );
		self::assertInstanceOf( \WP_Error::class, $long );
	}

	public function testWorkflowStateListsAreCanonical(): void {
		self::assertSame( array( 'new', 'contacted', 'qualified', 'converted', 'lost', 'spam' ), LeadService::statuses() );
		self::assertContains( 'requested', SiteVisitService::statuses() );
		self::assertContains( 'completed', SiteVisitService::statuses() );
	}
}
