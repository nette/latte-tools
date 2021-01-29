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

namespace LatteTools\Twiggy\Node\Expression\Binary;

use LatteTools\Twiggy\Compiler;

class FloorDivBinary extends AbstractBinary
{
	public function compile(Compiler $compiler): void
	{
		$compiler->raw('(int) floor(');
		parent::compile($compiler);
		$compiler->raw(')');
	}


	public function operator(Compiler $compiler): Compiler
	{
		return $compiler->raw('/');
	}
}
