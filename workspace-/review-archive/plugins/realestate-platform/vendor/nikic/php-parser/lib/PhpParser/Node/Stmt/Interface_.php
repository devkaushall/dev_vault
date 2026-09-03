<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

class Interface_ extends ClassLike {
	/** @var Node\Name[] Extended interfaces */
	public array $extends;

	/**
	 * Constructs a class node.
	 *
	 * @param string|Node\Identifier $name Name
	 * @param array{
	 *     extends?: Node\Name[],
	 *     stmts?: Node\Stmt[],
	 *     attrGroups?: Node\AttributeGroup[],
	 * } $subNodes Array of the following optional subnodes:
	 *             'extends'    => array(): Name of extended interfaces
	 *             'stmts'      => array(): Statements
	 *             'attrGroups' => array(): PHP attribute groups
	 * @param array<string, mixed> $attributes Additional attributes
	 */
	public function __construct( $name, array $subNodes = array(), array $attributes = array() ) {
		$this->attributes = $attributes;
		$this->name       = \is_string( $name ) ? new Node\Identifier( $name ) : $name;
		$this->extends    = $subNodes['extends'] ?? array();
		$this->stmts      = $subNodes['stmts'] ?? array();
		$this->attrGroups = $subNodes['attrGroups'] ?? array();
	}

	public function getSubNodeNames(): array {
		return array( 'attrGroups', 'name', 'extends', 'stmts' );
	}

	public function getType(): string {
		return 'Stmt_Interface';
	}
}
