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
 * Checks if a variable is the exact same value as a constant.
 *
 *    {% if post.status is constant('Post::PUBLISHED') %}
 *      the status attribute is exactly the same as Post::PUBLISHED
 *    {% endif %}
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ConstantTest extends TestExpression
{
	public function compile(Compiler $compiler): void
	{
		$compiler
			->raw('constant(')
		;

		if ($this->getNode('arguments')->hasNode(1)) {
			$compiler
				->raw('get_class(')
				->subcompile($this->getNode('arguments')->getNode(1))
				->raw(')."::".')
			;
		}

		$compiler
			->subcompile($this->getNode('arguments')->getNode(0))
			->raw(') === ')
			->subcompile($this->getNode('node'))
		;
	}
}
