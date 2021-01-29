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

/**
 * Represents a set node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SetNode extends Node implements NodeCaptureInterface
{
	public function __construct(bool $capture, Node $names, Node $values, int $lineno, string $tag = null)
	{
		parent::__construct(['names' => $names, 'values' => $values], ['capture' => $capture, 'safe' => false], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$names = $this->getNode('names');
		$values = $this->getNode('values');
		if (count($names) > 1) {
			$compiler->raw('{var ');
			foreach ($names as $id => $node) {
				if ($id) {
					$compiler->raw(', ');
				}
				$compiler->subcompile($node)
					->raw(' = ')
					->subcompile($values->getNode($id));
			}
			$compiler->raw('}');

		} elseif ($this->getAttribute('capture')) {
			$compiler->raw('{capture ')
				->subcompile($names)
				->raw('}')
				->subcompile($values)
				->raw('{/capture}');

		} else {
			$compiler->raw('{var ')
				->subcompile($names)
				->raw(' = ')
				->subcompile($values)
				->raw('}');
		}
	}
}
