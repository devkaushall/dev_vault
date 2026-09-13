<?php
/**
 * Spreadsheet import and export.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Csv;

use EstatOS\Audit\AuditLog;
use EstatOS\Data\Agents;
use EstatOS\Data\Listings;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Projects;
use EstatOS\Install\Schema;
use EstatOS\Leads\Leads;
use EstatOS\Leads\Visits;
use EstatOS\Notifications\Notifier;
use EstatOS\Search\SearchIndex;
use EstatOS\Support\Sanitize;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Imports run in resumable batches and are duplicate safe: rows carrying a
 * reference number update the existing record instead of creating a second one,
 * so importing the same file twice changes nothing.
 */
final class Spreadsheet {

	/**
	 * Rows handled per batch.
	 */
	public const BATCH = 50;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {}

	/**
	 * The documented column contract per record kind.
	 *
	 * @param string $kind listings|projects|agents|leads.
	 * @return array<string,string> Column => description.
	 */
	public static function columns( string $kind ): array {
		switch ( $kind ) {
			case 'projects':
				return array(
					'external_id'     => __( 'Your own reference number (used to avoid duplicates)', 'estat-os' ),
					'title'           => __( 'Name of the society or project', 'estat-os' ),
					'description'     => __( 'Description', 'estat-os' ),
					'locality'        => __( 'Locality', 'estat-os' ),
					'developer'       => __( 'Builder or developer', 'estat-os' ),
					'project_status'  => __( 'Stage: ready, construction, new_launch, completed', 'estat-os' ),
					'possession_date' => __( 'Possession date (YYYY-MM-DD)', 'estat-os' ),
					'price_min'       => __( 'Lowest price (numbers only)', 'estat-os' ),
					'price_max'       => __( 'Highest price (numbers only)', 'estat-os' ),
					'total_units'     => __( 'Total units', 'estat-os' ),
					'available_units' => __( 'Units still available', 'estat-os' ),
					'unit_types'      => __( 'Unit types, for example 2 BHK, 3 BHK', 'estat-os' ),
					'regulatory_id'   => __( 'Registration number', 'estat-os' ),
					'latitude'        => __( 'Map latitude', 'estat-os' ),
					'longitude'       => __( 'Map longitude', 'estat-os' ),
				);
			case 'agents':
				return array(
					'external_id' => __( 'Your own reference number', 'estat-os' ),
					'title'       => __( 'Full name', 'estat-os' ),
					'role'        => __( 'Role in the office', 'estat-os' ),
					'phone'       => __( 'Phone number', 'estat-os' ),
					'whatsapp'    => __( 'WhatsApp number', 'estat-os' ),
					'email'       => __( 'Email address', 'estat-os' ),
					'bio'         => __( 'Short biography', 'estat-os' ),
				);
			case 'leads':
				return array(
					'name'        => __( 'Name of the person', 'estat-os' ),
					'phone'       => __( 'Phone number', 'estat-os' ),
					'email'       => __( 'Email address', 'estat-os' ),
					'message'     => __( 'What they asked', 'estat-os' ),
					'source'      => __( 'Where it came from', 'estat-os' ),
					'listing_ref' => __( 'Reference number of the property they asked about', 'estat-os' ),
					'status'      => __( 'Status: new, contacted, qualified, visit_scheduled, converted, lost', 'estat-os' ),
				);
			case 'visits':
				/*
				 * The Spreadsheet screen has always offered "Export site
				 * visits", but this switch had no case for it, so
				 * Sanitize::choice() fell back to 'listings' and the office
				 * downloaded an empty listings sheet with no warning at all.
				 */
				return array(
					'scheduled_at' => __( 'When the visit is', 'estat-os' ),
					'name'         => __( 'Who is visiting', 'estat-os' ),
					'phone'        => __( 'Phone number', 'estat-os' ),
					'listing_ref'  => __( 'Reference number of the property', 'estat-os' ),
					'listing_name' => __( 'Name of the property', 'estat-os' ),
					'agent'        => __( 'Team member showing them round', 'estat-os' ),
					'outcome'      => __( 'How it went: pending, done, no_show, cancelled', 'estat-os' ),
					'notes'        => __( 'Notes', 'estat-os' ),
				);
			case 'listings':
			default:
				return array(
					'external_id'     => __( 'Your own reference number (used to avoid duplicates)', 'estat-os' ),
					'title'           => __( 'Headline for the property', 'estat-os' ),
					'description'     => __( 'Full description', 'estat-os' ),
					'offer'           => __( 'sale, rent or lease', 'estat-os' ),
					'property_type'   => __( 'apartment, villa, plot, office and so on', 'estat-os' ),
					'price'           => __( 'Price (numbers only)', 'estat-os' ),
					'price_type'      => __( 'fixed, negotiable, starting_from or on_request', 'estat-os' ),
					'rent'            => __( 'Monthly rent (numbers only)', 'estat-os' ),
					'deposit'         => __( 'Security deposit', 'estat-os' ),
					'maintenance'     => __( 'Monthly maintenance', 'estat-os' ),
					'area'            => __( 'Size (numbers only)', 'estat-os' ),
					'area_unit'       => __( 'sqft, sqyd or sqm', 'estat-os' ),
					'area_type'       => __( 'carpet, builtup, super or plot', 'estat-os' ),
					'bedrooms'        => __( 'Number of bedrooms', 'estat-os' ),
					'bathrooms'       => __( 'Number of bathrooms', 'estat-os' ),
					'balconies'       => __( 'Number of balconies', 'estat-os' ),
					'parking'         => __( 'Parking spaces', 'estat-os' ),
					'floor'           => __( 'Which floor', 'estat-os' ),
					'total_floors'    => __( 'Floors in the building', 'estat-os' ),
					'age'             => __( 'Age in years', 'estat-os' ),
					'facing'          => __( 'Direction it faces', 'estat-os' ),
					'furnishing'      => __( 'unfurnished, semi or furnished', 'estat-os' ),
					'address'         => __( 'Full address', 'estat-os' ),
					'locality'        => __( 'Locality (separate several with commas)', 'estat-os' ),
					'amenities'       => __( 'Amenities (separate several with commas)', 'estat-os' ),
					'latitude'        => __( 'Map latitude', 'estat-os' ),
					'longitude'       => __( 'Map longitude', 'estat-os' ),
					'availability'    => __( 'available, on_hold, sold or rented', 'estat-os' ),
					'construction'    => __( 'ready, construction or new_launch', 'estat-os' ),
					'possession_date' => __( 'Possession date (YYYY-MM-DD)', 'estat-os' ),
					'project_ref'     => __( 'Reference number of the society/project', 'estat-os' ),
					'agent_ref'       => __( 'Reference number of the team member', 'estat-os' ),
					'developer'       => __( 'Builder or developer', 'estat-os' ),
					'regulatory_id'   => __( 'Registration number', 'estat-os' ),
					'featured'        => __( 'yes or no', 'estat-os' ),
					'investment'      => __( 'yes or no', 'estat-os' ),
					'status'          => __( 'draft or publish', 'estat-os' ),
				);
		}
	}

