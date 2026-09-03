<?php
/**
 * WP-CLI commands.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\CLI;

use Mayfair\RealEstatePlatform\Compatibility\CompatibilityDetector;
use Mayfair\RealEstatePlatform\Core\ServiceRegistry;
use Mayfair\RealEstatePlatform\Diagnostics\DiagnosticsRunner;
use Mayfair\RealEstatePlatform\Search\SearchIndexConsistency;
use Mayfair\RealEstatePlatform\Search\SearchIndexRebuilder;
use Mayfair\RealEstatePlatform\ImportExport\ExportService;
use Mayfair\RealEstatePlatform\ImportExport\ImportService;

final class Commands {
	public static function register( ServiceRegistry $services ): void {
		\WP_CLI::add_command( 'realestate status', self::foundationStatus( $services ) );
		\WP_CLI::add_command( 'realestate search-index status', self::searchStatus( $services ) );
		\WP_CLI::add_command( 'realestate search-index rebuild', self::searchRebuild( $services ) );
		\WP_CLI::add_command( 'realestate import validate', self::importCommand( $services, 'validate' ) );
		\WP_CLI::add_command( 'realestate import dry-run', self::importCommand( $services, 'dry_run' ) );
		\WP_CLI::add_command( 'realestate import execute', self::importCommand( $services, 'import' ) );
		\WP_CLI::add_command( 'realestate export', self::exportCommand( $services ) );
	}

	private static function foundationStatus( ServiceRegistry $services ): callable {
		return static function () use ( $services ): void {
			$compatibility = $services->get( 'compatibility' );
			$diagnostics   = $services->get( 'diagnostics' );
			assert( $compatibility instanceof CompatibilityDetector );
			assert( $diagnostics instanceof DiagnosticsRunner );
			self::authorize( 'view_realestate_diagnostics' );
			self::output(
				array(
					'plugin_version' => REALESTATE_PLATFORM_VERSION,
					'dependencies'   => $compatibility->snapshot(),
					'diagnostics'    => $diagnostics->summary(),
				)
			);
		};
	}

	private static function searchStatus( ServiceRegistry $services ): callable {
		return static function () use ( $services ): void {
			self::authorize( 'view_realestate_diagnostics' );
			$consistency = $services->get( 'search_index_consistency' );
			assert( $consistency instanceof SearchIndexConsistency );
			self::output( $consistency->report() );
		};
	}

	private static function searchRebuild( ServiceRegistry $services ): callable {
		return static function ( array $args, array $assoc_args ) use ( $services ): void {
			unset( $args );
			self::authorize( 'manage_realestate_migrations' );
			$batch_size = isset( $assoc_args['batch-size'] ) ? (int) $assoc_args['batch-size'] : 100;
			$rebuilder  = $services->get( 'search_index_rebuilder' );
			assert( $rebuilder instanceof SearchIndexRebuilder );
			try {
				$result = $rebuilder->rebuild( $batch_size );
			} catch ( \Throwable $exception ) {
				\WP_CLI::error( 'Search-index rebuild failed safely: ' . sanitize_text_field( $exception->getMessage() ) );
				return;
			}
			if ( $result['failed'] > 0 ) {
				self::output( $result );
				\WP_CLI::error( 'Search-index rebuild completed with failed records.' );
				return;
			}
			self::output( $result );
		};
	}

	private static function importCommand( ServiceRegistry $services, string $mode ): callable {
		return static function ( array $args, array $assoc_args ) use ( $services, $mode ): void {
			unset( $args );
			self::authorize( 'manage_realestate_imports' );
			$entity = isset( $assoc_args['entity'] ) ? (string) $assoc_args['entity'] : '';
			$file   = isset( $assoc_args['file'] ) ? (string) $assoc_args['file'] : '';
			$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : (string) pathinfo( $file, PATHINFO_EXTENSION );
			if ( '' === $entity || '' === $file || ! in_array( strtolower( $format ), array( 'csv', 'json' ), true ) ) {
				\WP_CLI::error( 'Use --entity, --file, and a csv or json --format.' );
			}
			$service = $services->get( 'imports' );
			assert( $service instanceof ImportService );
			$options = array(
				'strategy'             => $assoc_args['strategy'] ?? 'upsert',
				'create_missing_terms' => array_key_exists( 'create-missing-terms', $assoc_args ),
				'allow_remote_media'   => array_key_exists( 'allow-remote-media', $assoc_args ),
			);
			$result  = $service->runFile( $mode, $entity, $format, $file, $options, get_current_user_id() );
			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_message() );
			}
			self::output( $result );
			if ( 'FAIL' === ( $result['status'] ?? '' ) ) {
				\WP_CLI::error( 'Import completed with validation, conflict, or execution findings.' );
			}
		};
	}

	private static function exportCommand( ServiceRegistry $services ): callable {
		return static function ( array $args, array $assoc_args ) use ( $services ): void {
			unset( $args );
			self::authorize( 'manage_realestate_exports' );
			$entity = isset( $assoc_args['entity'] ) ? (string) $assoc_args['entity'] : '';
			$file   = isset( $assoc_args['file'] ) ? (string) $assoc_args['file'] : '';
			$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : (string) pathinfo( $file, PATHINFO_EXTENSION );
			if ( '' === $entity || '' === $file || ! in_array( strtolower( $format ), array( 'csv', 'json' ), true ) ) {
				\WP_CLI::error( 'Use --entity, --file, and a csv or json --format.' );
			}
			$service = $services->get( 'exports' );
			assert( $service instanceof ExportService );
			$result = $service->writeFile(
				$entity,
				$format,
				$file,
				get_current_user_id(),
				array(
					'limit'             => $assoc_args['limit'] ?? 1000,
					'overwrite'         => array_key_exists( 'force', $assoc_args ),
					'include_nonpublic' => array_key_exists( 'include-nonpublic', $assoc_args ),
				)
			);
			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_message() );
			}
			self::output( $result );
		};
	}

	private static function authorize( string $capability ): void {
		if ( ! current_user_can( $capability ) ) {
			\WP_CLI::error( 'You are not authorized to run this command.' );
		}
	}

	/** @param array<string,mixed> $value */
	private static function output( array $value ): void {
		\WP_CLI::line( (string) wp_json_encode( $value, JSON_PRETTY_PRINT ) );
	}
}
