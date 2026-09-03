<?php

declare(strict_types=1);

use Mayfair\RealEstatePlatform\Content\ContentRegistrar;
use Mayfair\RealEstatePlatform\Fields\FieldDefinition;
use Mayfair\RealEstatePlatform\Fields\FieldRegistry;
use PHPUnit\Framework\TestCase;

final class FieldDefinitionTest extends TestCase {
	public function testRegistryIsCanonicalAndEntityScoped(): void {
		$registry = new FieldRegistry();
		self::assertArrayHasKey( 'price', $registry->forEntity( 'property' ) );
		self::assertArrayHasKey( 'subtitle', $registry->forEntity( 'insight' ) );
		self::assertArrayNotHasKey( 'subtitle', $registry->forEntity( 'property' ) );
	}

	public function testSanitizationAndCoordinates(): void {
		$field = new FieldDefinition( 'latitude', 'Latitude', 'latitude', array( 'property' ) );
		self::assertSame( 28.6139, $field->sanitize( '28.6139' ) );
		self::assertTrue( $field->validate( 28.6139 ) );
		self::assertFalse( $field->validate( 128.0 ) );
	}

	public function testInvalidMetaIsRejectedAfterSanitization(): void {
		$field = new FieldDefinition( 'latitude', 'Latitude', 'latitude', array( 'property' ) );
		self::assertNull( ContentRegistrar::sanitizeMeta( $field, '128' ) );
		self::assertFalse( ContentRegistrar::validateMeta( $field, '128' ) );
		$url = new FieldDefinition( 'video', 'Video', 'url', array( 'property' ) );
		self::assertFalse( ContentRegistrar::validateMeta( $url, 'javascript:alert(1)' ) );
	}

	public function testMissingOptionalValueRemainsNull(): void {
		$field = new FieldDefinition( 'price', 'Price', 'number', array( 'property' ) );
		self::assertNull( $field->sanitize( '' ) );
	}

	public function testAttachmentsAreValidated(): void {
		$field = new FieldDefinition( 'brochure', 'Brochure', 'attachment', array( 'property' ) );
		self::assertTrue( $field->validate( 10 ) );
		self::assertFalse( $field->validate( 11 ) );
	}
}
