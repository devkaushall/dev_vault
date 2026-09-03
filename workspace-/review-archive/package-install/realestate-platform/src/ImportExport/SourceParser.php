<?php
/**
 * Bounded CSV and JSON source parser.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\ImportExport;

final class SourceParser {
	public const MAX_BYTES      = 16 * 1024 * 1024;
	public const MAX_ROWS       = 10000;
	public const MAX_COLUMNS    = 128;
	public const MAX_CELL_BYTES = 65535;

	/**
	 * @return array{rows:list<array{line:int,data:array<string,mixed>}>,declared_columns:list<string>,warnings:list<string>}|\WP_Error
	 */
	public function parseString( string $contents, string $format ): array|\WP_Error {
		if ( strlen( $contents ) > self::MAX_BYTES ) {
			return $this->error( 'import_source_too_large', 'The import source exceeds the bounded size limit.' );
		}
		if ( ! $this->validUtf8( $contents ) ) {
			return $this->error( 'import_invalid_utf8', 'The import source must be valid UTF-8.' );
		}
		$format = strtolower( trim( $format ) );
		if ( 'json' === $format ) {
			return $this->parseJson( $contents );
		}
		if ( 'csv' !== $format ) {
			return $this->error( 'unsupported_import_format', 'Only CSV and JSON imports are supported.' );
		}
		$handle = fopen( 'php://temp', 'w+b' );
		if ( false === $handle ) {
			return $this->error( 'import_source_unavailable', 'The import source could not be opened.' );
		}
		fwrite( $handle, $contents );
		rewind( $handle );
		$result = $this->parseCsvHandle( $handle );
		fclose( $handle );
		return $result;
	}

	/**
	 * @return array{rows:list<array{line:int,data:array<string,mixed>}>,declared_columns:list<string>,warnings:list<string>}|\WP_Error
	 */
	public function parseFile( string $path, string $format ): array|\WP_Error {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return $this->error( 'import_source_unavailable', 'The import source could not be read.' );
		}
		$size = filesize( $path );
		if ( false === $size || $size > self::MAX_BYTES ) {
			return $this->error( 'import_source_too_large', 'The import source exceeds the bounded size limit.' );
		}
		$format = strtolower( trim( $format ) );
		if ( 'json' === $format ) {
			$contents = file_get_contents( $path );
			return false === $contents ? $this->error( 'import_source_unavailable', 'The import source could not be read.' ) : $this->parseString( $contents, 'json' );
		}
		if ( 'csv' !== $format ) {
			return $this->error( 'unsupported_import_format', 'Only CSV and JSON imports are supported.' );
		}
		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			return $this->error( 'import_source_unavailable', 'The import source could not be opened.' );
		}
		$result = $this->parseCsvHandle( $handle );
		fclose( $handle );
		return $result;
	}

	/**
	 * @param resource $handle
	 * @return array{rows:list<array{line:int,data:array<string,mixed>}>,declared_columns:list<string>,warnings:list<string>}|\WP_Error
	 */
	private function parseCsvHandle( $handle ): array|\WP_Error {
		$header = fgetcsv( $handle, 0, ',', '"', '' );
		if ( false === $header || $this->blankRow( $header ) ) {
			return $this->error( 'import_empty_csv', 'The CSV source must include a header row.' );
		}
		$columns = array();
		$seen    = array();
		foreach ( $header as $index => $value ) {
			if ( ! is_string( $value ) ) {
				return $this->error( 'import_invalid_header', 'CSV headers must be strings.' );
			}
			$value = trim( $value );
			if ( 0 === $index ) {
				$value = preg_replace( '/^\xEF\xBB\xBF/', '', $value ) ?? $value;
			}
			$value = strtolower( $value );
			if ( '' === $value || isset( $seen[ $value ] ) || strlen( $value ) > 128 || ! preg_match( '/^[a-z][a-z0-9_]*$/', $value ) ) {
				return $this->error( 'import_invalid_header', 'CSV headers must be unique canonical field names.' );
			}
			$seen[ $value ] = true;
			$columns[]      = $value;
		}
		if ( count( $columns ) > self::MAX_COLUMNS ) {
			return $this->error( 'import_too_many_columns', 'The import source contains too many columns.' );
		}
		$rows     = array();
		$warnings = array();
		$line     = 1;
		while ( true ) {
			$values = fgetcsv( $handle, 0, ',', '"', '' );
			if ( false === $values ) {
				break;
			}
			++$line;
			if ( $this->blankRow( $values ) ) {
				$warnings[] = 'Blank CSV row ' . $line . ' was ignored.';
				continue;
			}
			if ( count( $values ) !== count( $columns ) ) {
				return $this->error( 'import_malformed_csv', 'CSV row ' . $line . ' does not match the header column count.' );
			}
			$data = array();
			foreach ( $columns as $index => $column ) {
				$value = $values[ $index ];
				if ( ! is_string( $value ) || strlen( $value ) > self::MAX_CELL_BYTES ) {
					return $this->error( 'import_cell_too_large', 'CSV row ' . $line . ' contains an oversized cell.' );
				}
				$data[ $column ] = $value;
			}
			$rows[] = array(
				'line' => $line,
				'data' => $data,
			);
			if ( count( $rows ) > self::MAX_ROWS ) {
				return $this->error( 'import_too_many_rows', 'The import source exceeds the bounded row limit.' );
			}
		}
		return array(
			'rows'             => $rows,
			'declared_columns' => $columns,
			'warnings'         => $warnings,
		);
	}

	/**
	 * @return array{rows:list<array{line:int,data:array<string,mixed>}>,declared_columns:list<string>,warnings:list<string>}|\WP_Error
	 */
	private function parseJson( string $contents ): array|\WP_Error {
		try {
			$decoded = json_decode( $contents, true, 32, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $exception ) {
			return $this->error( 'import_invalid_json', 'The JSON source is malformed.' );
		}
		$declared = array();
		if ( is_array( $decoded ) && array_key_exists( 'rows', $decoded ) ) {
			$rows = $decoded['rows'];
			if ( array_key_exists( 'columns', $decoded ) ) {
				if ( ! is_array( $decoded['columns'] ) ) {
					return $this->error( 'import_invalid_columns', 'The JSON columns member must be an array.' );
				}
				$seen_columns = array();
				foreach ( $decoded['columns'] as $column ) {
					if ( ! is_string( $column ) ) {
						return $this->error( 'import_invalid_columns', 'The JSON columns member must contain strings.' );
					}
					$column = strtolower( trim( $column ) );
					if ( '' === $column || strlen( $column ) > 128 || ! preg_match( '/^[a-z][a-z0-9_]*$/', $column ) || isset( $seen_columns[ $column ] ) ) {
						return $this->error( 'import_invalid_columns', 'The JSON columns member must contain unique canonical field names.' );
					}
					$seen_columns[ $column ] = true;
					$declared[]              = $column;
				}
				if ( count( $declared ) > self::MAX_COLUMNS ) {
					return $this->error( 'import_invalid_columns', 'The JSON columns member must be unique and bounded.' );
				}
			}
		} elseif ( is_array( $decoded ) && array_is_list( $decoded ) ) {
			$rows = $decoded;
		} elseif ( is_array( $decoded ) ) {
			$rows = array( $decoded );
		} else {
			return $this->error( 'import_invalid_json_shape', 'JSON must contain an object row or a rows array.' );
		}
		if ( ! is_array( $rows ) || count( $rows ) > self::MAX_ROWS ) {
			return $this->error( 'import_too_many_rows', 'The import source exceeds the bounded row limit.' );
		}
		$normalized = array();
		foreach ( $rows as $index => $row ) {
			$line = (int) $index + 1;
			if ( ! is_array( $row ) || array_is_list( $row ) ) {
				return $this->error( 'import_invalid_json_row', 'JSON rows must be objects.' );
			}
			$data      = array();
			$seen_keys = array();
			foreach ( $row as $key => $value ) {
				if ( ! is_string( $key ) || strlen( $key ) > 128 ) {
					return $this->error( 'import_invalid_json_key', 'JSON row keys must be bounded strings.' );
				}
				$key = strtolower( trim( $key ) );
				if ( '' === $key || ! preg_match( '/^[a-z][a-z0-9_]*$/', $key ) || isset( $seen_keys[ $key ] ) ) {
					return $this->error( 'import_invalid_json_key', 'JSON row keys must be unique canonical field names.' );
				}
				$seen_keys[ $key ] = true;
				$data[ $key ]      = $value;
			}
			$normalized[] = array(
				'line' => $line,
				'data' => $data,
			);
		}
		return array(
			'rows'             => $normalized,
			'declared_columns' => $declared,
			'warnings'         => array(),
		);
	}

	/** @param array<int,mixed> $row */
	private function blankRow( array $row ): bool {
		foreach ( $row as $value ) {
			if ( null !== $value && '' !== trim( (string) $value ) ) {
				return false;
			}
		}
		return true;
	}

	private function validUtf8( string $contents ): bool {
		return 1 === preg_match( '//u', $contents );
	}

	private function error( string $code, string $message ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => 400 ) );
	}
}
