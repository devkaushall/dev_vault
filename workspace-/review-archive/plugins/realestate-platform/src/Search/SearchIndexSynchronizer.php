<?php
/** WordPress lifecycle adapter for search indexing. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Search;
final class SearchIndexSynchronizer {
	public function __construct( private SearchIndexWriter $writer ) {}
	public function register(): void {
		add_action( 'save_post_property', array( $this, 'saved' ), 100, 3 );
		add_action( 'set_object_terms', array( $this, 'termsChanged' ), 100, 6 );
		add_action( 'added_post_meta', array( $this, 'metaChanged' ), 100, 4 );
		add_action( 'updated_post_meta', array( $this, 'metaChanged' ), 100, 4 );
		add_action( 'deleted_post_meta', array( $this, 'metaChanged' ), 100, 4 );
		add_action( 'before_delete_post', array( $this, 'deleted' ), 10, 2 );
		add_action( 'trashed_post', array( $this, 'removed' ) );
	}
	public function saved( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $post, $update );
		if ( ! wp_is_post_revision( $post_id ) && ! wp_is_post_autosave( $post_id ) ) {
			$this->writer->synchronize( $post_id ); }
	}
	/** @param array<int|string> $terms @param list<int> $term_taxonomy_ids */
	public function termsChanged( int $object_id, array|string $terms, array $term_taxonomy_ids, string $taxonomy, bool $append, array $old_term_taxonomy_ids ): void {
		unset( $terms, $term_taxonomy_ids, $append, $old_term_taxonomy_ids );
		if ( in_array( $taxonomy, SearchIndexWriter::TAXONOMIES, true ) && 'property' === get_post_type( $object_id ) ) {
			$this->writer->synchronize( $object_id ); }
	}
	public function metaChanged( int|array $meta_id, int $post_id, string $meta_key, mixed $value ): void {
		unset( $meta_id, $value );
		if ( str_starts_with( $meta_key, 'rep_' ) && 'property' === get_post_type( $post_id ) ) {
			$this->writer->synchronize( $post_id );
		}
	}

	public function deleted( int $post_id, \WP_Post $post ): void {
		if ( 'property' === $post->post_type ) {
			$this->writer->remove( $post_id ); } }
	public function removed( int $post_id ): void {
		if ( 'property' === get_post_type( $post_id ) ) {
			$this->writer->remove( $post_id ); } }
}
