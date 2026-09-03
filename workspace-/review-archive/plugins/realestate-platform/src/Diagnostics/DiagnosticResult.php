<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Diagnostics;

final class DiagnosticResult implements \JsonSerializable {
	public const PASS = 'PASS', WARN = 'WARN', FAIL = 'FAIL';
	/** @param array<string, mixed> $details */
	public function __construct( public readonly string $name, public readonly string $status, public readonly string $message, public readonly array $details = array(), public readonly string $remediation = '' ) {
		if ( ! in_array( $status, array( self::PASS, self::WARN, self::FAIL ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid diagnostic status.' );
		}} /** @return array<string, mixed> */
	public function jsonSerialize(): array {
		return get_object_vars( $this );}
}
