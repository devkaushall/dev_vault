<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Contracts;

use Mayfair\RealEstatePlatform\Diagnostics\DiagnosticResult;
interface DiagnosticCheckInterface {
	public function name(): string;
	public function run(): DiagnosticResult;
}
