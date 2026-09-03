<?php
/** Optional Elementor Pro Form Action bridge into the canonical Lead Engine. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Elementor;

use Mayfair\RealEstatePlatform\Requests\RequestService;
use Mayfair\RealEstatePlatform\Security\PublicSubmissionRateLimiter;

final class LeadFormAction extends \ElementorPro\Modules\Forms\Classes\Action_Base {
	private const FIELD_MAP = array(
		'name'            => 'name',
		'full_name'       => 'name',
		'email'           => 'email',
		'phone'           => 'phone',
		'message'         => 'message',
		'property_id'     => 'property_id',
		'project_id'      => 'project_id',
		'consent'         => 'consent',
		'website_url'     => 'website_url',
		'idempotency_key' => 'idempotency_key',
	);

	public function __construct( private RequestService $requests, private PublicSubmissionRateLimiter $rate ) {}

	public function get_name(): string {
		return 'realestate_platform_lead';
	}

	public function get_label(): string {
		return 'RealEstate Platform Lead';
	}

	public function register_settings_section( $widget ): void {}

	public function on_export( $element ): array {
		return $element;
	}

	public function run( $record, $ajax_handler ): void {
		if ( ! $this->rate->allow( 'elementor' ) ) {
			$this->error( $ajax_handler );
			return;
		}
		$input  = array( 'source' => 'elementor' );
		$fields = is_object( $record ) ? $record->get( 'fields' ) : array();
		if ( ! is_array( $fields ) ) {
			$this->error( $ajax_handler );
			return;
		}
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$field_id = sanitize_key( (string) ( $field['id'] ?? '' ) );
			if ( ! isset( self::FIELD_MAP[ $field_id ] ) ) {
				continue;
			}
			$target = self::FIELD_MAP[ $field_id ];
			$value  = $this->fieldValue( $field['value'] ?? '' );
			if ( 'consent' === $target && is_string( $value ) ) {
				$value = in_array( strtolower( trim( $value ) ), array( '1', 'true', 'on', 'yes' ), true );
			}
			$input[ $target ] = $value;
		}
		$result = $this->requests->submit( $input, get_current_user_id(), 'elementor' );
		if ( is_wp_error( $result ) ) {
			$this->error( $ajax_handler );
			return;
		}
		if ( is_object( $ajax_handler ) && method_exists( $ajax_handler, 'add_success_message' ) ) {
			$ajax_handler->add_success_message( 'Your request has been received.' );
		}
	}

	private function fieldValue( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( static fn( mixed $item ): string => (string) $item, $value ) );
		}
		return $value;
	}

	private function error( mixed $ajax_handler ): void {
		if ( is_object( $ajax_handler ) && method_exists( $ajax_handler, 'add_error_message' ) ) {
			$ajax_handler->add_error_message( 'The request could not be submitted.' );
		}
	}
}
