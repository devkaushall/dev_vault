<?php
/** Validated contact submission DTO. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Forms;

use Mayfair\RealEstatePlatform\Security\StrictId;

final class Submission {
	public readonly string $name;
	public readonly string $email;
	public readonly string $phone;
	public readonly string $message;
	public readonly int $property_id;
	public readonly int $project_id;
	public readonly string $source;
	public readonly bool $consent;
	public readonly string $idempotency_key;

	private function __construct( string $name, string $email, string $phone, string $message, int $property_id, int $project_id, string $source, bool $consent, string $idempotency_key ) {
		$this->name            = $name;
		$this->email           = $email;
		$this->phone           = $phone;
		$this->message         = $message;
		$this->property_id     = $property_id;
		$this->project_id      = $project_id;
		$this->source          = $source;
		$this->consent         = $consent;
		$this->idempotency_key = $idempotency_key;
	}

	/** @param array<string,mixed> $input */
	public static function fromArray( array $input ): self|\WP_Error {
		$name = self::text( $input, 'name', 2, 120 );
		if ( is_wp_error( $name ) ) {
			return $name;
		}
		$email = $input['email'] ?? null;
		if ( ! is_string( $email ) || strlen( $email ) > 190 || ! is_email( $email ) ) {
			return self::error( 'invalid_email', 'A valid email address is required.' );
		}
		$email = sanitize_email( $email );
		$phone = self::optionalText( $input, 'phone', 64 );
		if ( is_wp_error( $phone ) ) {
			return $phone;
		}
		$message = self::optionalText( $input, 'message', 5000, true );
		if ( is_wp_error( $message ) ) {
			return $message;
		}
		$property_id = self::optionalId( $input, 'property_id' );
		if ( is_wp_error( $property_id ) ) {
			return $property_id;
		}
		$project_id = self::optionalId( $input, 'project_id' );
		if ( is_wp_error( $project_id ) ) {
			return $project_id;
		}
		$source = $input['source'] ?? 'rest';
		if ( ! is_string( $source ) || ! in_array( $source, array( 'rest', 'ajax', 'elementor', 'website', 'admin', 'mayfair', 'unknown' ), true ) ) {
			return self::error( 'invalid_source', 'The submission source is invalid.' );
		}
		$consent = self::boolean( $input['consent'] ?? null );
		if ( null === $consent || ! $consent ) {
			return self::error( 'consent_required', 'Explicit consent is required.' );
		}
		$honeypot = $input['website_url'] ?? '';
		if ( null !== $honeypot && ( ! is_string( $honeypot ) || '' !== trim( $honeypot ) ) ) {
			return self::error( 'spam_detected', 'The submission could not be accepted.' );
		}
		$idempotency_key = $input['idempotency_key'] ?? '';
		if ( ! is_string( $idempotency_key ) || strlen( $idempotency_key ) > 128 || ( '' !== $idempotency_key && ! preg_match( '/^[A-Za-z0-9._:-]+$/', $idempotency_key ) ) ) {
			return self::error( 'invalid_idempotency_key', 'The idempotency key is invalid.' );
		}
		return new self( $name, $email, $phone, $message, $property_id, $project_id, $source, true, $idempotency_key );
	}

	private static function text( array $input, string $key, int $minimum, int $maximum ): string|\WP_Error {
		$value = $input[ $key ] ?? null;
		if ( ! is_string( $value ) ) {
			return self::error( 'invalid_' . $key, 'A valid ' . $key . ' is required.' );
		}
		$clean = sanitize_text_field( $value );
		if ( strlen( $clean ) < $minimum || strlen( $clean ) > $maximum ) {
			return self::error( 'invalid_' . $key, 'The ' . $key . ' value is outside the permitted length.' );
		}
		return $clean;
	}

	private static function optionalText( array $input, string $key, int $maximum, bool $textarea = false ): string|\WP_Error {
		$value = $input[ $key ] ?? '';
		if ( ! is_string( $value ) || strlen( $value ) > $maximum ) {
			return self::error( 'invalid_' . $key, 'The ' . $key . ' value is invalid.' );
		}
		return $textarea ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}

	private static function optionalId( array $input, string $key ): int|\WP_Error {
		if ( ! array_key_exists( $key, $input ) || null === $input[ $key ] || '' === $input[ $key ] ) {
			return 0;
		}
		$id = StrictId::parse( $input[ $key ] );
		return $id > 0 ? $id : self::error( 'invalid_' . $key, 'The ' . $key . ' value is invalid.' );
	}

	private static function boolean( mixed $value ): ?bool {
		if ( true === $value || 1 === $value || '1' === $value || 'true' === $value ) {
			return true;
		}
		if ( false === $value || 0 === $value || '0' === $value || 'false' === $value ) {
			return false;
		}
		return null;
	}

	private static function error( string $code, string $message ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => 400 ) );
	}
}
