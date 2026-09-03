<?php
/** Deterministic import validation, plan, and execution report. @package RealEstatePlatform */
declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\ImportExport;

final class ImportReport implements \JsonSerializable {
	/** @var array<string,int> */
	private array $counts = array(
		'total'               => 0,
		'valid'               => 0,
		'invalid'             => 0,
		'create'              => 0,
		'update'              => 0,
		'conflict'            => 0,
		'skipped'             => 0,
		'imported'            => 0,
		'failed'              => 0,
		'taxonomy_issues'     => 0,
		'relationship_issues' => 0,
		'media_issues'        => 0,
		'warning_count'       => 0,
		'error_count'         => 0,
	);

	/** @var list<array<string,mixed>> */
	private array $rows = array();
	/** @var list<string> */
	private array $warnings = array();
	/** @var list<string> */
	private array $fatal_errors = array();
	private bool $finalized     = false;
	private string $status      = 'RUNNING';

	public function __construct( private string $entity, private string $mode, private string $strategy, private string $format ) {}

	/**
	 * @param array<string,mixed> $meta
	 * @param list<string>        $errors
	 * @param list<string>        $warnings
	 */
	public function row( int $line, string $status, array $meta = array(), array $errors = array(), array $warnings = array() ): void {
		if ( count( $this->rows ) >= SourceParser::MAX_ROWS ) {
			return;
		}
		$status = strtolower( $status );
		++$this->counts['total'];
		if ( in_array( $status, array( 'create', 'update', 'imported' ), true ) ) {
			++$this->counts['valid'];
		}
		if ( 'invalid' === $status ) {
			++$this->counts['invalid'];
		}
		if ( 'conflict' === $status ) {
			++$this->counts['conflict'];
		}
		if ( 'failed' === $status ) {
			++$this->counts['failed'];
		}
		if ( 'skipped' === $status ) {
			++$this->counts['skipped'];
		}
		$decision = (string) ( $meta['decision'] ?? '' );
		if ( 'create' === $decision ) {
			++$this->counts['create'];
		}
		if ( 'update' === $decision ) {
			++$this->counts['update'];
		}
		if ( 'imported' === $status ) {
			++$this->counts['imported'];
		}
		$this->counts['warning_count'] += count( $warnings );
		$this->counts['error_count']   += count( $errors );
		$this->categorize( $errors );
		$this->rows[] = array(
			'line'       => $line,
			'status'     => $status,
			'decision'   => $decision,
			'id'         => isset( $meta['id'] ) ? (int) $meta['id'] : null,
			'identity'   => isset( $meta['identity'] ) ? (string) $meta['identity'] : null,
			'errors'     => array_values( array_slice( $errors, 0, 20 ) ),
			'warnings'   => array_values( array_slice( $warnings, 0, 20 ) ),
			'created_at' => isset( $meta['created_at'] ) ? (string) $meta['created_at'] : null,
		);
	}

	/** @param list<string> $messages */
	public function warning( array $messages ): void {
		foreach ( $messages as $message ) {
			if ( count( $this->warnings ) < 100 ) {
				$this->warnings[] = $message;
			}
		}
		$this->counts['warning_count'] += count( $messages );
	}

	public function fatal( string $message ): void {
		if ( count( $this->fatal_errors ) < 20 ) {
			$this->fatal_errors[] = $message;
		}
		++$this->counts['error_count'];
	}

	public function finalize(): void {
		if ( $this->finalized ) {
			return;
		}
		$this->finalized = true;
		$this->status    = array() !== $this->fatal_errors || $this->counts['invalid'] > 0 || $this->counts['conflict'] > 0 || $this->counts['failed'] > 0 ? 'FAIL' : 'PASS';
	}

	/** @return array<string,mixed> */
	public function toArray(): array {
		$this->finalize();
		return array(
			'status'   => $this->status,
			'mutation' => in_array( $this->mode, array( 'validate', 'dry_run' ), true ) ? 'NONE' : ( $this->counts['imported'] > 0 ? 'APPLIED' : 'NONE' ),
			'entity'   => $this->entity,
			'mode'     => $this->mode,
			'strategy' => $this->strategy,
			'format'   => $this->format,
			'counts'   => $this->counts,
			'rows'     => $this->rows,
			'warnings' => $this->warnings,
			'errors'   => $this->fatal_errors,
		);
	}

	/** @return array<string,mixed> */
	public function jsonSerialize(): array {
		return $this->toArray();
	}

	/** @param list<string> $errors */
	private function categorize( array $errors ): void {
		foreach ( $errors as $error ) {
			if ( str_starts_with( $error, 'taxonomy:' ) ) {
				++$this->counts['taxonomy_issues'];
			}
			if ( str_starts_with( $error, 'relationship:' ) ) {
				++$this->counts['relationship_issues'];
			}
			if ( str_starts_with( $error, 'media:' ) ) {
				++$this->counts['media_issues'];
			}
		}
	}
}
