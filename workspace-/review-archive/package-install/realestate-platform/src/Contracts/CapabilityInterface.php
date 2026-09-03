<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Contracts;

interface CapabilityInterface {
	public function register(): void;
	public function currentUserCan( string $capability ): bool;
}
