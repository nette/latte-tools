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

class ConditionalExpression extends AbstractExpression
{
	public function __construct(
		AbstractExpression $expr1,
		AbstractExpression $expr2,
		AbstractExpression $expr3,
		int $lineno
	) {
		parent::__construct(['expr1' => $expr1, 'expr2' => $expr2, 'expr3' => $expr3], [], $lineno);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler
			->subcompile($this->getNode('expr1'))
			->raw(' ? ')
			->subcompile($this->getNode('expr2'));

		$expr3 = $this->getNode('expr3');
		if (!$expr3 instanceof ConstantExpression || $expr3->getAttribute('value') !== null) {
			$compiler->raw(' : ')
				->subcompile($this->getNode('expr3'));
		}
	}
}
