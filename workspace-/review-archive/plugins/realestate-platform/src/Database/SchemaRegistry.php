<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Database;

final class SchemaRegistry {
	public const VERSION = '1';
	public static function migrationsTable( string $prefix ): string {
		return $prefix . 'rep_schema_migrations';}
}
