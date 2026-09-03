<?php
/** @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Search;
final class SearchEngine {
	public function __construct( private SearchProvider $provider ) {}
	public function execute( SearchCriteria $criteria ): SearchPage {
		return $this->provider->search( $criteria ); }
}
