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

namespace LatteTools\Twiggy\Extra\Cache\Node;

use LatteTools\Twiggy\Compiler;
use LatteTools\Twiggy\Node\Expression\AbstractExpression;
use LatteTools\Twiggy\Node\Node;

class CacheNode extends Node
{
	public function __construct(
		AbstractExpression $key,
		?AbstractExpression $ttl,
		?AbstractExpression $tags,
		Node $body,
		int $lineno,
		string $tag
	) {
		$nodes = ['key' => $key, 'body' => $body];
		if ($ttl !== null) {
			$nodes['ttl'] = $ttl;
		}
		if ($tags !== null) {
			$nodes['tags'] = $tags;
		}

		parent::__construct($nodes, [], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$compiler
			->write('{cache ')
			->subcompile($this->getNode('key'))
		;

		if ($this->hasNode('tags')) {
			$compiler
				->write(', tags => [')
				->subcompile($this->getNode('tags'))
				->raw(']')
			;
		}

		if ($this->hasNode('ttl')) {
			$compiler
				->write(', expiration => ')
				->subcompile($this->getNode('ttl'))
			;
		}

		$compiler
			->write('}')
			->subcompile($this->getNode('body'))
			->write('{/cache}')
		;
	}
}
