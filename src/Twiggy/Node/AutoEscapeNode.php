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
 * Represents an autoescape node.
 *
 * The value is the escaping strategy (can be html, js, ...)
 *
 * The true value is equivalent to html.
 *
 * If autoescaping is disabled, then the value is false.
 */
class AutoEscapeNode extends Node
{
	public function __construct($value, Node $body, int $lineno, string $tag = 'autoescape')
	{
		parent::__construct(['body' => $body], ['value' => $value], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler->subcompile($this->getNode('body'));
	}
}
