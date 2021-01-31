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
use LatteTools\Twiggy\Error\SyntaxError;
use LatteTools\Twiggy\Node\Node;

abstract class CallExpression extends AbstractExpression
{
	private $reflector;


	protected function compileCallable(Compiler $compiler)
	{
		$name = $this->getAttribute('name');
		$compiler->raw($compiler->getEnvironment()->getLatteFunction($name));
		$this->compileArguments($compiler);
	}


	protected function compileArguments(Compiler $compiler): void
	{
		$compiler->raw('(');

		$first = true;

		if ($this->hasAttribute('arguments')) {
			foreach ($this->getAttribute('arguments') as $argument) {
				if (!$first) {
					$compiler->raw(', ');
				}
				$compiler->string($argument);
				$first = false;
			}
		}

		if ($this->hasNode('node')) {
			if (!$first) {
				$compiler->raw(', ');
			}
			$compiler->subcompile($this->getNode('node'));
			$first = false;
		}

		if ($this->hasNode('arguments')) {
			$callable = $this->getAttribute('callable');
			$arguments = $this->getArguments($callable, $this->getNode('arguments'));
			foreach ($arguments as $node) {
				if (!$first) {
					$compiler->raw(', ');
				}
				$compiler->subcompile($node);
				$first = false;
			}
		}

		$compiler->raw(')');
	}


	protected function getArguments($callable, $arguments)
	{
		$callType = $this->getAttribute('type');
		$callName = $this->getAttribute('name');

		$parameters = [];
		$named = false;
		foreach ($arguments as $name => $node) {
			if (!\is_int($name)) {
				$named = true;
				$name = $this->normalizeName($name);
			} elseif ($named) {
				throw new SyntaxError(sprintf('Positional arguments cannot be used after named arguments for %s "%s".', $callType, $callName), $this->getTemplateLine(), $this->getSourceContext());
			}

			$parameters[$name] = $node;
		}

		$isVariadic = $this->hasAttribute('is_variadic') && $this->getAttribute('is_variadic');
		if (!$named && !$isVariadic) {
			return $parameters;
		}

		if (!$callable) {
			if ($named) {
				$message = sprintf('Named arguments are not supported for %s "%s".', $callType, $callName);
			} else {
				$message = sprintf('Arbitrary positional arguments are not supported for %s "%s".', $callType, $callName);
			}

			throw new \LogicException($message);
		}

		[$callableParameters, $isPhpVariadic] = $this->getCallableParameters($callable, $isVariadic);
		$arguments = [];
		$names = [];
		$missingArguments = [];
		$optionalArguments = [];
		$pos = 0;
		foreach ($callableParameters as $callableParameter) {
			$name = $this->normalizeName($callableParameter->name);
			if (\PHP_VERSION_ID >= 80000 && $callable === 'range') {
				if ($name === 'start') {
					$name = 'low';
				} elseif ($name === 'end') {
					$name = 'high';
				}
			}

			$names[] = $name;

			if (\array_key_exists($name, $parameters)) {
				if (\array_key_exists($pos, $parameters)) {
					throw new SyntaxError(sprintf('Argument "%s" is defined twice for %s "%s".', $name, $callType, $callName), $this->getTemplateLine(), $this->getSourceContext());
				}

				if (\count($missingArguments)) {
					throw new SyntaxError(sprintf(
						'Argument "%s" could not be assigned for %s "%s(%s)" because it is mapped to an internal PHP function which cannot determine default value for optional argument%s "%s".',
						$name,
						$callType,
						$callName,
						implode(', ', $names),
						\count($missingArguments) > 1 ? 's' : '',
						implode('", "', $missingArguments),
					), $this->getTemplateLine(), $this->getSourceContext());
				}

				$arguments = array_merge($arguments, $optionalArguments);
				$arguments[] = $parameters[$name];
				unset($parameters[$name]);
				$optionalArguments = [];
			} elseif (\array_key_exists($pos, $parameters)) {
				$arguments = array_merge($arguments, $optionalArguments);
				$arguments[] = $parameters[$pos];
				unset($parameters[$pos]);
				$optionalArguments = [];
				++$pos;
			} elseif ($callableParameter->isDefaultValueAvailable()) {
				$optionalArguments[] = new ConstantExpression($callableParameter->getDefaultValue(), -1);
			} elseif ($callableParameter->isOptional()) {
				if (empty($parameters)) {
					break;
				} else {
					$missingArguments[] = $name;
				}
			} else {
				throw new SyntaxError(sprintf('Value for argument "%s" is required for %s "%s".', $name, $callType, $callName), $this->getTemplateLine(), $this->getSourceContext());
			}
		}

		if ($isVariadic) {
			$arbitraryArguments = $isPhpVariadic
				? new VariadicExpression([], -1)
				: new ArrayExpression([], -1);
			foreach ($parameters as $key => $value) {
				if (\is_int($key)) {
					$arbitraryArguments->addElement($value);
				} else {
					$arbitraryArguments->addElement($value, new ConstantExpression($key, -1));
				}
				unset($parameters[$key]);
			}

			if ($arbitraryArguments->count()) {
				$arguments = array_merge($arguments, $optionalArguments);
				$arguments[] = $arbitraryArguments;
			}
		}

		if (!empty($parameters)) {
			$unknownParameter = null;
			foreach ($parameters as $parameter) {
				if ($parameter instanceof Node) {
					$unknownParameter = $parameter;
					break;
				}
			}

			throw new SyntaxError(
				sprintf(
					'Unknown argument%s "%s" for %s "%s(%s)".',
					\count($parameters) > 1 ? 's' : '',
					implode('", "', array_keys($parameters)),
					$callType,
					$callName,
					implode(', ', $names),
				),
				$unknownParameter ? $unknownParameter->getTemplateLine() : $this->getTemplateLine(),
				$unknownParameter ? $unknownParameter->getSourceContext() : $this->getSourceContext(),
			);
		}

		return $arguments;
	}


