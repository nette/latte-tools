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
use LatteTools\Twiggy\Node\Expression\AssignNameExpression;
use LatteTools\Twiggy\Node\Expression\ConstantExpression;
use LatteTools\Twiggy\Node\Expression\GetAttrExpression;
use LatteTools\Twiggy\Node\Expression\MethodCallExpression;
use LatteTools\Twiggy\Node\Expression\NameExpression;
use LatteTools\Twiggy\Node\ImportNode;
use LatteTools\Twiggy\Node\ModuleNode;
use LatteTools\Twiggy\Node\Node;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
final class MacroAutoImportNodeVisitor implements NodeVisitorInterface
{
	private $inAModule = false;
	private $hasMacroCalls = false;


	public function enterNode(Node $node, Environment $env): Node
	{
		if ($node instanceof ModuleNode) {
			$this->inAModule = true;
			$this->hasMacroCalls = false;
		}

		return $node;
	}


	public function leaveNode(Node $node, Environment $env): Node
	{
		if ($node instanceof ModuleNode) {
			$this->inAModule = false;
			if ($this->hasMacroCalls) {
				$node->getNode('constructor_end')->setNode('_auto_macro_import', new ImportNode(new NameExpression('_self', 0), new AssignNameExpression('_self', 0), 0, 'import', true));
			}
		} elseif ($this->inAModule) {
			if (
				$node instanceof GetAttrExpression &&
				$node->getNode('node') instanceof NameExpression &&
				$node->getNode('node')->getAttribute('name') === '_self' &&
				$node->getNode('attribute') instanceof ConstantExpression
			) {
				$this->hasMacroCalls = true;

				$name = $node->getNode('attribute')->getAttribute('value');
				$node = new MethodCallExpression($node->getNode('node'), $name, $node->getNode('arguments'), $node->getTemplateLine());
				$node->setAttribute('safe', true);
			}
		}

		return $node;
	}


	public function getPriority(): int
	{
		// we must be ran before auto-escaping
		return -10;
	}
}
