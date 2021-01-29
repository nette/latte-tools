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
			->write('$cached = $this->env->getRuntime(\'LatteTools\Twiggy\Extra\Cache\CacheRuntime\')->getCache()->get(')
			->subcompile($this->getNode('key'))
			->raw(", function (\\Symfony\\Contracts\\Cache\\ItemInterface \$item) use (\$context) {\n")
		;

		if ($this->hasNode('tags')) {
			$compiler
				->write('$item->tag(')
				->subcompile($this->getNode('tags'))
				->raw(");\n")
			;
		}

		if ($this->hasNode('ttl')) {
			$compiler
				->write('$item->expiresAfter(')
				->subcompile($this->getNode('ttl'))
				->raw(");\n")
			;
		}

		$compiler
			->write("ob_start(function () { return ''; });\n")
			->subcompile($this->getNode('body'))
			->write("\n")
			->write("return ob_get_clean();\n")
			->write("});\n")
			->write("echo '' === \$cached ? '' : new Markup(\$cached, \$this->env->getCharset());\n")
		;
	}
}
