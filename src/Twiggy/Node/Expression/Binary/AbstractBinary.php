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

namespace LatteTools\Twiggy\Node\Expression\Binary;

use LatteTools\Twiggy\Compiler;
use LatteTools\Twiggy\Node\Expression\AbstractExpression;
use LatteTools\Twiggy\Node\Node;

abstract class AbstractBinary extends AbstractExpression
{
	public function __construct(Node $left, Node $right, int $lineno)
	{
		parent::__construct(['left' => $left, 'right' => $right], [], $lineno);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler
			->raw($this->hasAttribute('is_topmost') ? '' : '(')
			->subcompile($this->getNode('left'))
			->raw(' ')
		;
		$this->operator($compiler);
		$compiler
			->raw(' ')
			->subcompile($this->getNode('right'))
			->raw($this->hasAttribute('is_topmost') ? '' : ')')
		;
	}


	abstract public function operator(Compiler $compiler): Compiler;
}
