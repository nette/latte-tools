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

namespace LatteTools\Twiggy\Node\Expression\Filter;

use LatteTools\Twiggy\Compiler;
use LatteTools\Twiggy\Node\Expression\ConstantExpression;
use LatteTools\Twiggy\Node\Expression\FilterExpression;
use LatteTools\Twiggy\Node\Expression\GetAttrExpression;
use LatteTools\Twiggy\Node\Expression\NameExpression;
use LatteTools\Twiggy\Node\Expression\NullCoalesceExpression;
use LatteTools\Twiggy\Node\Node;

/**
 * Returns the value or the default value when it is undefined or empty.
 *
 *  {{ var.foo|default('foo item on var is not defined') }}
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DefaultFilter extends FilterExpression
{
	public function __construct(
		Node $node,
		ConstantExpression $filterName,
		Node $arguments,
		int $lineno,
		string $tag = null
	) {
		$default = new FilterExpression($node, new ConstantExpression('default', $node->getTemplateLine()), $arguments, $node->getTemplateLine());

		if (
			$filterName->getAttribute('value') === 'default'
			&& (
				$node instanceof NameExpression
				|| $node instanceof GetAttrExpression
			)
		) {
			$false = \count($arguments)
				? $arguments->getNode(0)
				: new ConstantExpression('', $node->getTemplateLine());

			$node = new NullCoalesceExpression($node, $false, $lineno);
		} else {
			$node = $default;
		}

		parent::__construct($node, $filterName, $arguments, $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler
			->raw($this->hasAttribute('is_topmost') ? '' : '(')
			->subcompile($this->getNode('node'))
			->raw($this->hasAttribute('is_topmost') ? '' : ')')
		;
	}
}
