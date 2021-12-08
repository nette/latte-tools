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

namespace LatteTools\Twiggy\TokenParser;

use LatteTools\Twiggy\Node\DoNode;
use LatteTools\Twiggy\Node\Node;
use LatteTools\Twiggy\Token;

/**
 * Evaluates an expression, discarding the returned value.
 *
 * @internal
 */
final class DoTokenParser extends AbstractTokenParser
{
	public function parse(Token $token): Node
	{
		$expr = $this->parser->getExpressionParser()->parseExpression();

		$this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

		return new DoNode($expr, $token->getLine(), $this->getTag());
	}


	public function getTag(): string
	{
		return 'do';
	}
}
