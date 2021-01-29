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

namespace LatteTools\Twiggy\Node;

use LatteTools\Twiggy\Compiler;

/**
 * Represents a block node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class BlockNode extends Node
{
	public function __construct(string $name, Node $body, int $lineno, Node $filter = null)
	{
		parent::__construct(['body' => $body], ['name' => $name, 'filter' => $filter], $lineno);
	}


	public function compile(Compiler $compiler): void
	{
		$name = $this->getAttribute('name');
		$filter = $this->getAttribute('filter');

		$compiler
			->raw('{block')
			->raw($name ? " $name" : '');

		if ($filter) {
			$filter->setAttribute('is_topmost', true);
			$compiler
				->subcompile($filter);
		}

		$compiler
			->raw('}')
			->subcompile($this->getNode('body'))
			->raw('{/block}')
		;
	}
}
