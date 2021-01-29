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
use LatteTools\Twiggy\Node\Expression\ArrayExpression;

/**
 * Represents a nested "with" scope.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class WithNode extends Node
{
	public function __construct(Node $body, ?Node $variables, bool $only, int $lineno, string $tag = null)
	{
		$nodes = ['body' => $body];
		if ($variables !== null) {
			$nodes['variables'] = $variables;
		}

		parent::__construct($nodes, ['only' => $only], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$parentContextName = $compiler->getVarName();

		$compiler->raw('{block}');

		if ($this->hasNode('variables')) {
			$vars = $this->getNode('variables');
			if ($vars instanceof ArrayExpression) {
				$vars = $vars->getKeyValuePairs();
				if ($vars) {
					$compiler->raw("\n{var ");
					foreach ($vars as $id => $pair) {
						if ($id) {
							$compiler->raw(', ');
						}
						$compiler->subcompile($pair['key'])
							->raw(' = ')
							->subcompile($pair['value']);
					}
					$compiler->raw('}');
				}
			} else {
				$compiler
					->raw("\n{var ")
					->subcompile($vars)
					->raw('}')
				;
			}

			if ($this->getAttribute('only')) {
				$compiler->raw("{* WARNING: 'only' is not supported *}");
			}
		}

		$compiler
			->subcompile($this->getNode('body'))
			->raw('{/block}')
		;
	}
}
