<?php
/** @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Geo;
interface MapProviderInterface {
	public function id(): string;
	/** @return array<string,mixed> */
	public function publicConfiguration(): array;
	public function supportsClustering(): bool;
}
