<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Compatibility;

enum OperatingMode: string {
	case Standalone    = 'standalone';
	case Compatibility = 'compatibility';
	case Migration     = 'migration';
}
