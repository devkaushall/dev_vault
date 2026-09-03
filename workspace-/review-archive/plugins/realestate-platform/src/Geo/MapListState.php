<?php
/** Provider-neutral immutable map/list synchronization state. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Geo;
final class MapListState implements \JsonSerializable {
	/** @param array<string,mixed> $criteria @param list<int> $result_ids */
	public function __construct( public readonly array $criteria = array(), public readonly array $result_ids = array(), public readonly ?int $selected_id = null, public readonly int $revision = 0, private readonly string $last_event = '' ) {}
	/** @param array<string,mixed> $payload */
	public function transition( string $event, array $payload ): self {
		$fingerprint = hash( 'sha256', $event . '|' . wp_json_encode( $payload ) );
		if ( $fingerprint === $this->last_event ) {
			return $this;
		}
		return match ( $event ) {
			'criteria', 'viewport'=>new self( $payload, $this->result_ids, null, $this->revision + 1, $fingerprint ),
			'results'=>new self( $this->criteria, array_values( array_unique( array_map( 'absint', $payload['ids'] ?? array() ) ) ), $this->selected_id, $this->revision + 1, $fingerprint ),
			'select'=>new self( $this->criteria, $this->result_ids, in_array( absint( $payload['id'] ?? 0 ), $this->result_ids, true ) ? absint( $payload['id'] ) : null, $this->revision + 1, $fingerprint ),
			default=>$this,
		};
	}
	public function jsonSerialize(): array {
		return array(
			'criteria'    => $this->criteria,
			'result_ids'  => $this->result_ids,
			'selected_id' => $this->selected_id,
			'revision'    => $this->revision,
		);}
}
