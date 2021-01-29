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
use LatteTools\Twiggy\Node\Node;

/**
 * Represents an arrow function.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ArrowFunctionExpression extends AbstractExpression
{
	public function __construct(AbstractExpression $expr, Node $names, $lineno, $tag = null)
	{
		parent::__construct(['expr' => $expr, 'names' => $names], [], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler
			->raw('fn(')
		;
		foreach ($this->getNode('names') as $i => $name) {
			if ($i) {
				$compiler->raw(', ');
			}

			$compiler
				->raw('$')
				->raw($name->getAttribute('name'))
			;
		}
		$compiler
			->raw(') => ')
		;
		$compiler
			->subcompile($this->getNode('expr'))
		;
	}
}
