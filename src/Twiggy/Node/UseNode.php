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

class UseNode extends Node
{
	public function __construct(AbstractExpression $expr, int $lineno, string $tag = null)
	{
		parent::__construct(['expr' => $expr], [], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler
			->raw('{import ')
			->filename($this->getNode('expr'))
			->raw('}')
		;
	}
}
