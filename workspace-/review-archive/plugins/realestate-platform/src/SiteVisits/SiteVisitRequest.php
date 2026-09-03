<?php
/** Validated site-visit request DTO. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\SiteVisits;

use DateTimeImmutable;
use DateTimeZone;
use Mayfair\RealEstatePlatform\Forms\Submission;
use Mayfair\RealEstatePlatform\Forms\SubmissionValidator;
use Mayfair\RealEstatePlatform\Security\StrictId;

final class SiteVisitRequest {
	public readonly Submission $submission;
	public readonly int $lead_id;
	public readonly string $requested_start_at;
	public readonly string $requested_end_at;

	private function __construct( Submission $submission, int $lead_id, string $requested_start_at, string $requested_end_at ) {
		$this->submission         = $submission;
		$this->lead_id            = $lead_id;
		$this->requested_start_at = $requested_start_at;
		$this->requested_end_at   = $requested_end_at;
	}

	/** @param array<string,mixed> $input */
	public static function fromArray( array $input, SubmissionValidator $validator ): self|\WP_Error {
		if ( ! array_key_exists( 'property_id', $input ) ) {
			return self::error( 'property_required', 'A published Property is required.' );
		}
		$lead_id = 0;
		if ( array_key_exists( 'lead_id', $input ) && null !== $input['lead_id'] && '' !== $input['lead_id'] ) {
			$lead_id = StrictId::parse( $input['lead_id'] );
			if ( 0 === $lead_id ) {
				return self::error( 'invalid_lead_id', 'The lead ID is invalid.' );
			}
		}
		$submission = $validator->validate( $input );
		if ( is_wp_error( $submission ) ) {
			return $submission;
		}
		$start = $input['requested_start_at'] ?? null;
		$end   = $input['requested_end_at'] ?? null;
		if ( ! is_string( $start ) || '' === trim( $start ) ) {
			return self::error( 'invalid_visit_window', 'A requested start time is required.' );
		}
		$window = self::parseWindow( $start, is_string( $end ) && '' !== trim( $end ) ? $end : '' );
		if ( is_wp_error( $window ) ) {
			return $window;
		}
		return new self( $submission, $lead_id, $window['start'], $window['end'] );
	}

	/** @return array{start:string,end:string}|\WP_Error */
	public static function parseWindow( string $start, string $end = '' ): array|\WP_Error {
		$start_date = self::parseDate( $start );
		if ( is_wp_error( $start_date ) ) {
			return $start_date;
		}
		$end_date = '' === trim( $end ) ? $start_date->modify( '+1 hour' ) : self::parseDate( $end );
		if ( is_wp_error( $end_date ) ) {
			return $end_date;
		}
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		if ( $start_date <= $now || $end_date <= $start_date || $end_date->getTimestamp() - $start_date->getTimestamp() > 43200 ) {
			return self::error( 'invalid_visit_window', 'The requested visit window must be a future interval of twelve hours or less.' );
		}
		return array(
			'start' => $start_date->format( 'Y-m-d H:i:s' ),
			'end'   => $end_date->format( 'Y-m-d H:i:s' ),
		);
	}

	private static function parseDate( string $value ): DateTimeImmutable|\WP_Error {
		$trimmed = trim( $value );
		$zone    = new DateTimeZone( 'UTC' );
		$date    = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $trimmed, $zone );
		if ( false === $date || $date->format( 'Y-m-d H:i:s' ) !== $trimmed ) {
			try {
				$date = new DateTimeImmutable( $trimmed, $zone );
			} catch ( \Exception $exception ) {
				return self::error( 'invalid_visit_window', 'The visit time is invalid.' );
			}
			$date = $date->setTimezone( $zone );
		}
		return $date;
	}

	private static function error( string $code, string $message ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => 400 ) );
	}
}
