<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Contracts;

interface SettingsInterface {
	public function get( string $key, mixed $fallback = null ): mixed;
	public function update( string $key, mixed $value ): bool;
}
