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

namespace LatteTools\Twiggy\Node\Expression\Test;

use LatteTools\Twiggy\Compiler;
use LatteTools\Twiggy\Node\Expression\TestExpression;

/**
 * Checks if a number is even.
 *
 *  {{ var is even }}
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EvenTest extends TestExpression
{
	public function compile(Compiler $compiler): void
	{
		$compiler
			->subcompile($this->getNode('node'))
			->raw(' % 2 === 0')
		;
	}
}
