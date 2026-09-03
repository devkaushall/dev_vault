<?php
/** Batched search-index rebuild. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Search;
final class SearchIndexRebuilder {
	public function __construct( private SearchIndexWriter $writer ) {}
	/** @return array{processed:int,failed:int,batches:int} */
	public function rebuild( int $batch_size = 100, ?callable $progress = null ): array {
		if ( $batch_size < 1 || $batch_size > 1000 ) {
			throw new \InvalidArgumentException( 'Batch size must be between 1 and 1000.' );}
		global $wpdb;
		$last      = 0;
		$processed = 0;
		$failed    = 0;
		$batches   = 0;
		do {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='property' AND post_status='publish' AND ID>%d ORDER BY ID ASC LIMIT %d", $last, $batch_size ) );
			if ( $ids ) {
				++$batches;
				foreach ( $ids as $id ) {
					$last = (int) $id;
					$this->writer->synchronize( $last ) ? ++$processed : ++$failed;
				}if ( $progress ) {
					$progress( $processed, $failed );
				}
			}
		} while ( count( $ids ) === $batch_size );
		$properties_table = $wpdb->prefix . 'rep_search_properties';
		$terms_table      = $wpdb->prefix . 'rep_search_terms';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table identifiers only.
		$wpdb->query( "DELETE FROM {$terms_table} WHERE post_id NOT IN (SELECT post_id FROM {$properties_table})" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table identifiers only.
		$wpdb->query( "DELETE FROM {$properties_table} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts} WHERE post_type='property' AND post_status='publish')" );
		update_option(
			'realestate_platform_search_last_rebuild',
			array(
				'completed_at' => gmdate( 'c' ),
				'processed'    => $processed,
				'failed'       => $failed,
			),
			false
		);
		return compact( 'processed', 'failed', 'batches' );
	}
}
