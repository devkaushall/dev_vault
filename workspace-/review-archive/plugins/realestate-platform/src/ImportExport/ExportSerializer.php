<?php
/** Deterministic, provider-neutral CSV/JSON serialization primitives. @package RealEstatePlatform */
declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\ImportExport;

final class ExportSerializer {
	/** @param list<string> $columns
	 * @param list<array<string,mixed>> $rows
	 * @return string|\WP_Error */
	public function json( string $entity, array $columns, array $rows ): string|\WP_Error {
		try {
			return json_encode(
				array(
					'entity'  => $entity,
					'columns' => $columns,
					'rows'    => $rows,
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
			);
		} catch ( \JsonException $exception ) {
			return new \WP_Error( 'export_encoding_failed', 'The export contains data that could not be encoded as UTF-8 JSON.', array( 'status' => 500 ) );
		}
	}

	/** @param list<string> $columns
	 * @param list<array<string,mixed>> $rows
	 * @return string|\WP_Error */
	public function csv( array $columns, array $rows ): string|\WP_Error {
		$handle = fopen( 'php://temp', 'w+b' );
		if ( false === $handle ) {
			return new \WP_Error( 'export_write_failed', 'The export stream could not be opened.', array( 'status' => 500 ) );
		}
		if ( ! $this->write( $handle, $columns ) ) {
			fclose( $handle );
			return new \WP_Error( 'export_write_failed', 'The export stream could not be written.', array( 'status' => 500 ) );
		}
		foreach ( $rows as $row ) {
			$values = array_map( fn( string $column ): string => $this->csvValue( $row[ $column ] ?? null ), $columns );
			if ( ! $this->write( $handle, $values ) ) {
				fclose( $handle );
				return new \WP_Error( 'export_write_failed', 'The export stream could not be written.', array( 'status' => 500 ) );
			}
		}
		rewind( $handle );
		$output = stream_get_contents( $handle );
		fclose( $handle );
		if ( false === $output ) {
			return new \WP_Error( 'export_write_failed', 'The export stream could not be read.', array( 'status' => 500 ) );
		}
		return $output;
	}

	/** @param list<mixed> $values */
	public function line( array $values ): string|\WP_Error {
		$handle = fopen( 'php://temp', 'w+b' );
		if ( false === $handle || false === fputcsv( $handle, array_map( fn( mixed $value ): string => $this->csvValue( $value ), $values ), ',', '"', '', "\n" ) ) {
			if ( false !== $handle ) {
				fclose( $handle );
			}
			return new \WP_Error( 'export_write_failed', 'The CSV line could not be serialized.', array( 'status' => 500 ) );
		}
		rewind( $handle );
		$line = stream_get_contents( $handle );
		fclose( $handle );
		return false === $line ? new \WP_Error( 'export_write_failed', 'The CSV line could not be read.', array( 'status' => 500 ) ) : $line;
	}

	public function csvValue( mixed $value ): string {
		if ( null === $value ) {
			return '';
		}
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		if ( is_array( $value ) ) {
			$value = implode( '|', array_map( fn( mixed $item ): string => $this->csvValue( $item ), $value ) );
		}
		$value = (string) $value;
		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ? "'" . $value : $value;
	}

	/** @param resource $handle
	 * @param list<mixed> $values */
	private function write( $handle, array $values ): bool {
		return false !== fputcsv( $handle, $values, ',', '"', '', "\n" );
	}
}
