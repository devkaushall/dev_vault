<?php
/** Normalized import row DTO. @package RealEstatePlatform */
declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\ImportExport;

final class ImportRow {
	/**
	 * @param array<string,mixed> $raw
	 * @param array<string,mixed> $normalized
	 * @param list<string>        $errors
	 * @param list<string>        $warnings
	 */
	public function __construct(
		public readonly int $line,
		public readonly array $raw,
		public readonly array $normalized,
		public readonly array $errors = array(),
		public readonly array $warnings = array()
	) {}

	public function valid(): bool {
		return array() === $this->errors;
	}
}
