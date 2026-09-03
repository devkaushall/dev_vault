<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use Mayfair\RealEstatePlatform\Security\Security;
function wp_normalize_path( $p ) {
	return str_replace( '\\', '/', $p );
} function trailingslashit( $p ) {
	return rtrim( $p, '/' ) . '/';
} function __( $s ) {
	return $s;}
final class SecurityTest extends TestCase {
	public function testTraversalRejected(): void {
		self::assertInstanceOf( WP_Error::class, Security::safePath( '/safe', '../bad' ) );
	} public function testSafeRelativePath(): void {
		self::assertSame( '/safe/file.txt', Security::safePath( '/safe', 'file.txt' ) );
	} public function testTokenEntropyShape(): void {
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', Security::token() );}
}
