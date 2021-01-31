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

use LatteTools\Twiggy\Node\IncludeNode;
use LatteTools\Twiggy\Node\Node;
use LatteTools\Twiggy\Token;

/**
 * Includes a template.
 *
 *   {% include 'header.html' %}
 *     Body
 *   {% include 'footer.html' %}
 *
 * @internal
 */
class IncludeTokenParser extends AbstractTokenParser
{
	public function parse(Token $token): Node
	{
		$expr = $this->parser->getExpressionParser()->parseExpression();

		[$variables, $only, $ignoreMissing] = $this->parseArguments();

		return new IncludeNode($expr, $variables, $only, $ignoreMissing, $token->getLine(), $this->getTag());
	}


	protected function parseArguments()
	{
		$stream = $this->parser->getStream();

		$ignoreMissing = false;
		if ($stream->nextIf(Token::NAME_TYPE, 'ignore')) {
			$stream->expect(Token::NAME_TYPE, 'missing');

			$ignoreMissing = true;
		}

		$variables = null;
		if ($stream->nextIf(Token::NAME_TYPE, 'with')) {
			$variables = $this->parser->getExpressionParser()->parseExpression();
		}

		$only = false;
		if ($stream->nextIf(Token::NAME_TYPE, 'only')) {
			$only = true;
		}

		$stream->expect(Token::BLOCK_END_TYPE);

		return [$variables, $only, $ignoreMissing];
	}


	public function getTag(): string
	{
		return 'include';
	}
}