	protected function normalizeName(string $name): string
	{
		return strtolower(preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'], ['\\1_\\2', '\\1_\\2'], $name));
	}


	private function getCallableParameters($callable, bool $isVariadic): array
	{
		[$r] = $this->reflectCallable($callable);
		if ($r === null) {
			return [[], false];
		}

		$parameters = $r->getParameters();
		if ($this->hasNode('node')) {
			array_shift($parameters);
		}
		if ($this->hasAttribute('needs_environment') && $this->getAttribute('needs_environment')) {
			array_shift($parameters);
		}
		if ($this->hasAttribute('needs_context') && $this->getAttribute('needs_context')) {
			array_shift($parameters);
		}
		if ($this->hasAttribute('arguments') && $this->getAttribute('arguments') !== null) {
			foreach ($this->getAttribute('arguments') as $argument) {
				array_shift($parameters);
			}
		}
		$isPhpVariadic = false;
		if ($isVariadic) {
			$argument = end($parameters);
			$isArray = $argument && $argument->hasType() && $argument->getType()->getName() === 'array';
			if ($isArray && $argument->isDefaultValueAvailable() && $argument->getDefaultValue() === []) {
				array_pop($parameters);
			} elseif ($argument && $argument->isVariadic()) {
				array_pop($parameters);
				$isPhpVariadic = true;
			} else {
				$callableName = $r->name;
				if ($r instanceof \ReflectionMethod) {
					$callableName = $r->getDeclaringClass()->name . '::' . $callableName;
				}

				throw new \LogicException(sprintf('The last parameter of "%s" for %s "%s" must be an array with default value, eg. "array $arg = []".', $callableName, $this->getAttribute('type'), $this->getAttribute('name')));
			}
		}

		return [$parameters, $isPhpVariadic];
	}


	private function reflectCallable($callable)
	{
		if ($this->reflector !== null) {
			return $this->reflector;
		}

		if (\is_array($callable)) {
			if (!method_exists($callable[0], $callable[1])) {
				// __call()
				return [null, []];
			}
			$r = new \ReflectionMethod($callable[0], $callable[1]);
		} elseif (\is_object($callable) && !$callable instanceof \Closure) {
			$r = new \ReflectionObject($callable);
			$r = $r->getMethod('__invoke');
			$callable = [$callable, '__invoke'];
		} elseif (\is_string($callable) && false !== $pos = strpos($callable, '::')) {
			$class = substr($callable, 0, $pos);
			$method = substr($callable, $pos + 2);
			if (!method_exists($class, $method)) {
				// __staticCall()
				return [null, []];
			}
			$r = new \ReflectionMethod($callable);
			$callable = [$class, $method];
		} else {
			$r = new \ReflectionFunction($callable);
		}

		return $this->reflector = [$r, $callable];
	}
}
