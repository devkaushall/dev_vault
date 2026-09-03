<?php
/** @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Search;
interface SearchProvider {
	public function search( SearchCriteria $criteria ): SearchPage;
}
