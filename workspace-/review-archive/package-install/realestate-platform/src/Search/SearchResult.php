<?php
/** @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Search;
final class SearchResult implements \JsonSerializable {
	/** @param array<string,mixed> $data */
	public function __construct( private array $data ) {}
	/** @return array<string,mixed> */
	public function jsonSerialize(): array {
		return $this->data; }
}
