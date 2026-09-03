<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use Mayfair\RealEstatePlatform\Core\ServiceRegistry;
final class ServiceRegistryTest extends TestCase {
	public function testLazySingletonAndDuplicateProtection(): void {
		$r = new ServiceRegistry();
		$r->set( 'x', fn()=>new stdClass() );
		self::assertSame( $r->get( 'x' ), $r->get( 'x' ) );
		$this->expectException( LogicException::class );
		$r->set( 'x', new stdClass() );
	} public function testOptionalMissingIsNull(): void {
		self::assertNull( ( new ServiceRegistry() )->optional( 'none' ) );}
}
