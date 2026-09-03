<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Settings;

final class SettingDefinition {
	public function __construct( public readonly string $key, public readonly string $group, public readonly string $type, public readonly mixed $default_value, public readonly \Closure $sanitize, public readonly \Closure $validate, public readonly string $capability, public readonly string $description ) {}
}
