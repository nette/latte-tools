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

namespace LatteTools\Twiggy\TokenParser;

use LatteTools\Twiggy\Error\SyntaxError;
use LatteTools\Twiggy\Node\ExtendsNode;
use LatteTools\Twiggy\Node\Node;
use LatteTools\Twiggy\Token;

/**
 * Extends a template by another one.
 *
 *  {% extends "base.html" %}
 *
 * @internal
 */
final class ExtendsTokenParser extends AbstractTokenParser
{
	public function parse(Token $token): Node
	{
		$stream = $this->parser->getStream();

		if ($this->parser->peekBlockStack()) {
			throw new SyntaxError('Cannot use "extend" in a block.', $token->getLine(), $stream->getSourceContext());
		} elseif (!$this->parser->isMainScope()) {
			throw new SyntaxError('Cannot use "extend" in a macro.', $token->getLine(), $stream->getSourceContext());
		}

		if ($this->parser->getParent() !== null) {
			throw new SyntaxError('Multiple extends tags are forbidden.', $token->getLine(), $stream->getSourceContext());
		}
		$expr = $this->parser->getExpressionParser()->parseExpression();

		$stream->expect(Token::BLOCK_END_TYPE);

		return new ExtendsNode($expr, $token->getLine(), $this->getTag());
	}


	public function getTag(): string
	{
		return 'extends';
	}
}
