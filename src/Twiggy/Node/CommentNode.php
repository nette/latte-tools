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

/**
 * Represents a commment.
 */
class CommentNode extends Node implements NodeOutputInterface
{
	public function __construct(string $data, int $lineno)
	{
		parent::__construct([], ['data' => $data], $lineno);
	}


	public function compile(Compiler $compiler): void
	{
		$text = str_replace('*}', '* }', $this->getAttribute('data'));
		$compiler
			->raw('{*')
			->raw($text)
			->raw('*}')
		;
	}
}
