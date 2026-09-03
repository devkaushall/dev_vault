<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Contracts;

interface DatabaseInterface {
	public function query( string $sql ): int|false;
	public function prepare( string $query, mixed ...$args ): string;
	public function prefix(): string;
	public function lastError(): string;
}
