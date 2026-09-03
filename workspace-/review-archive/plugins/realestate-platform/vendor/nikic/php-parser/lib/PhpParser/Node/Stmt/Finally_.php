<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

class Finally_ extends Node\Stmt {
	/** @var Node\Stmt[] Statements */
	public array $stmts;

	/**
	 * Constructs a finally node.
	 *
	 * @param Node\Stmt[] $stmts Statements
	 * @param array<string, mixed> $attributes Additional attributes
	 */
	public function __construct( array $stmts = array(), array $attributes = array() ) {
		$this->attributes = $attributes;
		$this->stmts      = $stmts;
	}

	public function getSubNodeNames(): array {
		return array( 'stmts' );
	}

	public function getType(): string {
		return 'Stmt_Finally';
	}
}
