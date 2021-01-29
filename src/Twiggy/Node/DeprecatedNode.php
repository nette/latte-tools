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
use LatteTools\Twiggy\Node\Expression\AbstractExpression;

/**
 * Represents a deprecated node.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class DeprecatedNode extends Node
{
	public function __construct(AbstractExpression $expr, int $lineno, string $tag = null)
	{
		parent::__construct(['expr' => $expr], [], $lineno, $tag);
	}


	public function compile(Compiler $compiler): void
	{
		$expr = $this->getNode('expr');
		$compiler
			->raw('{do trigger_error(')
			->subcompile($expr)
			->raw(' . ')
			->string(sprintf(' ("%s" at line %d).', $this->getTemplateName(), $this->getTemplateLine()))
			->raw(', E_USER_DEPRECATED) }')
		;
	}
}
