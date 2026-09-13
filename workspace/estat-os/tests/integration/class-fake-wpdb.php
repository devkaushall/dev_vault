<?php
/**
 * An in-memory $wpdb that actually interprets the SQL it is given.
 *
 * This exists because no MySQL server is available in the QA environment.
 * It deliberately does NOT rubber-stamp queries: it parses INSERT / UPDATE /
 * DELETE / SELECT, applies WHERE filtering, ORDER BY, LIMIT and the aggregate
 * functions the plugin uses, and it throws on a malformed prepare() call.
 * That means genuine SQL mistakes and placeholder mismatches still fail.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

/**
 * Minimal but honest wpdb replacement.
 */
class Estat_Fake_WPDB {

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * Posts table name.
	 *
	 * @var string
	 */
	public string $posts = 'wp_posts';

	/**
	 * Postmeta table name.
	 *
	 * @var string
	 */
	public string $postmeta = 'wp_postmeta';

	/**
	 * Users table name.
	 *
	 * @var string
	 */
	public string $users = 'wp_users';

	/**
	 * Last insert ID.
	 *
	 * @var int
	 */
	public int $insert_id = 0;

	/**
	 * Last error.
	 *
	 * @var string
	 */
	public string $last_error = '';

	/**
	 * Character set collation clause.
	 *
	 * @var string
	 */
	public string $charset = 'utf8mb4';

	/**
	 * In-memory rows keyed by table.
	 *
	 * @var array<string,array<int,array<string,mixed>>>
	 */
	public array $tables = array();

	/**
	 * Auto-increment counters keyed by table.
	 *
	 * @var array<string,int>
	 */
	private array $auto = array();

	/**
	 * Every query executed, for performance assertions.
	 *
	 * @var string[]
	 */
	public array $queries = array();

	/**
	 * Create a table.
	 *
	 * @param string $name Table name.
	 * @return void
	 */
	public function create_table( string $name ): void {
		$this->tables[ $name ] = array();
		$this->auto[ $name ]   = 0;
	}

	/**
	 * Whether a table exists.
	 *
	 * @param string $name Table name.
	 * @return bool
	 */
	public function has_table( string $name ): bool {
		return isset( $this->tables[ $name ] );
	}

	/**
	 * Collation clause.
	 *
	 * @return string
	 */
	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	/**
	 * Escape a value the way $wpdb does for LIKE clauses.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	/**
	 * Interpolate a prepared statement.
	 *
	 * Mirrors the real signature: prepare( $sql, $args... ) or
	 * prepare( $sql, array $args ).
	 *
	 * @param string $query Query with placeholders.
	 * @param mixed  ...$args Arguments.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$placeholders = preg_match_all( '/%[sdfF]/', $query );
		if ( $placeholders !== count( $args ) ) {
			throw new RuntimeException(
				sprintf(
					'wpdb::prepare() placeholder mismatch: %d placeholders, %d arguments. SQL: %s',
					$placeholders,
					count( $args ),
					$query
				)
			);
		}

		$i = 0;
		return (string) preg_replace_callback(
			'/%[sdfF]/',
			function ( $m ) use ( &$i, $args ) {
				$value = $args[ $i ];
				$i++;
				if ( '%d' === $m[0] ) {
					return (string) (int) $value;
				}
				if ( '%f' === $m[0] || '%F' === $m[0] ) {
					return (string) (float) $value;
				}
				return "'" . str_replace( "'", "''", (string) $value ) . "'";
			},
			$query
		);
	}

	/**
	 * Insert a row.
	 *
	 * @param string               $table  Table.
	 * @param array<string,mixed>  $data   Data.
	 * @param mixed                $format Ignored.
	 * @return int|false
	 */
	public function insert( $table, $data, $format = null ) {
		$this->queries[] = 'INSERT ' . $table;
		if ( ! isset( $this->tables[ $table ] ) ) {
			$this->last_error = "Table {$table} does not exist";
			return false;
		}
		$this->auto[ $table ] = ( $this->auto[ $table ] ?? 0 ) + 1;
		$id                   = $this->auto[ $table ];

		// Real MySQL materialises every column declared in CREATE TABLE, with
		// NULL for anything the INSERT omitted. Mirroring that here means code
		// that reads an unset column behaves the same as it would in production.
		$skeleton = array();
		foreach ( $GLOBALS['estat_table_columns'][ $table ] ?? array() as $column ) {
			$skeleton[ $column ] = null;
		}
		$row = array_merge( $skeleton, array( 'id' => $id ), $data );
		$this->tables[ $table ][ $id ] = $row;
		$this->insert_id      = $id;
		return 1;
	}

