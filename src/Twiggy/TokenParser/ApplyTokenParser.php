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

use LatteTools\Twiggy\Node\BlockNode;
use LatteTools\Twiggy\Node\Node;
use LatteTools\Twiggy\Token;

/**
 * Applies filters on a section of a template.
 *
 *   {% apply upper %}
 *      This text becomes uppercase
 *   {% endapply %}
 *
 * @internal
 */
final class ApplyTokenParser extends AbstractTokenParser
{
	public function parse(Token $token): Node
	{
		$lineno = $token->getLine();
		$ref = new Node;
		$filter = $this->parser->getExpressionParser()->parseFilterExpressionRaw($ref, $this->getTag());

		$this->parser->getStream()->expect(Token::BLOCK_END_TYPE);
		$body = $this->parser->subparse([$this, 'decideApplyEnd'], true);
		$this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

		return new BlockNode('', $body, $lineno, $filter);
	}


	public function decideApplyEnd(Token $token): bool
	{
		return $token->test('endapply');
	}


	public function getTag(): string
	{
		return 'apply';
	}
}
