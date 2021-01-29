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
 * Represents a flush node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FlushNode extends Node
{
	public function __construct(int $lineno, string $tag)
	{
		parent::__construct([], [], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler
			->raw('{do flush()}')
		;
	}
}
