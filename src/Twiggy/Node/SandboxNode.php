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
 * Represents a sandbox node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SandboxNode extends Node
{
	public function __construct(Node $body, int $lineno, string $tag = null)
	{
		parent::__construct(['body' => $body], [], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler
			->subcompile($this->getNode('body'))
		;
	}
}
