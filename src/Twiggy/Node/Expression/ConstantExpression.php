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

class ConstantExpression extends AbstractExpression
{
	public function __construct($value, int $lineno)
	{
		parent::__construct([], ['value' => $value], $lineno);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler->repr($this->getAttribute('value'));
	}
}
