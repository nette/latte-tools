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
use LatteTools\Twiggy\Node\Expression\AssignNameExpression;

/**
 * Represents a for node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ForNode extends Node
{
	private $loop;


	public function __construct(
		AssignNameExpression $keyTarget,
		AssignNameExpression $valueTarget,
		AbstractExpression $seq,
		?Node $ifexpr,
		Node $body,
		?Node $else,
		int $lineno,
		string $tag = null
	) {
		$body = new Node([$body, $this->loop = new ForLoopNode($lineno, $tag)]);

		$nodes = ['key_target' => $keyTarget, 'value_target' => $valueTarget, 'seq' => $seq, 'body' => $body];
		if ($else !== null) {
			$nodes['else'] = $else;
		}

		parent::__construct($nodes, ['with_loop' => true], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$this->loop->setAttribute('else', $this->hasNode('else'));
		$this->loop->setAttribute('with_loop', $this->getAttribute('with_loop'));

		$compiler
			->raw('{foreach ')
			->subcompile($this->getNode('seq'))
			->raw(' as ')
		;

		if ($this->getNode('key_target')->getAttribute('name') !== '') {
			$compiler
				->subcompile($this->getNode('key_target'))
				->raw(' => ')
			;
		}

		$compiler
			->subcompile($this->getNode('value_target'))
			->raw('}')
			->subcompile($this->getNode('body'));


		if ($this->hasNode('else')) {
			$compiler
				->raw('{else}')
				->subcompile($this->getNode('else'))
			;
		}

		$compiler->raw('{/foreach}');
	}
}
