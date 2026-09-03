<?php
/**
 * WordPress attachment relationship validation.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Media;

final class MediaService {
	public function validAttachment( int $attachment_id, ?string $mime_prefix = null ): bool {
		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}
		$mime = (string) get_post_mime_type( $attachment_id );
		return null === $mime_prefix || str_starts_with( $mime, $mime_prefix );
	}

	/** @param list<int> $attachment_ids
	 * @return list<int> */
	public function normalizeGallery( array $attachment_ids ): array {
		return array_values( array_unique( array_filter( array_map( 'absint', $attachment_ids ), fn( int $id ) => $this->validAttachment( $id, 'image/' ) ) ) );
	}
}
