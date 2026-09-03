<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

class Enum_ extends ClassLike {
	/** @var null|Node\Identifier Scalar Type */
	public ?Node $scalarType;
	/** @var Node\Name[] Names of implemented interfaces */
	public array $implements;

	/**
	 * @param string|Node\Identifier|null $name Name
	 * @param array{
	 *     scalarType?: Node\Identifier|null,
	 *     implements?: Node\Name[],
	 *     stmts?: Node\Stmt[],
	 *     attrGroups?: Node\AttributeGroup[],
	 * } $subNodes Array of the following optional subnodes:
	 *             'scalarType'  => null    : Scalar type
	 *             'implements'  => array() : Names of implemented interfaces
	 *             'stmts'       => array() : Statements
	 *             'attrGroups'  => array() : PHP attribute groups
	 * @param array<string, mixed> $attributes Additional attributes
	 */
	public function __construct( $name, array $subNodes = array(), array $attributes = array() ) {
		$this->name       = \is_string( $name ) ? new Node\Identifier( $name ) : $name;
		$this->scalarType = $subNodes['scalarType'] ?? null;
		$this->implements = $subNodes['implements'] ?? array();
		$this->stmts      = $subNodes['stmts'] ?? array();
		$this->attrGroups = $subNodes['attrGroups'] ?? array();

		parent::__construct( $attributes );
	}

	public function getSubNodeNames(): array {
		return array( 'attrGroups', 'name', 'scalarType', 'implements', 'stmts' );
	}

	public function getType(): string {
		return 'Stmt_Enum';
	}
}
