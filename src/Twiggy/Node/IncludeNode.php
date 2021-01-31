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
use LatteTools\Twiggy\Node\Expression\AbstractExpression;
use LatteTools\Twiggy\Node\Expression\ArrayExpression;

/**
 * Represents an include node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class IncludeNode extends Node implements NodeOutputInterface
{
	public function __construct(
		AbstractExpression $expr,
		?AbstractExpression $variables,
		bool $only,
		bool $ignoreMissing,
		int $lineno,
		string $tag = null
	) {
		$nodes = ['expr' => $expr];
		if ($variables !== null) {
			$nodes['variables'] = $variables;
		}

		parent::__construct($nodes, ['only' => (bool) $only, 'ignore_missing' => (bool) $ignoreMissing], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		if ($this->getAttribute('ignore_missing')) {
			$compiler->raw("{try}\n");
			$this->addGetTemplate($compiler);
			$compiler->raw("{/try}\n");
			return;
		}

		$this->addGetTemplate($compiler);
	}


	protected function addGetTemplate(Compiler $compiler)
	{
		$compiler
			->raw($this->hasAttribute('sandbox') ? '{sandbox ' : '{include ')
			->filename($this->getNode('expr'));
		$this->addTemplateArguments($compiler);
		$compiler
			->raw('}')
		;
	}


	protected function addTemplateArguments(Compiler $compiler)
	{
		if (!$this->hasNode('variables')) {
			return;
		}

		$compiler->raw(', ');
		$vars = $this->getNode('variables');
		if ($vars instanceof ArrayExpression) {
			$vars->setAttribute('as_arguments', true);
		} else {
			$compiler->raw('...');
		}
		$compiler->subcompile($vars);
	}
}