	/**
	 * Read the header row and a few sample rows from an uploaded file.
	 *
	 * @param string $path File path.
	 * @param int    $sample_rows Sample rows to read.
	 * @return array{headers:string[],rows:array<int,string[]>,total:int}|WP_Error
	 */
	public static function preview( string $path, int $sample_rows = 5 ) {
		$handle = self::open( $path );
		if ( is_wp_error( $handle ) ) {
			return $handle;
		}
		$headers = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! is_array( $headers ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'estat_bad_csv', __( 'We could not read the first line of this file. Please check that it is a CSV with column names.', 'estat-os' ) );
		}
		$headers = array_map( static fn( $h ) => Sanitize::text( (string) $h ), $headers );

		$rows  = array();
		$total = 0;
		while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) ) { // phpcs:ignore
			++$total;
			if ( count( $rows ) < $sample_rows ) {
				$rows[] = array_map( static fn( $v ) => Sanitize::text( (string) $v ), (array) $row );
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return array( 'headers' => $headers, 'rows' => $rows, 'total' => $total );
	}

	/**
	 * Guess a mapping from file headers to known columns.
	 *
	 * @param string[] $headers File headers.
	 * @param string   $kind    Record kind.
	 * @return array<string,string> Column => header.
	 */
	public static function guess_mapping( array $headers, string $kind ): array {
		$mapping = array();
		$known   = array_keys( self::columns( $kind ) );
		foreach ( $known as $column ) {
			foreach ( $headers as $header ) {
				$normalized = strtolower( str_replace( array( ' ', '-' ), '_', trim( $header ) ) );
				if ( $normalized === $column ) {
					$mapping[ $column ] = $header;
					break;
				}
			}
		}
		return $mapping;
	}

	/**
	 * Create an import job.
	 *
	 * @param string               $path    Stored file path.
	 * @param string               $kind    Record kind.
	 * @param array<string,string> $mapping Column mapping.
	 * @return int|WP_Error Job ID.
	 */
	public static function create_job( string $path, string $kind, array $mapping ) {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return new WP_Error( 'estat_no_storage', __( 'The office database is not ready yet.', 'estat-os' ) );
		}
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'estat_missing_file', __( 'The uploaded file could not be found. Please upload it again.', 'estat-os' ) );
		}
		$kind    = Sanitize::choice( $kind, array( 'listings', 'projects', 'agents', 'leads' ), 'listings' );
		$preview = self::preview( $path, 0 );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}

		$hash  = (string) hash_file( 'sha256', $path );
		$table = Schema::table( 'estat_import_jobs' );
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'created_at' => $now,
				'updated_at' => $now,
				'user_id'    => get_current_user_id(),
				'kind'       => $kind,
				'file_path'  => $path,
				'mapping'    => (string) wp_json_encode( array_map( array( Sanitize::class, 'text' ), $mapping ) ),
				'state'      => 'running',
				'total_rows' => (int) $preview['total'],
				'file_hash'  => $hash,
			)
		);
		$job_id = (int) $wpdb->insert_id;
		AuditLog::record( 'import.started', 'import', $job_id, array( 'kind' => $kind, 'rows' => $preview['total'] ) );
		return $job_id;
	}

	/**
	 * Process one batch of an import job.
	 *
	 * @param int  $job_id  Job ID.
	 * @param bool $dry_run Validate without writing.
	 * @return array<string,mixed>|WP_Error Progress summary.
	 */
	public static function process_batch( int $job_id, bool $dry_run = false ) {
		global $wpdb;
		$table = Schema::table( 'estat_import_jobs' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $job_id ), ARRAY_A );
		if ( ! $job ) {
			return new WP_Error( 'estat_not_found', __( 'That import could not be found.', 'estat-os' ) );
		}
		if ( 'done' === $job['state'] ) {
			return self::summary( $job );
		}

		$handle = self::open( (string) $job['file_path'] );
		if ( is_wp_error( $handle ) ) {
			return $handle;
		}

		$mapping = json_decode( (string) $job['mapping'], true );
		$mapping = is_array( $mapping ) ? $mapping : array();
		$headers = fgetcsv( $handle, 0, ',', '"', '' );
		$headers = is_array( $headers ) ? array_map( 'strval', $headers ) : array();

		$skip_to   = (int) $job['processed_rows'];
		$processed = 0;
		$counts    = array( 'created' => (int) $job['created_count'], 'updated' => (int) $job['updated_count'], 'skipped' => (int) $job['skipped_count'] );
		$errors    = json_decode( (string) $job['errors'], true );
		$errors    = is_array( $errors ) ? $errors : array();

		$row_number = 0;
		while ( false !== ( $raw = fgetcsv( $handle, 0, ',', '"', '' ) ) ) { // phpcs:ignore
			++$row_number;
			if ( $row_number <= $skip_to ) {
				continue;
			}
			if ( $processed >= self::BATCH ) {
				break;
			}
			++$processed;

			$assoc  = self::map_row( $headers, (array) $raw, $mapping );
			$result = self::import_row( (string) $job['kind'], $assoc, $dry_run );

			if ( is_wp_error( $result ) ) {
				if ( count( $errors ) < 500 ) {
					$errors[] = array(
						'row'     => $row_number + 1,
						'message' => $result->get_error_message(),
						'data'    => $assoc,
					);
				}
				++$counts['skipped'];
				continue;
			}
			++$counts[ $result ];
		}

		$position = $skip_to + $processed;
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$done = $position >= (int) $job['total_rows'] || 0 === $processed;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			array(
				'updated_at'     => current_time( 'mysql', true ),
				'processed_rows' => $position,
				'created_count'  => $counts['created'],
				'updated_count'  => $counts['updated'],
				'skipped_count'  => $counts['skipped'],
				'errors'         => (string) wp_json_encode( $errors ),
				'state'          => $done ? 'done' : 'running',
			),
			array( 'id' => $job_id )
		);

		if ( $done && ! $dry_run ) {
			AuditLog::record( 'import.completed', 'import', $job_id, $counts );
			Notifier::import_finished( $counts );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$fresh = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $job_id ), ARRAY_A );
		return self::summary( is_array( $fresh ) ? $fresh : $job );
	}

	/**
	 * Build a progress summary.
	 *
	 * @param array<string,mixed> $job Job row.
	 * @return array<string,mixed>
	 */
	private static function summary( array $job ): array {
		$errors = json_decode( (string) $job['errors'], true );
		return array(
			'job_id'    => (int) $job['id'],
			'state'     => (string) $job['state'],
			'total'     => (int) $job['total_rows'],
			'processed' => (int) $job['processed_rows'],
			'created'   => (int) $job['created_count'],
			'updated'   => (int) $job['updated_count'],
			'skipped'   => (int) $job['skipped_count'],
			'errors'    => is_array( $errors ) ? $errors : array(),
		);
	}

	/**
	 * Turn a raw CSV row into a mapped associative array.
	 *
	 * @param string[]             $headers Header row.
	 * @param array<int,mixed>     $raw     Raw values.
	 * @param array<string,string> $mapping Column => header.
	 * @return array<string,string>
	 */
	private static function map_row( array $headers, array $raw, array $mapping ): array {
		$by_header = array();
		foreach ( $headers as $index => $header ) {
			$by_header[ $header ] = isset( $raw[ $index ] ) ? Sanitize::text( (string) $raw[ $index ] ) : '';
		}
		$out = array();
		foreach ( $mapping as $column => $header ) {
			$out[ $column ] = $by_header[ $header ] ?? '';
		}
		return $out;
	}

	/**
	 * Import one row.
	 *
	 * @param string                $kind    Record kind.
	 * @param array<string,string>  $row     Mapped row.
	 * @param bool                  $dry_run Validate only.
	 * @return string|WP_Error 'created', 'updated' or an error.
	 */
	private static function import_row( string $kind, array $row, bool $dry_run ) {
		switch ( $kind ) {
			case 'projects':
				$existing = Projects::find_by_external_id( $row['external_id'] ?? '' );
				if ( $dry_run ) {
					return '' === trim( (string) ( $row['title'] ?? '' ) ) && ! $existing
						? new WP_Error( 'estat_missing_title', __( 'This row has no project name.', 'estat-os' ) )
						: ( $existing ? 'updated' : 'created' );
				}
				$result = Projects::save( $row, $existing );
				return is_wp_error( $result ) ? $result : ( $existing ? 'updated' : 'created' );

			case 'agents':
				$existing = self::find_agent( $row['external_id'] ?? '', $row['email'] ?? '' );
				if ( $dry_run ) {
					return '' === trim( (string) ( $row['title'] ?? '' ) ) && ! $existing
						? new WP_Error( 'estat_missing_name', __( 'This row has no name.', 'estat-os' ) )
						: ( $existing ? 'updated' : 'created' );
				}
				$result = Agents::save( $row, $existing );
				return is_wp_error( $result ) ? $result : ( $existing ? 'updated' : 'created' );

			case 'leads':
				if ( $dry_run ) {
					return '' === trim( (string) ( $row['name'] ?? '' ) )
						? new WP_Error( 'estat_missing_name', __( 'This row has no name.', 'estat-os' ) )
						: 'created';
				}
				$listing_id = Listings::find_by_external_id( $row['listing_ref'] ?? '' );
				$lead       = Leads::create(
					array(
						'name'            => $row['name'] ?? '',
						'phone'           => $row['phone'] ?? '',
						'email'           => $row['email'] ?? '',
						'message'         => $row['message'] ?? '',
						'source'          => 'import',
						'listing_id'      => $listing_id,
						// A stable key so re-importing the same file cannot duplicate.
						'idempotency_key' => 'import|' . md5( wp_json_encode( $row ) ),
					)
				);
				return is_wp_error( $lead ) ? $lead : 'created';

			case 'listings':
			default:
				$existing = Listings::find_by_external_id( $row['external_id'] ?? '' );
				$input    = $row;
				if ( isset( $row['project_ref'] ) && '' !== $row['project_ref'] ) {
					$input['project_id'] = Projects::find_by_external_id( $row['project_ref'] );
				}
				if ( isset( $row['agent_ref'] ) && '' !== $row['agent_ref'] ) {
					$input['agent_id'] = self::find_agent( $row['agent_ref'], '' );
				}
				if ( isset( $row['featured'] ) ) {
					$input['featured'] = in_array( strtolower( $row['featured'] ), array( 'yes', '1', 'true', 'y' ), true );
				}
				if ( isset( $row['investment'] ) ) {
					$input['investment'] = in_array( strtolower( $row['investment'] ), array( 'yes', '1', 'true', 'y' ), true );
				}
				$input['status'] = Sanitize::choice( $row['status'] ?? 'draft', array( 'draft', 'publish' ), 'draft' );

				if ( $dry_run ) {
					$errors = Listings::validate( $input, $existing );
					if ( $errors ) {
						return new WP_Error( 'estat_invalid_row', implode( ' ', $errors ) );
					}
					return $existing ? 'updated' : 'created';
				}

				$result = Listings::save( $input, $existing );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				SearchIndex::index_listing( (int) $result );
				return $existing ? 'updated' : 'created';
		}
	}

	/**
	 * Find an agent by reference number or email.
	 *
	 * @param string $external_id Reference.
	 * @param string $email       Email.
	 * @return int
	 */
	private static function find_agent( string $external_id, string $email ): int {
		$external_id = Sanitize::text( $external_id );
		if ( '' !== $external_id ) {
			$found = get_posts(
				array(
					'post_type'     => PostTypes::AGENT,
					'numberposts'   => 1,
					'fields'        => 'ids',
					'meta_key'      => '_estat_external_id', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'    => $external_id, // phpcs:ignore WordPress.DB.SlowDBQuery
					'no_found_rows' => true,
				)
			);
			if ( $found ) {
				return (int) $found[0];
			}
		}
		$email = Sanitize::email( $email );
		if ( '' !== $email ) {
			$found = get_posts(
				array(
					'post_type'     => PostTypes::AGENT,
					'numberposts'   => 1,
					'fields'        => 'ids',
					'meta_key'      => '_estat_email', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'    => $email, // phpcs:ignore WordPress.DB.SlowDBQuery
					'no_found_rows' => true,
				)
			);
			if ( $found ) {
				return (int) $found[0];
			}
		}
		return 0;
	}

	/**
	 * Export records as CSV text.
	 *
	 * @param string $kind  Record kind.
	 * @param int    $limit Maximum rows.
	 * @return string CSV content.
	 */
	public static function export( string $kind, int $limit = 5000 ): string {
		/*
		 * Visits can be exported but not imported: a visit only means anything
		 * attached to an enquiry, so it is created from that screen, never
		 * from a spreadsheet. The import list on line ~205 stays as it is.
		 */
		$kind    = Sanitize::choice( $kind, array( 'listings', 'projects', 'agents', 'leads', 'visits' ), 'listings' );
		$columns = array_keys( self::columns( $kind ) );
		$rows    = array();

		if ( 'visits' === $kind ) {
			$visits = Visits::query( array( 'per_page' => max( 1, min( 500, $limit ) ), 'page' => 1 ) );

			foreach ( (array) $visits['items'] as $visit ) {
				$lead       = (int) $visit['lead_id'] > 0 ? Leads::get( (int) $visit['lead_id'] ) : null;
				$listing_id = (int) $visit['listing_id'];

				$rows[] = array(
					'scheduled_at' => (string) $visit['scheduled_at'],
					'name'         => $lead ? (string) $lead['name'] : '',
					'phone'        => $lead ? (string) $lead['phone'] : '',
					'listing_ref'  => $listing_id > 0 ? (string) get_post_meta( $listing_id, '_estat_external_id', true ) : '',
					'listing_name' => $listing_id > 0 ? (string) get_the_title( $listing_id ) : '',
					'agent'        => (int) $visit['agent_id'] > 0 ? (string) get_the_title( (int) $visit['agent_id'] ) : '',
					'outcome'      => (string) $visit['outcome'],
					'notes'        => (string) ( $visit['notes'] ?? '' ),
				);
			}
		} elseif ( 'leads' === $kind ) {
			$leads = Leads::query( array( 'per_page' => min( 100, $limit ), 'page' => 1 ) );
			foreach ( $leads['items'] as $lead ) {
				$rows[] = array(
					'name'        => $lead['name'],
					'phone'       => $lead['phone'],
					'email'       => $lead['email'],
					'message'     => $lead['message'],
					'source'      => $lead['source'],
					'listing_ref' => (int) $lead['listing_id'] > 0 ? (string) get_post_meta( (int) $lead['listing_id'], '_estat_external_id', true ) : '',
					'status'      => $lead['status'],
				);
			}
		} else {
			$map   = array( 'listings' => PostTypes::LISTING, 'projects' => PostTypes::PROJECT, 'agents' => PostTypes::AGENT );
			$posts = get_posts(
				array(
					'post_type'      => $map[ $kind ],
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => max( 1, min( 5000, $limit ) ),
					'no_found_rows'  => true,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);
			foreach ( $posts as $post ) {
				$row = array();
				foreach ( $columns as $column ) {
					$row[ $column ] = self::export_value( $post, $column, $kind );
				}
				$rows[] = $row;
			}
		}

		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $handle ) {
			return '';
		}
		fputcsv( $handle, $columns, ',', '"', '' );
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $columns as $column ) {
				$line[] = self::safe_cell( (string) ( $row[ $column ] ?? '' ) );
			}
			fputcsv( $handle, $line, ',', '"', '' );
		}
		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $csv;
	}

	/**
	 * Resolve one export cell.
	 *
	 * @param \WP_Post $post   Post.
	 * @param string   $column Column name.
	 * @param string   $kind   Record kind.
	 * @return string
	 */
	private static function export_value( \WP_Post $post, string $column, string $kind ): string {
		switch ( $column ) {
			case 'title':
				return $post->post_title;
			case 'description':
			case 'bio':
				return wp_strip_all_tags( $post->post_content );
			case 'status':
				return $post->post_status;
			case 'locality':
				$terms = wp_get_object_terms( $post->ID, \EstatOS\Data\Taxonomies::LOCALITY, array( 'fields' => 'names' ) );
				return is_wp_error( $terms ) ? '' : implode( ', ', $terms );
			case 'amenities':
				$terms = wp_get_object_terms( $post->ID, \EstatOS\Data\Taxonomies::AMENITY, array( 'fields' => 'names' ) );
				return is_wp_error( $terms ) ? '' : implode( ', ', $terms );
			case 'project_ref':
				$project_id = (int) get_post_meta( $post->ID, '_estat_project_id', true );
				return $project_id ? (string) get_post_meta( $project_id, '_estat_external_id', true ) : '';
			case 'agent_ref':
				$agent_id = (int) get_post_meta( $post->ID, '_estat_agent_id', true );
				return $agent_id ? (string) get_post_meta( $agent_id, '_estat_external_id', true ) : '';
			case 'featured':
			case 'investment':
				return get_post_meta( $post->ID, '_estat_' . $column, true ) ? 'yes' : 'no';
			default:
				$value = get_post_meta( $post->ID, '_estat_' . $column, true );
				if ( is_array( $value ) ) {
					return implode( ',', array_map( 'strval', $value ) );
				}
				return (string) $value;
		}
	}

	/**
	 * Make one cell safe to open in a spreadsheet.
	 *
	 * Excel, LibreOffice and Google Sheets treat a cell starting with `=`, `+`,
	 * `-`, `@`, a tab or a carriage return as a formula. A website visitor can
	 * type such a value into a public enquiry form, so the office would be the
	 * one to trigger it by opening their own export. Prefixing a single quote
	 * makes the spreadsheet show the text exactly as typed instead of running
	 * it. The stored data is never altered — only this exported copy.
	 *
	 * @param string $value Raw cell value.
	 * @return string Safe cell value.
	 */
	private static function safe_cell( string $value ): string {
		if ( '' === $value ) {
			return $value;
		}
		// A plain number such as -500 or +2.5 is data, not a formula.
		if ( is_numeric( $value ) ) {
			return $value;
		}
		$first = substr( $value, 0, 1 );
		if ( in_array( $first, array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Open a CSV file safely.
	 *
	 * @param string $path Path.
	 * @return resource|WP_Error
	 */
	private static function open( string $path ) {
		$uploads = wp_get_upload_dir();
		$real    = realpath( $path );
		$base    = realpath( (string) $uploads['basedir'] );
		if ( ! $real || ! $base || 0 !== strpos( $real, $base ) ) {
			return new WP_Error( 'estat_bad_path', __( 'That file is not in the uploads folder, so we cannot read it.', 'estat-os' ) );
		}
		$handle = fopen( $real, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $handle ) {
			return new WP_Error( 'estat_unreadable', __( 'We could not open the file. Please upload it again.', 'estat-os' ) );
		}
		return $handle;
	}

	/**
	 * The most recent import jobs, newest first.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent_jobs( int $limit = 10 ): array {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return array();
		}
		$table = Schema::table( 'estat_import_jobs' );
		$limit = Sanitize::int( $limit, 1, 100 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Store an uploaded CSV in a private folder inside uploads.
	 *
	 * @param array<string,mixed> $file One entry from $_FILES.
	 * @return string|WP_Error Stored path.
	 */
	public static function store_upload( array $file ) {
		if ( ! isset( $file['tmp_name'], $file['name'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return new WP_Error( 'estat_no_file', __( 'Please choose a CSV file to upload.', 'estat-os' ) );
		}
		$check = wp_check_filetype( (string) $file['name'], array( 'csv' => 'text/csv' ) );
		if ( 'csv' !== $check['ext'] ) {
			return new WP_Error( 'estat_bad_type', __( 'Only CSV files can be imported. Save your spreadsheet as CSV and try again.', 'estat-os' ) );
		}
		if ( (int) ( $file['size'] ?? 0 ) > 20 * MB_IN_BYTES ) {
			return new WP_Error( 'estat_too_big', __( 'That file is larger than 20 MB. Please split it into smaller files.', 'estat-os' ) );
		}

		$uploads = wp_get_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'estat-imports';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'estat_no_dir', __( 'We could not create a folder for imports.', 'estat-os' ) );
		}
		// Keep imported files out of the web root.
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		$name = wp_unique_filename( $dir, 'import-' . gmdate( 'Ymd-His' ) . '.csv' );
		$path = trailingslashit( $dir ) . $name;
		if ( ! move_uploaded_file( (string) $file['tmp_name'], $path ) ) {
			return new WP_Error( 'estat_move_failed', __( 'We could not save the uploaded file.', 'estat-os' ) );
		}
		return $path;
	}
}
