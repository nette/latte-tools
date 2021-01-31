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

namespace LatteTools\Twiggy\Node\Expression;

use LatteTools\Twiggy\Compiler;
use LatteTools\Twiggy\Node\Node;

class FilterExpression extends CallExpression
{
	public function __construct(
		Node $node,
		ConstantExpression $filterName,
		Node $arguments,
		int $lineno,
		string $tag = null
	) {
		parent::__construct(['node' => $node, 'filter' => $filterName, 'arguments' => $arguments], [], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$name = $this->getNode('filter')->getAttribute('value');
		$topMost = $this->hasAttribute('is_topmost');
		$node = $this->getNode('node');
		if ($topMost) {
			$node->setAttribute('is_topmost', true);
		}

		$compiler
			->raw($topMost ? '' : '(')
			->subcompile($node);

		$compiler->raw('|');
		$compiler->raw($compiler->getEnvironment()->getLatteFilter($name));

		if ($this->hasNode('arguments')) {
			$arguments = $this->getNode('arguments');
			$first = true;
			foreach ($arguments as $name => $node) {
				$compiler->raw($first ? ':' : ', ');
				if (is_string($name)) {
					$compiler->raw($name . ': ');
				}
				$compiler->subcompile($node);
				$first = false;
			}
		}

		$compiler
			->raw($topMost ? '' : ')');
	}
}
