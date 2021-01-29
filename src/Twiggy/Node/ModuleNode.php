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
use LatteTools\Twiggy\Source;

/**
 * Represents a module node.
 *
 * Consider this class as being final. If you need to customize the behavior of
 * the generated class, consider adding nodes to the following nodes: display_start,
 * display_end, constructor_start, constructor_end, and class_end.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ModuleNode extends Node
{
	public function __construct(
		Node $body,
		?AbstractExpression $parent,
		Node $blocks,
		Node $macros,
		Node $traits,
		$embeddedTemplates,
		Source $source
	) {
		$nodes = [
			'body' => $body,
			'blocks' => $blocks,
			'macros' => $macros,
			'traits' => $traits,
			'display_start' => new Node,
			'display_end' => new Node,
			'constructor_start' => new Node,
			'constructor_end' => new Node,
			'class_end' => new Node,
		];
		if ($parent !== null) {
			$nodes['parent'] = $parent;
		}

		// embedded templates are set as attributes so that they are only visited once by the visitors
		parent::__construct($nodes, [
			'index' => null,
			'embedded_templates' => $embeddedTemplates,
		], 1);

		// populate the template name of all node children
		$this->setSourceContext($source);
	}


	public function setIndex($index)
	{
		$this->setAttribute('index', $index);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler->subcompile($this->getNode('body'));
	}
}
