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
use LatteTools\Twiggy\Node\Node;

/**
 * Represents a block call node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class BlockReferenceExpression extends AbstractExpression
{
	public function __construct(Node $name, ?Node $template, int $lineno, string $tag = null)
	{
		$nodes = ['name' => $name];
		if ($template !== null) {
			$nodes['template'] = $template;
		}

		parent::__construct($nodes, ['is_defined_test' => false, 'output' => false], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$name = $this->getNode('name');
		if ($this->getAttribute('is_defined_test')) {
			// TODO

			if (iterator_count($this->getIterator()) > 1) {
				$compiler->raw('/* NOT SUPPORTED ');
				foreach ($this as $node) {
					$compiler->subcompile($node)->raw(' ');
				}
				$compiler->raw(' */');
			}
			if ($name instanceof ConstantExpression) {
				$compiler->raw('ifset ')
					->raw($name->getAttribute('value'));
			} else {
				$compiler->raw('ifset block ')
					->subcompile($name);
			}

		} else {
			$compiler->raw('{include ');
			if ($name instanceof ConstantExpression) {
				$compiler->raw($name->getAttribute('value'));
			} else {
				$compiler->subcompile($name);
			}

			if ($this->hasNode('template')) {
				$compiler->raw(' from ')
					->filename($this->getNode('template'));
			}

			$compiler->raw('}');
		}
	}
}
