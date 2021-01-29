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

class ArrayExpression extends AbstractExpression
{
	private $index;


	public function __construct(array $elements, int $lineno)
	{
		parent::__construct($elements, [], $lineno);

		$this->index = -1;
		foreach ($this->getKeyValuePairs() as $pair) {
			if (
				$pair['key'] instanceof ConstantExpression
				&& ctype_digit((string) $pair['key']->getAttribute('value'))
				&& $pair['key']->getAttribute('value') > $this->index
			) {
				$this->index = $pair['key']->getAttribute('value');
			}
		}
	}


	public function getKeyValuePairs(): array
	{
		$pairs = [];
		foreach (array_chunk($this->nodes, 2) as $pair) {
			$pairs[] = [
				'key' => $pair[0],
				'value' => $pair[1],
			];
		}

		return $pairs;
	}


	public function hasElement(AbstractExpression $key): bool
	{
		foreach ($this->getKeyValuePairs() as $pair) {
			// we compare the string representation of the keys
			// to avoid comparing the line numbers which are not relevant here.
			if ((string) $key === (string) $pair['key']) {
				return true;
			}
		}

		return false;
	}


	public function addElement(AbstractExpression $value, AbstractExpression $key = null): void
	{
		if ($key === null) {
			$key = new ConstantExpression(++$this->index, $value->getTemplateLine());
		}

		array_push($this->nodes, $key, $value);
	}


	public function compile(Compiler $compiler): void
	{
		if ($this->hasAttribute('as_arguments')) {
			$this->compileAsArguments($compiler);
			return;
		}

		$compiler->raw('[');
		$first = true;
		$counter = 0;
		foreach ($this->getKeyValuePairs() as $pair) {
			if (!$first) {
				$compiler->raw(', ');
			}
			$first = false;

			if (!$pair['key'] instanceof ConstantExpression || $pair['key']->getAttribute('value') !== $counter++) {
				$compiler
					->subcompile($pair['key'])
					->raw(' => ');
			}

			$compiler->subcompile($pair['value']);
		}
		$compiler->raw(']');
	}


	private function compileAsArguments(Compiler $compiler): void
	{
		$first = true;
		$counter = 0;
		foreach ($this->getKeyValuePairs() as $pair) {
			if (!$first) {
				$compiler->raw(', ');
			}
			$first = false;

			if (!$pair['key'] instanceof ConstantExpression || $pair['key']->getAttribute('value') !== $counter++) {
				$compiler
					->subcompile($pair['key'])
					->raw(' => ');
			}

			$compiler->subcompile($pair['value']);
		}
	}
}
