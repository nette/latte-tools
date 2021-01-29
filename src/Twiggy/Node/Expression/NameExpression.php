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

class NameExpression extends AbstractExpression
{
	private $specialVars = [
		//		'_self' => '$this->getTemplateName()',
		//		'_context' => '$context',
		//		'_charset' => '$this->env->getCharset()',
		'loop' => '$iterator',
	];


	public function __construct(string $name, int $lineno)
	{
		parent::__construct([], ['name' => $name, 'is_defined_test' => false, 'ignore_strict_check' => false, 'always_defined' => false], $lineno);
	}


	public function compile(Compiler $compiler): void
	{
		$name = $this->getAttribute('name');

		if ($this->getAttribute('is_defined_test')) {
			if ($this->isSpecial()) {
				$compiler->repr(true);
			} else {
				$compiler
					->raw('isset(')
					->raw('$' . $name)
					->raw(')')
				;
			}
		} elseif ($this->isSpecial()) {
			$compiler->raw($this->specialVars[$name]);

		} else {
			$compiler
				->raw('$' . $name)
				;
		}
	}


	public function isSpecial()
	{
		return isset($this->specialVars[$this->getAttribute('name')]);
	}
}
