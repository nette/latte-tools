<?php
declare(strict_types=1);

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LatteTools\Twiggy\Node;

use LatteTools\Twiggy\Compiler;
use LatteTools\Twiggy\Node\Expression\AbstractExpression;

/**
 * Represents an import node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ImportNode extends Node
{
	public function __construct(
		AbstractExpression $expr,
		AbstractExpression $var,
		int $lineno,
		string $tag = null,
		bool $global = true
	) {
		parent::__construct(['expr' => $expr, 'var' => $var], ['global' => $global], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler
			->raw('{import ')
			->subcompile($this->getNode('expr'))
			->raw('}');
	}
}
