<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Database;

use Mayfair\RealEstatePlatform\Contracts\DatabaseInterface;

final class WpDatabase implements DatabaseInterface {
	private \wpdb $db;
	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;
		$this->db = $db ?? $wpdb;
	} public function query( string $sql ): int|false {
		$result = $this->db->query( $sql );
		return false === $result ? false : (int) $result;
	} public function prepare( string $query, mixed ...$args ): string {
		return (string) $this->db->prepare( $query, ...$args );
	} public function prefix(): string {
		return $this->db->prefix;
	} public function lastError(): string {
		return $this->db->last_error;}
}
