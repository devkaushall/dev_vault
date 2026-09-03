<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Migration;

use Mayfair\RealEstatePlatform\Contracts\{DatabaseInterface, LoggerInterface, MigrationInterface};
use RuntimeException;
final class MigrationRunner {
	public function __construct( private DatabaseInterface $db, private LoggerInterface $logger, private string $directory ) {} /** @return array<string, MigrationInterface> */
	public function discover(): array {
		$items = array();
		$files = glob( rtrim( $this->directory, '/' ) . '/*.php' );
		if ( false === $files ) {
			$files = array();
		}
		foreach ( $files as $file ) {
			$migration = require $file;
			if ( ! $migration instanceof MigrationInterface ) {
				throw new RuntimeException( 'Invalid migration: ' . $file );
			}if ( isset( $items[ $migration->id() ] ) ) {
				throw new RuntimeException( 'Duplicate migration: ' . $migration->id() );
			}$items[ $migration->id() ] = $migration;
		}ksort( $items, SORT_NATURAL );
		return $items;
	} public function run(): void {
		$done = (array) get_option( 'realestate_platform_applied_migrations', array() );
		foreach ( $this->discover() as $id => $migration ) {
			if ( in_array( $id, $done, true ) ) {
				continue;
			}try {
				$migration->up( $this->db );
				$done[] = $id;
				update_option( 'realestate_platform_applied_migrations', array_values( array_unique( $done ) ), false );
				update_option( 'realestate_platform_db_version', $id, false );
				$this->logger->log( 'info', 'Migration applied.', array( 'migration' => $id ), 'migration' );
			} catch ( \Throwable $e ) {
				$this->logger->log(
					'critical',
					'Migration failed.',
					array(
						'migration' => $id,
						'exception' => get_class( $e ),
					),
					'migration'
				);
				throw new RuntimeException( 'Migration failed: ' . $id, 0, $e );}
		} }
}
