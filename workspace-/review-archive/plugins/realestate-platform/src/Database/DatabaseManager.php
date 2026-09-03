<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Database;

use Mayfair\RealEstatePlatform\Contracts\DatabaseInterface;

final class DatabaseManager {
	public function __construct( private DatabaseInterface $database ) {} public function connected(): bool {
		return $this->database->query( 'SELECT 1' ) !== false;
	} public function version(): string {
		return (string) get_option( 'realestate_platform_db_version', '0' );}
}
