<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Contracts;

interface LoggerInterface {
	/** @param array<string, mixed> $context */
	public function log( string $level, string $message, array $context = array(), string $category = 'system' ): void;
}
