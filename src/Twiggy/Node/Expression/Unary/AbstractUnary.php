<?php
declare(strict_types=1);

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 * (c) Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LatteTools\Twiggy\Node\Expression\Unary;

use LatteTools\Twiggy\Compiler;
use LatteTools\Twiggy\Node\Expression\AbstractExpression;
use LatteTools\Twiggy\Node\Node;

abstract class AbstractUnary extends AbstractExpression
{
	public function __construct(Node $node, int $lineno)
	{
		parent::__construct(['node' => $node], [], $lineno);
	}


	public function compile(Compiler $compiler): void
	{
		$this->operator($compiler);
		$compiler->subcompile($this->getNode('node'));
	}


	abstract public function operator(Compiler $compiler): Compiler;
}
