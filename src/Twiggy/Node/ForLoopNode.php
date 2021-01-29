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
 * Internal node used by the for node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ForLoopNode extends Node
{
	public function __construct(int $lineno, string $tag = null)
	{
		parent::__construct([], ['with_loop' => false, 'ifexpr' => false, 'else' => false], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
	}
}
