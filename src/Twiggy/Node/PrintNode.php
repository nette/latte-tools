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
use LatteTools\Twiggy\Node\Expression\AbstractExpression;

/**
 * Represents a node that outputs an expression.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class PrintNode extends Node implements NodeOutputInterface
{
	public function __construct(AbstractExpression $expr, int $lineno, string $tag = null)
	{
		parent::__construct(['expr' => $expr], [], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$subcomp = new Compiler($compiler->getEnvironment());
		$subcomp->compile($this->getNode('expr'));
		$code = $subcomp->getSource();

		$compiler
			->raw('{')
			->raw(preg_match('~\$|\w+::|\w+\(|\d+~A', $code) ? '' : '=')
			->raw($code)
			->raw('}')
		;
	}
}
