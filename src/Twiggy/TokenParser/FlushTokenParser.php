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

use LatteTools\Twiggy\Node\FlushNode;
use LatteTools\Twiggy\Node\Node;
use LatteTools\Twiggy\Token;

/**
 * Flushes the output to the client.
 *
 * @see flush()
 *
 * @internal
 */
final class FlushTokenParser extends AbstractTokenParser
{
	public function parse(Token $token): Node
	{
		$this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

		return new FlushNode($token->getLine(), $this->getTag());
	}


	public function getTag(): string
	{
		return 'flush';
	}
}
