<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use Mayfair\RealEstatePlatform\Diagnostics\DiagnosticResult;
final class DiagnosticResultTest extends TestCase {
	public function testStatuses(): void {
		foreach ( array( 'PASS', 'WARN', 'FAIL' ) as $s ) {
			self::assertSame( $s, ( new DiagnosticResult( 'x', $s, 'm' ) )->status );
		}} public function testInvalidStatusRejected(): void {
		$this->expectException( InvalidArgumentException::class );
		new DiagnosticResult( 'x', 'OK', 'm' );}
}
