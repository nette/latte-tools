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

namespace LatteTools\Twiggy\Node\Expression\Test;

use LatteTools\Twiggy\Compiler;
use LatteTools\Twiggy\Error\SyntaxError;
use LatteTools\Twiggy\Node\Expression\ArrayExpression;
use LatteTools\Twiggy\Node\Expression\BlockReferenceExpression;
use LatteTools\Twiggy\Node\Expression\ConstantExpression;
use LatteTools\Twiggy\Node\Expression\FunctionExpression;
use LatteTools\Twiggy\Node\Expression\GetAttrExpression;
use LatteTools\Twiggy\Node\Expression\MethodCallExpression;
use LatteTools\Twiggy\Node\Expression\NameExpression;
use LatteTools\Twiggy\Node\Expression\TestExpression;
use LatteTools\Twiggy\Node\Node;

/**
 * Checks if a variable is defined in the current context.
 *
 *    {# defined works with variable names and variable attributes #}
 *    {% if foo is defined %}
 *        {# ... #}
 *    {% endif %}
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DefinedTest extends TestExpression
{
	public function __construct(Node $node, string $name, ?Node $arguments, int $lineno)
	{
		if ($node instanceof NameExpression) {
			$node->setAttribute('is_defined_test', true);
		} elseif ($node instanceof GetAttrExpression) {
			$node->setAttribute('is_defined_test', true);
			$this->changeIgnoreStrictCheck($node);
		} elseif ($node instanceof BlockReferenceExpression) {
			$node->setAttribute('is_defined_test', true);
		} elseif ($node instanceof FunctionExpression && $node->getAttribute('name') === 'constant') {
			$node->setAttribute('is_defined_test', true);
		} elseif ($node instanceof ConstantExpression || $node instanceof ArrayExpression) {
			$node = new ConstantExpression(true, $node->getTemplateLine());
		} elseif ($node instanceof MethodCallExpression) {
			$node->setAttribute('is_defined_test', true);
		} else {
			throw new SyntaxError('The "defined" test only works with simple variables.', $lineno);
		}

		parent::__construct($node, $name, $arguments, $lineno);
	}


	private function changeIgnoreStrictCheck(GetAttrExpression $node)
	{
		$node->setAttribute('optimizable', false);
		$node->setAttribute('ignore_strict_check', true);

		if ($node->getNode('node') instanceof GetAttrExpression) {
			$this->changeIgnoreStrictCheck($node->getNode('node'));
		}
	}


	public function compile(Compiler $compiler): void
	{
		$compiler->subcompile($this->getNode('node'));
	}
}
