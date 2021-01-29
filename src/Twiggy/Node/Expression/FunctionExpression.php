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

namespace LatteTools\Twiggy\Node\Expression;

use LatteTools\Twiggy\Compiler;
use LatteTools\Twiggy\Node\Node;

class FunctionExpression extends CallExpression
{
	public function __construct(string $name, Node $arguments, int $lineno)
	{
		parent::__construct(['arguments' => $arguments], ['name' => $name, 'is_defined_test' => false], $lineno);
	}


	public function compile(Compiler $compiler)
	{
		$name = $this->getAttribute('name');
		$function = $compiler->getEnvironment()->getFunction($name);

		$this->setAttribute('name', $name);
		$this->setAttribute('type', 'function');
		$this->setAttribute('needs_environment', $function->needsEnvironment());
		$this->setAttribute('needs_context', $function->needsContext());
		$this->setAttribute('arguments', $function->getArguments());
		$callable = $function->getCallable();
		if ($name === 'constant' && $this->getAttribute('is_defined_test')) {
			$callable = 'defined';
			$this->setAttribute('name', 'defined');
		}
		$this->setAttribute('callable', $callable);
		$this->setAttribute('is_variadic', $function->isVariadic());

		if ($name === 'include') {
			if (!$this->hasAttribute('is_topmost')) {
				//$compiler->raw('/* NOT SUPPORTED */ ');
			}

			$first = true;
			$compiler->raw('{include ');
			$arguments = $this->getArguments($callable, $this->getNode('arguments'));
			foreach ($arguments as $node) {
				$compiler->raw($first ? '' : ', ');
				$compiler->subcompile($node);
				$first = false;
			}

			$compiler->raw('}');
			return;
		}

		$this->compileCallable($compiler);
	}
}
