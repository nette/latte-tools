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

namespace LatteTools\Twiggy\Extra\Cache\TokenParser;

use LatteTools\Twiggy\Error\SyntaxError;
use LatteTools\Twiggy\Extra\Cache\Node\CacheNode;
use LatteTools\Twiggy\Node\Node;
use LatteTools\Twiggy\Token;
use LatteTools\Twiggy\TokenParser\AbstractTokenParser;

class CacheTokenParser extends AbstractTokenParser
{
	public function parse(Token $token): Node
	{
		$stream = $this->parser->getStream();
		$expressionParser = $this->parser->getExpressionParser();
		$key = $expressionParser->parseExpression();

		$ttl = null;
		$tags = null;
		while ($stream->test(Token::NAME_TYPE)) {
			$k = $stream->getCurrent()->getValue();
			$stream->next();
			$args = $expressionParser->parseArguments();

			switch ($k) {
				case 'ttl':
					if (count($args) !== 1) {
						throw new SyntaxError(sprintf('The "ttl" modifier takes exactly one argument (%d given).', count($args)), $stream->getCurrent()->getLine(), $stream->getSourceContext());
					}
					$ttl = $args->getNode(0);
					break;
				case 'tags':
					if (count($args) !== 1) {
						throw new SyntaxError(sprintf('The "ttl" modifier takes exactly one argument (%d given).', count($args)), $stream->getCurrent()->getLine(), $stream->getSourceContext());
					}
					$tags = $args->getNode(0);
					break;
				default:
					throw new SyntaxError(sprintf('Unknown "%s" configuration.', $k), $stream->getCurrent()->getLine(), $stream->getSourceContext());
			}
		}

		$stream->expect(Token::BLOCK_END_TYPE);
		$body = $this->parser->subparse([$this, 'decideCacheEnd'], true);
		$stream->expect(Token::BLOCK_END_TYPE);

		return new CacheNode($key, $ttl, $tags, $body, $token->getLine(), $this->getTag());
	}


	public function decideCacheEnd(Token $token): bool
	{
		return $token->test('endcache');
	}


	public function getTag(): string
	{
		return 'cache';
	}
}
