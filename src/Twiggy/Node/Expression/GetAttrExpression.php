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

class GetAttrExpression extends AbstractExpression
{
	public function __construct(
		AbstractExpression $node,
		AbstractExpression $attribute,
		?AbstractExpression $arguments,
		string $type,
		int $lineno
	) {
		$nodes = ['node' => $node, 'attribute' => $attribute];
		if ($arguments !== null) {
			$nodes['arguments'] = $arguments;
		}

		parent::__construct($nodes, ['type' => $type, 'is_defined_test' => false, 'ignore_strict_check' => false, 'optimizable' => true], $lineno);
	}


	public function compile(Compiler $compiler): void
	{
		$type = $this->getAttribute('type');
		$attr = $this->getNode('attribute');

		if ($this->getAttribute('is_defined_test')) {
			if ($type === 'method') {
				$compiler->raw('true');
				return;
			}

			$compiler->raw('isset(');
		}

		$property = !$attr instanceof ConstantExpression || !is_int($attr->getAttribute('value'));

		$compiler->subcompile($this->getNode('node'))
			->raw($property ? '->' : '[');

		if ($attr instanceof ConstantExpression) {
			$compiler->raw($attr->getAttribute('value'));
		} else {
			$compiler
				->raw('{')
				->subcompile($this->getNode('attribute'))
				->raw('}')
			;
		}

		if (!$property) {
			$compiler->raw(']');
		}

		if ($this->getAttribute('is_defined_test')) {
			$compiler->raw(')');
		}

		if ($type === 'method') {
			$args = $this->getNode('arguments');
			$args->setAttribute('as_arguments', true);
			$compiler->raw('(')
				->subcompile($args)
				->raw(')');
		}
	}
}
