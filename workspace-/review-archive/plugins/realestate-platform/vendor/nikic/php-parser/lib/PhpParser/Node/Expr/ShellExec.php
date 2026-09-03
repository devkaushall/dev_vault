<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node\Expr;
use PhpParser\Node\InterpolatedStringPart;

class ShellExec extends Expr {
	/** @var (Expr|InterpolatedStringPart)[] Interpolated string array */
	public array $parts;

	/**
	 * Constructs a shell exec (backtick) node.
	 *
	 * @param (Expr|InterpolatedStringPart)[] $parts Interpolated string array
	 * @param array<string, mixed> $attributes Additional attributes
	 */
	public function __construct( array $parts, array $attributes = array() ) {
		$this->attributes = $attributes;
		$this->parts      = $parts;
	}

	public function getSubNodeNames(): array {
		return array( 'parts' );
	}

	public function getType(): string {
		return 'Expr_ShellExec';
	}
}
