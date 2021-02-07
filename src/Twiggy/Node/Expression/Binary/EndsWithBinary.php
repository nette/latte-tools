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

class EndsWithBinary extends AbstractBinary
{
	public function compile(Compiler $compiler): void
	{
		$compiler
			->raw('str_ends_with(')
			->subcompile($this->getNode('left'))
			->raw(', ')
			->subcompile($this->getNode('right'))
			->raw(')')
		;
	}


	public function operator(Compiler $compiler): Compiler
	{
		return $compiler->raw('');
	}
}
