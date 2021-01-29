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

namespace LatteTools\Twiggy\Node;

use LatteTools\Twiggy\Compiler;
use LatteTools\Twiggy\Error\SyntaxError;
use LatteTools\Twiggy\Node\Expression\ConstantExpression;

/**
 * Represents a macro node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MacroNode extends Node
{
	public const VARARGS_NAME = 'varargs';


	public function __construct(string $name, Node $body, Node $arguments, int $lineno, string $tag = null)
	{
		foreach ($arguments as $argumentName => $argument) {
			if ($argumentName === self::VARARGS_NAME) {
				throw new SyntaxError(sprintf('The argument "%s" in macro "%s" cannot be defined because the variable "%s" is reserved for arbitrary arguments.', self::VARARGS_NAME, $name, self::VARARGS_NAME), $argument->getTemplateLine(), $argument->getSourceContext());
			}
		}

		parent::__construct(['body' => $body, 'arguments' => $arguments], ['name' => $name], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler
			->raw('{define ')
			->raw($this->getAttribute('name'));


		foreach ($this->getNode('arguments') as $name => $default) {
			$compiler->raw(', ');
			if ($default instanceof ConstantExpression && $default->getAttribute('value') === null) {
				$compiler->raw('$' . $name);
			} else {
				$compiler
					->raw('$' . $name . ' = ')
					->subcompile($default);
			}
		}

		$compiler->raw('}')
			->subcompile($this->getNode('body'))
			->raw('{/define}');
	}
}
