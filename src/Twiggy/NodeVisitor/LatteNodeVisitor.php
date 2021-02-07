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

namespace LatteTools\Twiggy\NodeVisitor;

use LatteTools\Twiggy\Environment;
use LatteTools\Twiggy\Node\Expression\ArrayExpression;
use LatteTools\Twiggy\Node\Expression\ConditionalExpression;
use LatteTools\Twiggy\Node\Expression\ConstantExpression;
use LatteTools\Twiggy\Node\Expression\FilterExpression;
use LatteTools\Twiggy\Node\Expression\FunctionExpression;
use LatteTools\Twiggy\Node\Expression\MethodCallExpression;
use LatteTools\Twiggy\Node\Node;
use LatteTools\Twiggy\Node\PrintNode;

final class LatteNodeVisitor implements NodeVisitorInterface
{
	public function enterNode(Node $node, Environment $env): Node
	{
		return $node;
	}


	public function leaveNode(Node $node, Environment $env): ?Node
	{
		if ($node instanceof FilterExpression) {
			$name = $node->getNode('filter')->getAttribute('value');

			// removed |escape filter
			if (in_array($name, ['escape', 'e'], true)) {
				return $node->getNode('node');
			}

			// removed |filter with function()
			return $this->filterToFunction($node);
		}

		if ($node instanceof FunctionExpression) {
			$name = $node->getAttribute('name');

			// html_classes
			if ($name === 'html_classes') {
				return $this->functionHtmlClasses($node);
			}
		}

		if ($node instanceof PrintNode) {
			$expr = $node->getNode('expr');

			// remove extra ()
			$expr->setAttribute('is_topmost', true);

			// {= include() } -> {include ...}
			if (($expr instanceof FunctionExpression && $expr->getAttribute('name') === 'include')
				|| $expr instanceof MethodCallExpression
			) {
				return $expr;
			}
		}

		return $node;
	}


	public function getPriority(): int
	{
		return 255;
	}


	private function filterToFunction(FilterExpression $node): Node
	{
		static $funcs = [
			'reduce' => 'array_reduce',
			'merge' => 'array_merge',
			'map' => 'array_map',
			'filter' => 'array_filter',
			'column' => 'array_column',
			'keys' => 'array_keys',
			'json_encode' => 'json_encode',
		];

		$name = $node->getNode('filter')->getAttribute('value');
		if (!isset($funcs[$name])) {
			return $node;
		}

		$arguments = array_merge([$node->getNode('node')], iterator_to_array($node->getNode('arguments')));
		if ($name === 'map') {
			$arguments = array_reverse($arguments);
		}
		return new FunctionExpression($funcs[$name], new Node($arguments), $node->getTemplateLine());
	}


	private function functionHtmlClasses(FunctionExpression $node): FunctionExpression
	{
		$res = [];
		$arguments = $node->getNode('arguments');
		foreach ($arguments as $argument) {
			if ($argument instanceof ArrayExpression) {
				foreach ($argument->getKeyValuePairs() as ['key' => $key, 'value' => $value]) {
					$res[] = new ConditionalExpression(
						$value,
						$key,
						new ConstantExpression(null, $argument->getTemplateLine()),
						$argument->getTemplateLine()
					);
				}
			} else {
				$res[] = $argument;
			}
		}
		$arguments->setNodes($res);
		return $node;
	}
}
