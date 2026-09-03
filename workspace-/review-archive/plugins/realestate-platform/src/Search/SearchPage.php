<?php
/** @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Search;
final class SearchPage {
	/** @param list<SearchResult> $results */
	public function __construct( public readonly array $results, public readonly int $total, public readonly int $page, public readonly int $per_page ) {}
	/** @return array<string,mixed> */
	public function toArray(): array {
		return array(
			'results'    => $this->results,
			'pagination' => array(
				'total'        => $this->total,
				'total_pages'  => (int) ceil( $this->total / $this->per_page ),
				'current_page' => $this->page,
				'per_page'     => $this->per_page,
			),
		); }
}
