<?php
/**
 * RealEstate Platform foundation component.
 *
 * @package RealEstatePlatform
 */

declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Core;

use LogicException;
final class ServiceRegistry {
	/** @var array<string, callable(self): object> */
	private array $factories = array();
	/** @var array<string, object> */
	private array $instances = array();
	public function set( string $id, callable|object $service ): void {
		if ( isset( $this->factories[ $id ] ) || isset( $this->instances[ $id ] ) ) {
			throw new LogicException( "Service already registered: {$id}" );
		} if ( is_object( $service ) && ! ( $service instanceof \Closure ) ) {
			$this->instances[ $id ] = $service;
		} else {
			$this->factories[ $id ] = $service;
		}
	} public function has( string $id ): bool {
		return isset( $this->instances[ $id ] ) || isset( $this->factories[ $id ] );
	} public function get( string $id ): object {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		} if ( ! isset( $this->factories[ $id ] ) ) {
			throw new LogicException( "Unknown service: {$id}" );
		} $object = ( $this->factories[ $id ] )( $this );
		if ( ! is_object( $object ) ) {
			throw new LogicException( "Factory did not return object: {$id}" );
		}
		$this->instances[ $id ] = $object;
		return $object;
	} public function optional( string $id ): ?object {
		return $this->has( $id ) ? $this->get( $id ) : null;}
}
