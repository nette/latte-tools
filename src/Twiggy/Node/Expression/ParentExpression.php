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

namespace LatteTools\Twiggy\Node\Expression;

use LatteTools\Twiggy\Compiler;

/**
 * Represents a parent node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ParentExpression extends AbstractExpression
{
	public function __construct(string $name, int $lineno, string $tag = null)
	{
		parent::__construct([], ['output' => false, 'name' => $name], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler
			->raw('{include parent}')
		;
	}
}
