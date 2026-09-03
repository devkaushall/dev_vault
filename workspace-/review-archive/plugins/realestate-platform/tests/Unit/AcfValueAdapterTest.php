<?php

declare(strict_types=1);

use Mayfair\RealEstatePlatform\Compatibility\AcfValueAdapter;
use PHPUnit\Framework\TestCase;

final class AcfValueAdapterTest extends TestCase {
	public function testMissingAcfUsesFallbackWithoutFailure(): void {
		$adapter = new AcfValueAdapter();
		self::assertFalse( $adapter->available() );
		self::assertSame( 'fallback', $adapter->read( 'mpd_price', 1, 'fallback' ) );
	}

	public function testInvalidSourceKeyIsRejected(): void {
		self::assertNull( ( new AcfValueAdapter() )->read( '../secret', 1 ) );
	}
}
