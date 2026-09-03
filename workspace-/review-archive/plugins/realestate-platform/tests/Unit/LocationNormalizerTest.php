<?php

declare(strict_types=1);

use Mayfair\RealEstatePlatform\Locations\LocationNormalizer;
use PHPUnit\Framework\TestCase;

final class LocationNormalizerTest extends TestCase {
	public function testNormalizesLocationWithoutInventingMissingValues(): void {
		$value = ( new LocationNormalizer() )->normalize(
			array(
				'city'      => ' Delhi ',
				'latitude'  => '28.6139',
				'longitude' => '77.2090',
			)
		);
		self::assertSame( 'Delhi', $value['city'] );
		self::assertNull( $value['locality'] );
		self::assertSame( 28.6139, $value['latitude'] );
	}

	public function testRejectsInvalidCoordinate(): void {
		$this->expectException( InvalidArgumentException::class );
		( new LocationNormalizer() )->normalize( array( 'latitude' => 91 ) );
	}
}
