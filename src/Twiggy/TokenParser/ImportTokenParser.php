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

use LatteTools\Twiggy\Node\Expression\AssignNameExpression;
use LatteTools\Twiggy\Node\ImportNode;
use LatteTools\Twiggy\Node\Node;
use LatteTools\Twiggy\Token;

/**
 * Imports macros.
 *
 *   {% import 'forms.html' as forms %}
 *
 * @internal
 */
final class ImportTokenParser extends AbstractTokenParser
{
	public function parse(Token $token): Node
	{
		$macro = $this->parser->getExpressionParser()->parseExpression();
		$this->parser->getStream()->expect(Token::NAME_TYPE, 'as');
		$var = new AssignNameExpression($this->parser->getStream()->expect(Token::NAME_TYPE)->getValue(), $token->getLine());
		$this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

		$this->parser->addImportedSymbol('template', $var->getAttribute('name'));

		return new ImportNode($macro, $var, $token->getLine(), $this->getTag(), $this->parser->isMainScope());
	}


	public function getTag(): string
	{
		return 'import';
	}
}
