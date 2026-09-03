<?php
/** Keyless provider-neutral OpenStreetMap configuration. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Geo;
final class OpenStreetMapProvider implements MapProviderInterface {
	public function id(): string {
		return 'openstreetmap';}
	public function publicConfiguration(): array {
		return array(
			'provider'    => $this->id(),
			'attribution' => '© OpenStreetMap contributors',
		);}
	public function supportsClustering(): bool {
		return true;}
}
