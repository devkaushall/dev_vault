<?php
/** Thin form/input validation adapter. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Forms;

final class SubmissionValidator {
	/** @param array<string,mixed> $input */
	public function validate( array $input ): Submission|\WP_Error {
		return Submission::fromArray( $input );
	}
}
