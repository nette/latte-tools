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

namespace LatteTools\Twiggy\Node\Expression;

use LatteTools\Twiggy\Compiler;
use LatteTools\Twiggy\Node\Expression\Test\DefinedTest;
use LatteTools\Twiggy\Node\Node;

class NullCoalesceExpression extends AbstractExpression
{
	public function __construct(Node $left, Node $right, int $lineno)
	{
		$test = new DefinedTest(clone $left, 'defined', new Node, $left->getTemplateLine());
		parent::__construct(['left' => $left, 'right' => $right], [], $lineno);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler
			->subcompile($this->getNode('left'))
			->raw(' ?? ')
			->subcompile($this->getNode('right'))
		;
	}
}
