<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

class TraitUse extends Node\Stmt {
	/** @var Node\Name[] Traits */
	public array $traits;
	/** @var TraitUseAdaptation[] Adaptations */
	public array $adaptations;

	/**
	 * Constructs a trait use node.
	 *
	 * @param Node\Name[] $traits Traits
	 * @param TraitUseAdaptation[] $adaptations Adaptations
	 * @param array<string, mixed> $attributes Additional attributes
	 */
	public function __construct( array $traits, array $adaptations = array(), array $attributes = array() ) {
		$this->attributes  = $attributes;
		$this->traits      = $traits;
		$this->adaptations = $adaptations;
	}

	public function getSubNodeNames(): array {
		return array( 'traits', 'adaptations' );
	}

	public function getType(): string {
		return 'Stmt_TraitUse';
	}
}
