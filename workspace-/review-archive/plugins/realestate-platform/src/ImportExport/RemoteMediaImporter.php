<?php
/**
 * Opt-in, bounded remote media sideloader.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);

namespace Mayfair\RealEstatePlatform\ImportExport;

use Mayfair\RealEstatePlatform\Security\Security;

final class RemoteMediaImporter {
	public const MAX_BYTES = 8 * 1024 * 1024;

	/** @var list<string> */
	private const MIME_TYPES = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'application/pdf',
	);

	public function validate( string $url ): string|\WP_Error {
		return Security::validateRemoteUrl( $url );
	}

	public function import( string $url, int $parent_id ): int|\WP_Error {
		$clean = $this->validate( $url );
		if ( is_wp_error( $clean ) ) {
			return $this->error( 'remote_media_not_verified', 'Remote media is NOT VERIFIED and was not downloaded.' );
		}
		$response = wp_remote_get(
			$clean,
			array(
				'timeout'             => 5,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_BYTES + 1,
				'reject_unsafe_urls'  => true,
				'headers'             => array( 'Accept' => implode( ',', self::MIME_TYPES ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $this->error( 'remote_media_not_verified', 'Remote media is NOT VERIFIED because the request failed.' );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return $this->error( 'remote_media_not_verified', 'Remote media is NOT VERIFIED because the server did not return HTTP 200.' );
		}
		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || '' === $body || strlen( $body ) > self::MAX_BYTES ) {
			return $this->error( 'remote_media_too_large', 'Remote media exceeds the bounded size limit.' );
		}
		$mime = strtolower( trim( (string) wp_remote_retrieve_header( $response, 'content-type' ) ) );
		$mime = (string) strtok( $mime, ';' );
		if ( ! in_array( $mime, self::MIME_TYPES, true ) ) {
			return $this->error( 'remote_media_type_not_allowed', 'The remote media MIME type is not allowed.' );
		}
		$path     = (string) wp_parse_url( $clean, PHP_URL_PATH );
		$filename = sanitize_file_name( wp_basename( $path ) );
		if ( '' === $filename ) {
			$filename = 'realestate-media-' . substr( hash( 'sha256', $clean ), 0, 16 ) . $this->extension( $mime );
		}
		$tmp = wp_tempnam( $filename );
		if ( ! is_string( $tmp ) || '' === $tmp || false === file_put_contents( $tmp, $body, LOCK_EX ) ) {
			return $this->error( 'remote_media_unavailable', 'The remote media could not be staged safely.' );
		}
		if ( function_exists( 'finfo_open' ) ) {
			$finfo    = finfo_open( FILEINFO_MIME_TYPE );
			$detected = false !== $finfo ? finfo_file( $finfo, $tmp ) : false;
			if ( false !== $finfo ) {
				finfo_close( $finfo );
			}
			if ( ! is_string( $detected ) || ! in_array( $detected, self::MIME_TYPES, true ) || $detected !== $mime ) {
				wp_delete_file( $tmp );
				return $this->error( 'remote_media_type_not_allowed', 'The remote media content type could not be verified.' );
			}
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$file = array(
			'name'     => $filename,
			'type'     => $mime,
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => strlen( $body ),
		);
		$id   = media_handle_sideload( $file, $parent_id );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return $id;
		}
		return (int) $id;
	}

	private function extension( string $mime ): string {
		return match ( $mime ) {
			'image/jpeg' => '.jpg',
			'image/png'  => '.png',
			'image/gif'  => '.gif',
			'image/webp' => '.webp',
			default      => '.pdf',
		};
	}

	private function error( string $code, string $message ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => 400 ) );
	}
}
