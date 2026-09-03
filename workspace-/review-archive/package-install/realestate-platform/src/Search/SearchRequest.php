<?php
/**
 * Shared transport validation and execution for public search adapters.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\Search;

final class SearchRequest {
	public function __construct( private SearchEngine $engine ) {}

	/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
	public function execute( array $input ): array|\WP_Error {
		try {
			$criteria = SearchCriteria::fromArray( $input );
			$this->validateRelationships( $criteria );
			$response                    = $this->engine->execute( $criteria )->toArray();
			$response['applied_filters'] = $criteria->canonical();
			return $response;
		} catch ( \InvalidArgumentException $exception ) {
			return new \WP_Error(
				'realestate_platform_invalid_search',
				$exception->getMessage(),
				array( 'status' => 400 )
			);
		}
	}

	private function validateRelationships( SearchCriteria $criteria ): void {
		foreach ( $criteria->terms as $taxonomy => $ids ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				throw new \InvalidArgumentException( 'Invalid taxonomy.' );
			}
			foreach ( $ids as $id ) {
				$term = get_term( $id, $taxonomy );
				if ( ! $term instanceof \WP_Term ) {
					throw new \InvalidArgumentException( "Invalid {$taxonomy}." );
				}
			}
		}
		if ( isset( $criteria->filters['project'] ) ) {
			$project = get_post( (int) $criteria->filters['project'] );
			if ( ! $project instanceof \WP_Post || 'project' !== $project->post_type || 'publish' !== $project->post_status ) {
				throw new \InvalidArgumentException( 'Invalid project.' );
			}
		}
	}
}
