<?php

declare(strict_types=1);

use Mayfair\RealEstatePlatform\Security\StrictId;
use PHPUnit\Framework\TestCase;

final class StrictIdTest extends TestCase {
	public function testPositiveIntegersAndDecimalStringsAreAccepted(): void {
		self::assertSame( 7, StrictId::parse( 7 ) );
		self::assertSame( 7, StrictId::parse( '7' ) );
		self::assertSame( 7, StrictId::parse( '007' ) );
	}

	/** @dataProvider invalidValues */
	public function testInvalidValuesAreRejectedWithoutCoercion( mixed $value ): void {
		self::assertSame( 0, StrictId::parse( $value ) );
	}

	/** @return iterable<string,array{0:mixed}> */
	public static function invalidValues(): iterable {
		yield 'zero' => array( 0 );
		yield 'negative integer' => array( -1 );
		yield 'negative string' => array( '-1' );
		yield 'signed string' => array( '+1' );
		yield 'whitespace string' => array( ' 1' );
		yield 'decimal string' => array( '1.0' );
		yield 'float' => array( 1.0 );
		yield 'boolean' => array( true );
		yield 'array' => array( array( 1 ) );
		yield 'object' => array( new stdClass() );
		yield 'null' => array( null );
		yield 'overflow' => array( '999999999999999999999999999999' );
	}
}
