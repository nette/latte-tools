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

namespace LatteTools\Twiggy\Node\Expression\Binary;

use LatteTools\Twiggy\Compiler;

class BitwiseOrBinary extends AbstractBinary
{
	public function operator(Compiler $compiler): Compiler
	{
		throw new SyntaxError('Bitwise OR is not allowed in Latte.', $lineno);
	}
}
