<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
/** Requires the WordPress test suite; skipped by the lightweight unit bootstrap. */
final class WordPressFoundationTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'do_action' ) ) {
			self::markTestSkipped( 'WordPress integration test suite not installed.' );
		}} public function testRestAndActivationFoundation(): void {
		self::assertTrue( class_exists( \Mayfair\RealEstatePlatform\Core\Bootstrap::class ) );
		self::assertTrue( class_exists( \Mayfair\RealEstatePlatform\Migration\MigrationRunner::class ) );}
}
