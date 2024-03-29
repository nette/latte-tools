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
 * Represents an if node.
 */
class IfNode extends Node
{
	public function __construct(Node $tests, ?Node $else, int $lineno, string $tag = null)
	{
		$nodes = ['tests' => $tests];
		if ($else !== null) {
			$nodes['else'] = $else;
		}

		parent::__construct($nodes, [], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		for ($i = 0, $count = \count($this->getNode('tests')); $i < $count; $i += 2) {
			if ($i > 0) {
				$compiler
					->raw('{elseif ');
			} else {
				$compiler
					->raw('{if ');
			}

			$compiler
				->subcompile($this->getNode('tests')->getNode($i))
				->raw('}')
				->subcompile($this->getNode('tests')->getNode($i + 1));
		}

		if ($this->hasNode('else')) {
			$compiler
				->raw('{else}')
				->subcompile($this->getNode('else'));
		}

		$compiler
			->raw('{/if}');
	}
}