	/**
	 * Update rows.
	 *
	 * @param string              $table  Table.
	 * @param array<string,mixed> $data   Data.
	 * @param array<string,mixed> $where  Where clause.
	 * @param mixed               $format Ignored.
	 * @param mixed               $wf     Ignored.
	 * @return int|false
	 */
	public function update( $table, $data, $where, $format = null, $wf = null ) {
		$this->queries[] = 'UPDATE ' . $table;
		if ( ! isset( $this->tables[ $table ] ) ) {
			$this->last_error = "Table {$table} does not exist";
			return false;
		}
		$count = 0;
		foreach ( $this->tables[ $table ] as $id => $row ) {
			$match = true;
			foreach ( $where as $key => $value ) {
				if ( ! array_key_exists( $key, $row ) || (string) $row[ $key ] !== (string) $value ) {
					$match = false;
					break;
				}
			}
			if ( $match ) {
				$this->tables[ $table ][ $id ] = array_merge( $row, $data );
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Delete rows.
	 *
	 * @param string              $table Table.
	 * @param array<string,mixed> $where Where clause.
	 * @param mixed               $f     Ignored.
	 * @return int|false
	 */
	public function delete( $table, $where, $f = null ) {
		$this->queries[] = 'DELETE ' . $table;
		if ( ! isset( $this->tables[ $table ] ) ) {
			return false;
		}
		$count = 0;
		foreach ( $this->tables[ $table ] as $id => $row ) {
			$match = true;
			foreach ( $where as $key => $value ) {
				if ( ! array_key_exists( $key, $row ) || (string) $row[ $key ] !== (string) $value ) {
					$match = false;
					break;
				}
			}
			if ( $match ) {
				unset( $this->tables[ $table ][ $id ] );
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Run a raw query.
	 *
	 * @param string $sql SQL.
	 * @return mixed
	 */
	public function query( $sql ) {
		$this->queries[] = $sql;
		$sql             = trim( $sql );

		if ( preg_match( '/^CREATE TABLE\s+`?(\w+)`?/i', $sql, $m ) ) {
			if ( ! isset( $this->tables[ $m[1] ] ) ) {
				$this->create_table( $m[1] );
			}
			return true;
		}
		if ( preg_match( '/^DROP TABLE(?: IF EXISTS)?\s+`?(\w+)`?/i', $sql, $m ) ) {
			unset( $this->tables[ $m[1] ] );
			return true;
		}
		if ( preg_match( '/^DELETE FROM\s+`?(\w+)`?(.*)$/is', $sql, $m ) ) {
			$table = $m[1];
			if ( ! isset( $this->tables[ $table ] ) ) {
				return 0;
			}
			$rows  = $this->filter_rows( $this->tables[ $table ], $m[2] );
			$count = 0;
			foreach ( $rows as $row ) {
				unset( $this->tables[ $table ][ $row['id'] ] );
				$count++;
			}
			return $count;
		}
		if ( preg_match( '/^UPDATE\s+`?(\w+)`?\s+SET\s+(.+?)(\s+WHERE\s+.*)?$/is', $sql, $m ) ) {
			$table = $m[1];
			if ( ! isset( $this->tables[ $table ] ) ) {
				return 0;
			}
			$sets = array();
			foreach ( explode( ',', $m[2] ) as $pair ) {
				if ( preg_match( "/\s*`?(\w+)`?\s*=\s*'?([^']*)'?\s*/", $pair, $pm ) ) {
					$sets[ $pm[1] ] = $pm[2];
				}
			}
			$rows  = $this->filter_rows( $this->tables[ $table ], $m[3] ?? '' );
			$count = 0;
			foreach ( $rows as $row ) {
				$this->tables[ $table ][ $row['id'] ] = array_merge( $row, $sets );
				$count++;
			}
			return $count;
		}
		return true;
	}

	/**
	 * Fetch a single scalar.
	 *
	 * @param string $sql SQL.
	 * @return string|null
	 */
	public function get_var( $sql ) {
		$this->queries[] = $sql;
		$rows            = $this->run_select( $sql );
		if ( ! $rows ) {
			return null;
		}
		$first = reset( $rows );
		return (string) reset( $first );
	}

	/**
	 * Fetch one row.
	 *
	 * @param string $sql    SQL.
	 * @param string $output Output type.
	 * @return array<string,mixed>|null
	 */
	public function get_row( $sql, $output = ARRAY_A ) {
		$this->queries[] = $sql;
		$rows            = $this->run_select( $sql );
		return $rows ? reset( $rows ) : null;
	}

	/**
	 * Fetch many rows.
	 *
	 * @param string $sql    SQL.
	 * @param string $output Output type.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_results( $sql, $output = ARRAY_A ) {
		$this->queries[] = $sql;
		return array_values( $this->run_select( $sql ) );
	}

	/**
	 * Fetch one column.
	 *
	 * @param string $sql SQL.
	 * @return array<int,string>
	 */
	public function get_col( $sql ) {
		$this->queries[] = $sql;
		$out             = array();
		foreach ( $this->run_select( $sql ) as $row ) {
			$out[] = (string) reset( $row );
		}
		return $out;
	}

	/**
	 * Execute a SELECT against the in-memory rows.
	 *
	 * @param string $sql SQL.
	 * @return array<int,array<string,mixed>>
	 */
	private function run_select( string $sql ): array {
		$sql = trim( preg_replace( '/\s+/', ' ', $sql ) ?? '' );

		if ( ! preg_match( '/^SELECT\s+(.+?)\s+FROM\s+`?(\w+)`?(.*)$/is', $sql, $m ) ) {
			// SHOW TABLES LIKE …
			if ( preg_match( "/^SHOW TABLES LIKE '(\w+)'/i", $sql, $sm ) ) {
				return isset( $this->tables[ $sm[1] ] )
					? array( array( 'name' => $sm[1] ) )
					: array();
			}
			return array();
		}

		$select = trim( $m[1] );
		$table  = $m[2];
		$rest   = $m[3];

		if ( ! isset( $this->tables[ $table ] ) ) {
			$this->last_error = "Table {$table} does not exist";
			return array();
		}

		$rows = $this->filter_rows( $this->tables[ $table ], $rest );

		// GROUP BY.
		if ( preg_match( '/GROUP BY\s+`?(\w+)`?/i', $rest, $gm ) ) {
			$groups = array();
			foreach ( $rows as $row ) {
				$key = (string) ( $row[ $gm[1] ] ?? '' );
				$groups[ $key ][] = $row;
			}
			$out = array();
			foreach ( $groups as $key => $bucket ) {
				$out[] = $this->project( $select, $bucket, array( $gm[1] => $key ) );
			}
			return $out;
		}

		// Aggregates without GROUP BY.
		if ( preg_match( '/\b(COUNT|SUM|AVG|MIN|MAX)\s*\(/i', $select ) ) {
			return array( $this->project( $select, $rows, array() ) );
		}

		// ORDER BY.
		if ( preg_match( '/ORDER BY\s+`?(\w+)`?\s*(ASC|DESC)?/i', $rest, $om ) ) {
			$field = $om[1];
			$dir   = strtoupper( $om[2] ?? 'ASC' );
			usort(
				$rows,
				function ( $a, $b ) use ( $field, $dir ) {
					$av = $a[ $field ] ?? '';
					$bv = $b[ $field ] ?? '';
					$cmp = is_numeric( $av ) && is_numeric( $bv )
						? ( (float) $av <=> (float) $bv )
						: strcmp( (string) $av, (string) $bv );
					return 'DESC' === $dir ? -$cmp : $cmp;
				}
			);
		}

		// LIMIT / OFFSET.
		if ( preg_match( '/LIMIT\s+(\d+)(?:\s+OFFSET\s+(\d+))?/i', $rest, $lm ) ) {
			$limit  = (int) $lm[1];
			$offset = isset( $lm[2] ) ? (int) $lm[2] : 0;
			$rows   = array_slice( array_values( $rows ), $offset, $limit );
		}

		if ( '*' === $select ) {
			return array_values( $rows );
		}

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = $this->project( $select, array( $row ), $row );
		}
		return $out;
	}

	/**
	 * Apply a WHERE clause.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows.
	 * @param string                         $rest SQL tail.
	 * @return array<int,array<string,mixed>>
	 */
	private function filter_rows( array $rows, string $rest ): array {
		if ( ! preg_match( '/WHERE\s+(.*?)(?:\s+GROUP BY|\s+ORDER BY|\s+LIMIT|$)/is', $rest, $wm ) ) {
			return array_values( $rows );
		}
		$where = trim( $wm[1] );
		if ( '' === $where || '1=1' === $where ) {
			return array_values( $rows );
		}

		$out = array();
		foreach ( $rows as $row ) {
			if ( $this->evaluate( $where, $row ) ) {
				$out[] = $row;
			}
		}
		return array_values( $out );
	}

	/**
	 * Evaluate a simple boolean SQL expression against one row.
	 *
	 * @param string              $expr Expression.
	 * @param array<string,mixed> $row  Row.
	 * @return bool
	 */
	private function evaluate( string $expr, array $row ): bool {
		$expr = trim( $expr );

		// Split on top-level AND / OR.
		$parts = preg_split( '/\s+AND\s+/i', $expr );
		if ( $parts && count( $parts ) > 1 ) {
			foreach ( $parts as $part ) {
				if ( ! $this->evaluate( $part, $row ) ) {
					return false;
				}
			}
			return true;
		}
		$parts = preg_split( '/\s+OR\s+/i', $expr );
		if ( $parts && count( $parts ) > 1 ) {
			foreach ( $parts as $part ) {
				if ( $this->evaluate( $part, $row ) ) {
					return true;
				}
			}
			return false;
		}

		$expr = trim( $expr, '() ' );

		if ( '1=1' === str_replace( ' ', '', $expr ) ) {
			return true;
		}

		// field IN ('a','b')
		if ( preg_match( "/`?(\w+)`?\s+IN\s*\((.*?)\)/i", $expr, $m ) ) {
			$field  = $m[1];
			$values = array_map(
				static fn( $v ) => trim( trim( $v ), "'" ),
				explode( ',', $m[2] )
			);
			return in_array( (string) ( $row[ $field ] ?? '' ), $values, true );
		}

		// field LIKE '%x%'
		if ( preg_match( "/`?(\w+)`?\s+LIKE\s+'(.*?)'/i", $expr, $m ) ) {
			$needle = str_replace( '%', '', $m[2] );
			return false !== stripos( (string) ( $row[ $m[1] ] ?? '' ), $needle );
		}

		// field IS NULL / IS NOT NULL
		if ( preg_match( '/`?(\w+)`?\s+IS\s+(NOT\s+)?NULL/i', $expr, $m ) ) {
			$isnull = ! isset( $row[ $m[1] ] ) || null === $row[ $m[1] ] || '' === $row[ $m[1] ];
			return empty( $m[2] ) ? $isnull : ! $isnull;
		}

		// field <op> value
		if ( preg_match( "/`?(\w+)`?\s*(>=|<=|!=|<>|=|>|<)\s*'?([^']*)'?/", $expr, $m ) ) {
			$field = $m[1];
			$op    = $m[2];
			$value = trim( $m[3] );
			$left  = $row[ $field ] ?? null;

			if ( is_numeric( $left ) && is_numeric( $value ) ) {
				$left  = (float) $left;
				$value = (float) $value;
			} else {
				$left  = (string) $left;
				$value = (string) $value;
			}

			switch ( $op ) {
				case '=':
					return $left == $value; // phpcs:ignore Universal.Operators.StrictComparisons
				case '!=':
				case '<>':
					return $left != $value; // phpcs:ignore Universal.Operators.StrictComparisons
				case '>':
					return $left > $value;
				case '<':
					return $left < $value;
				case '>=':
					return $left >= $value;
				case '<=':
					return $left <= $value;
			}
		}

		return true;
	}

	/**
	 * Build a projected result row for a SELECT list.
	 *
	 * @param string                         $select Select list.
	 * @param array<int,array<string,mixed>> $rows   Rows in scope.
	 * @param array<string,mixed>            $base   Base row values.
	 * @return array<string,mixed>
	 */
	private function project( string $select, array $rows, array $base ): array {
		$out = array();
		foreach ( $this->split_select( $select ) as $piece ) {
			$piece = trim( $piece );
			$alias = null;
			if ( preg_match( '/^(.*?)\s+AS\s+`?(\w+)`?$/i', $piece, $am ) ) {
				$piece = trim( $am[1] );
				$alias = $am[2];
			}

			if ( preg_match( '/^COUNT\s*\(\s*(.*?)\s*\)$/i', $piece, $m ) ) {
				$value = count( $rows );
				$out[ $alias ?? 'COUNT(' . $m[1] . ')' ] = (string) $value;
				continue;
			}
			if ( preg_match( '/^(SUM|AVG|MIN|MAX)\s*\(\s*(.*?)\s*\)$/i', $piece, $m ) ) {
				$fn   = strtoupper( $m[1] );
				$inner = $m[2];
				$values = array();
				foreach ( $rows as $r ) {
					$values[] = $this->scalar( $inner, $r );
				}
				$values = array_filter( $values, static fn( $v ) => null !== $v );
				if ( ! $values ) {
					$out[ $alias ?? $piece ] = null;
					continue;
				}
				$value = 'SUM' === $fn ? array_sum( $values )
					: ( 'AVG' === $fn ? array_sum( $values ) / count( $values )
					: ( 'MIN' === $fn ? min( $values ) : max( $values ) ) );
				$out[ $alias ?? $piece ] = (string) $value;
				continue;
			}

			$name        = preg_replace( '/^.*\./', '', $piece );
			$name        = trim( (string) $name, '`' );
			$out[ $alias ?? $name ] = $base[ $name ] ?? ( $rows ? ( $rows[0][ $name ] ?? null ) : null );
		}
		return $out;
	}

	/**
	 * Resolve one scalar expression against a row.
	 *
	 * @param string              $expr Expression.
	 * @param array<string,mixed> $row  Row.
	 * @return float|null
	 */
	private function scalar( string $expr, array $row ) {
		$expr = trim( $expr );

		if ( preg_match( '/^CASE\s+WHEN\s+(.*?)\s+THEN\s+(\S+)\s+ELSE\s+(\S+)\s+END$/is', $expr, $m ) ) {
			return $this->evaluate( $m[1], $row ) ? (float) $m[2] : (float) $m[3];
		}
		if ( preg_match( '/^NULLIF\s*\(\s*`?(\w+)`?\s*,\s*([\d.]+)\s*\)$/i', $expr, $m ) ) {
			$v = (float) ( $row[ $m[1] ] ?? 0 );
			return $v === (float) $m[2] ? null : $v;
		}
		$name = trim( $expr, '`' );
		if ( ! isset( $row[ $name ] ) || null === $row[ $name ] || '' === $row[ $name ] ) {
			return null;
		}
		return (float) $row[ $name ];
	}

	/**
	 * Split a SELECT list on commas that are not inside brackets.
	 *
	 * @param string $select Select list.
	 * @return string[]
	 */
	private function split_select( string $select ): array {
		$out   = array();
		$depth = 0;
		$buf   = '';
		foreach ( str_split( $select ) as $ch ) {
			if ( '(' === $ch ) {
				$depth++;
			} elseif ( ')' === $ch ) {
				$depth--;
			}
			if ( ',' === $ch && 0 === $depth ) {
				$out[] = $buf;
				$buf   = '';
				continue;
			}
			$buf .= $ch;
		}
		if ( '' !== trim( $buf ) ) {
			$out[] = $buf;
		}
		return $out;
	}
}
