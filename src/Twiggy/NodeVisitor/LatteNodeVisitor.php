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
		// removed |escape filter
		if (
			$node instanceof FilterExpression
			&& in_array($node->getNode('filter')->getAttribute('value'), ['escape', 'e'], true)
		) {
			return $node->getNode('node');
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
}
