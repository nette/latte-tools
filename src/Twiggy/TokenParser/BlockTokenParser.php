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
use LatteTools\Twiggy\Node\BlockNode;
use LatteTools\Twiggy\Node\BlockReferenceNode;
use LatteTools\Twiggy\Node\Node;
use LatteTools\Twiggy\Node\PrintNode;
use LatteTools\Twiggy\Token;

/**
 * Marks a section of a template as being reusable.
 *
 *  {% block head %}
 *    <link rel="stylesheet" href="style.css" />
 *    <title>{% block title %}{% endblock %} - My Webpage</title>
 *  {% endblock %}
 *
 * @internal
 */
final class BlockTokenParser extends AbstractTokenParser
{
	public function parse(Token $token): Node
	{
		$lineno = $token->getLine();
		$stream = $this->parser->getStream();
		$name = $stream->expect(Token::NAME_TYPE)->getValue();
		if ($this->parser->hasBlock($name)) {
			throw new SyntaxError(sprintf("The block '%s' has already been defined line %d.", $name, $this->parser->getBlock($name)->getTemplateLine()), $stream->getCurrent()->getLine(), $stream->getSourceContext());
		}
		$this->parser->setBlock($name, $block = new BlockNode($name, new Node([]), $lineno));
		$this->parser->pushLocalScope();
		$this->parser->pushBlockStack($name);

		if ($stream->nextIf(Token::BLOCK_END_TYPE)) {
			$body = $this->parser->subparse([$this, 'decideBlockEnd'], true);
			if ($token = $stream->nextIf(Token::NAME_TYPE)) {
				$value = $token->getValue();

				if ($value != $name) {
					throw new SyntaxError(sprintf('Expected endblock for block "%s" (but "%s" given).', $name, $value), $stream->getCurrent()->getLine(), $stream->getSourceContext());
				}
			}
		} else {
			$body = new Node([
				new PrintNode($this->parser->getExpressionParser()->parseExpression(), $lineno),
			]);
		}
		$stream->expect(Token::BLOCK_END_TYPE);

		$block->setNode('body', $body);
		$this->parser->popBlockStack();
		$this->parser->popLocalScope();

		return new BlockReferenceNode($name, $lineno, $this->getTag(), $body);
	}


	public function decideBlockEnd(Token $token): bool
	{
		return $token->test('endblock');
	}


	public function getTag(): string
	{
		return 'block';
	}
}
