<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Diagnostics;

use Mayfair\RealEstatePlatform\Contracts\DiagnosticCheckInterface;

final class CallbackCheck implements DiagnosticCheckInterface {
	public function __construct( private string $check_name, private \Closure $callback ) {}

	public function name(): string {
		return $this->check_name;
	}

	public function run(): DiagnosticResult {
		return ( $this->callback )();
	}
}
