<?php
/**
 * Search-index diagnostic adapter.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\Diagnostics;

use Mayfair\RealEstatePlatform\Contracts\DiagnosticCheckInterface;
use Mayfair\RealEstatePlatform\Search\SearchIndexConsistency;

final class SearchIndexCheck implements DiagnosticCheckInterface {
	public function __construct( private SearchIndexConsistency $consistency ) {}

	public function name(): string {
		return 'Search index';
	}

	public function run(): DiagnosticResult {
		$facts  = $this->consistency->report();
		$status = true === $facts['healthy'] ? DiagnosticResult::PASS : DiagnosticResult::FAIL;
		return new DiagnosticResult(
			$this->name(),
			$status,
			true === $facts['healthy'] ? 'Search index is consistent.' : 'Search index inconsistencies detected.',
			$facts,
			true === $facts['healthy'] ? '' : 'Run the authorized search-index rebuild command after reviewing the reported counts.'
		);
	}
}
