<?php
/** Provider-neutral public marker. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Geo;
final class MapMarker implements \JsonSerializable {
	public function __construct( public readonly int $id, public readonly float $latitude, public readonly float $longitude, public readonly string $title, public readonly string $url, public readonly ?float $price, public readonly ?string $currency ) {}
	/** @return array<string,int|float|string|null> */
	public function jsonSerialize(): array {
		return array(
			'id'        => $this->id,
			'latitude'  => $this->latitude,
			'longitude' => $this->longitude,
			'title'     => $this->title,
			'url'       => $this->url,
			'price'     => $this->price,
			'currency'  => $this->currency,
		); }
}
