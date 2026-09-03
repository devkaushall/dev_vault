<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Contracts;

interface MigrationInterface {
	public function id(): string;
	public function up( DatabaseInterface $database ): void;
}
