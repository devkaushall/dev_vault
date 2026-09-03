<?php
/** Canonical request/inquiry application facade. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Requests;

use Mayfair\RealEstatePlatform\Forms\SubmissionValidator;
use Mayfair\RealEstatePlatform\Leads\LeadService;

final class RequestService {
	public function __construct( private LeadService $leads, private SubmissionValidator $validator ) {}

	/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
	public function submit( array $input, int $actor_id, string $source = 'rest' ): array|\WP_Error {
		if ( ! isset( $input['source'] ) ) {
			$input['source'] = $source;
		}
		$submission = $this->validator->validate( $input );
		if ( is_wp_error( $submission ) ) {
			return $submission;
		}
		$created = $this->leads->create( $submission, $actor_id, 'inquiry' );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		return array(
			'accepted'   => true,
			'status'     => 'received',
			'lead_id'    => $created['id'],
			'request_id' => $created['request_id'],
			'duplicate'  => $created['duplicate'],
		);
	}

	/** @return array<string,mixed>|\WP_Error */
	public function get( int $request_id, int $actor_id ): array|\WP_Error {
		return $this->leads->getRequest( $request_id, $actor_id );
	}
}
