<?php
/** @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Search;
final class SearchQuery {
	public function __construct( private SearchEngine $engine ) {}
	public function run( SearchCriteria $criteria ): SearchPage {
		return $this->engine->execute( $criteria ); }
}
