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

namespace LatteTools\Twiggy;

use LatteTools\Twiggy\Error\SyntaxError;
use LatteTools\Twiggy\Node\Expression\AbstractExpression;
use LatteTools\Twiggy\Node\Expression\ArrayExpression;
use LatteTools\Twiggy\Node\Expression\ArrowFunctionExpression;
use LatteTools\Twiggy\Node\Expression\AssignNameExpression;
use LatteTools\Twiggy\Node\Expression\Binary\ConcatBinary;
use LatteTools\Twiggy\Node\Expression\BlockReferenceExpression;
use LatteTools\Twiggy\Node\Expression\ConditionalExpression;
use LatteTools\Twiggy\Node\Expression\ConstantExpression;
use LatteTools\Twiggy\Node\Expression\GetAttrExpression;
use LatteTools\Twiggy\Node\Expression\MethodCallExpression;
use LatteTools\Twiggy\Node\Expression\NameExpression;
use LatteTools\Twiggy\Node\Expression\ParentExpression;
use LatteTools\Twiggy\Node\Expression\TestExpression;
use LatteTools\Twiggy\Node\Expression\Unary\NegUnary;
use LatteTools\Twiggy\Node\Expression\Unary\NotUnary;
use LatteTools\Twiggy\Node\Expression\Unary\PosUnary;
use LatteTools\Twiggy\Node\Node;

/**
 * Parses expressions.
 *
 * This parser implements a "Precedence climbing" algorithm.
 *
 * @see https://www.engr.mun.ca/~theo/Misc/exp_parsing.htm
 * @see https://en.wikipedia.org/wiki/Operator-precedence_parser
 *
 * @internal
 */
class ExpressionParser
{
	public const OPERATOR_LEFT = 1;
	public const OPERATOR_RIGHT = 2;

	private $parser;
	private $env;
	private $unaryOperators;
	private $binaryOperators;


	public function __construct(Parser $parser, Environment $env)
	{
		$this->parser = $parser;
		$this->env = $env;
		$this->unaryOperators = $env->getUnaryOperators();
		$this->binaryOperators = $env->getBinaryOperators();
	}


	public function parseExpression($precedence = 0, $allowArrow = false)
	{
		if ($allowArrow && $arrow = $this->parseArrow()) {
			return $arrow;
		}

		$expr = $this->getPrimary();
		$token = $this->parser->getCurrentToken();
		while ($this->isBinary($token) && $this->binaryOperators[$token->getValue()]['precedence'] >= $precedence) {
			$op = $this->binaryOperators[$token->getValue()];
			$this->parser->getStream()->next();

			if ($token->getValue() === 'is not') {
				$expr = $this->parseNotTestExpression($expr);
			} elseif ($token->getValue() === 'is') {
				$expr = $this->parseTestExpression($expr);
			} elseif (isset($op['callable'])) {
				$expr = $op['callable']($this->parser, $expr);
			} else {
				$expr1 = $this->parseExpression($op['associativity'] === self::OPERATOR_LEFT ? $op['precedence'] + 1 : $op['precedence']);
				$class = $op['class'];
				$expr = new $class($expr, $expr1, $token->getLine());
			}

			$token = $this->parser->getCurrentToken();
		}

		if ($precedence === 0) {
			return $this->parseConditionalExpression($expr);
		}

		return $expr;
	}


	/**
	 * @return ArrowFunctionExpression|null
	 */
	private function parseArrow()
	{
		$stream = $this->parser->getStream();

		// short array syntax (one argument, no parentheses)?
		if ($stream->look(1)->test(Token::ARROW_TYPE)) {
			$line = $stream->getCurrent()->getLine();
			$token = $stream->expect(Token::NAME_TYPE);
			$names = [new AssignNameExpression($token->getValue(), $token->getLine())];
			$stream->expect(Token::ARROW_TYPE);

			return new ArrowFunctionExpression($this->parseExpression(0), new Node($names), $line);
		}

		// first, determine if we are parsing an arrow function by finding => (long form)
		$i = 0;
		if (!$stream->look($i)->test(Token::PUNCTUATION_TYPE, '(')) {
			return null;
		}
		++$i;
		while (true) {
			// variable name
			++$i;
			if (!$stream->look($i)->test(Token::PUNCTUATION_TYPE, ',')) {
				break;
			}
			++$i;
		}

		if (!$stream->look($i)->test(Token::PUNCTUATION_TYPE, ')')) {
			return null;
		}
		++$i;
		if (!$stream->look($i)->test(Token::ARROW_TYPE)) {
			return null;
		}

		// yes, let's parse it properly
		$token = $stream->expect(Token::PUNCTUATION_TYPE, '(');
		$line = $token->getLine();

		$names = [];
		while (true) {
			$token = $stream->expect(Token::NAME_TYPE);
			$names[] = new AssignNameExpression($token->getValue(), $token->getLine());

			if (!$stream->nextIf(Token::PUNCTUATION_TYPE, ',')) {
				break;
			}
		}

		$stream->expect(Token::PUNCTUATION_TYPE, ')');
		$stream->expect(Token::ARROW_TYPE);

		return new ArrowFunctionExpression($this->parseExpression(0), new Node($names), $line);
	}


	private function getPrimary(): AbstractExpression
	{
		$token = $this->parser->getCurrentToken();

		if ($this->isUnary($token)) {
			$operator = $this->unaryOperators[$token->getValue()];
			$this->parser->getStream()->next();
			$expr = $this->parseExpression($operator['precedence']);
			$class = $operator['class'];

			return $this->parsePostfixExpression(new $class($expr, $token->getLine()));
		} elseif ($token->test(Token::PUNCTUATION_TYPE, '(')) {
			$this->parser->getStream()->next();
			$expr = $this->parseExpression();
			$this->parser->getStream()->expect(Token::PUNCTUATION_TYPE, ')', 'An opened parenthesis is not properly closed');

			return $this->parsePostfixExpression($expr);
		}

		return $this->parsePrimaryExpression();
	}


	private function parseConditionalExpression($expr): AbstractExpression
	{
		while ($this->parser->getStream()->nextIf(Token::PUNCTUATION_TYPE, '?')) {
			if (!$this->parser->getStream()->nextIf(Token::PUNCTUATION_TYPE, ':')) {
				$expr2 = $this->parseExpression();
				if ($this->parser->getStream()->nextIf(Token::PUNCTUATION_TYPE, ':')) {
					$expr3 = $this->parseExpression();
				} else {
					$expr3 = new ConstantExpression('', $this->parser->getCurrentToken()->getLine());
				}
			} else {
				$expr2 = $expr;
				$expr3 = $this->parseExpression();
			}

			$expr = new ConditionalExpression($expr, $expr2, $expr3, $this->parser->getCurrentToken()->getLine());
		}

		return $expr;
	}


	private function isUnary(Token $token): bool
	{
		return $token->test(Token::OPERATOR_TYPE) && isset($this->unaryOperators[$token->getValue()]);
	}


	private function isBinary(Token $token): bool
	{
		return $token->test(Token::OPERATOR_TYPE) && isset($this->binaryOperators[$token->getValue()]);
	}


	public function parsePrimaryExpression()
	{
		$token = $this->parser->getCurrentToken();
		switch ($token->getType()) {
			case Token::NAME_TYPE:
				$this->parser->getStream()->next();
				switch ($token->getValue()) {
					case 'true':
					case 'TRUE':
						$node = new ConstantExpression(true, $token->getLine());
						break;

					case 'false':
					case 'FALSE':
						$node = new ConstantExpression(false, $token->getLine());
						break;

					case 'none':
					case 'NONE':
					case 'null':
					case 'NULL':
						$node = new ConstantExpression(null, $token->getLine());
						break;

					default:
						if ($this->parser->getCurrentToken()->getValue() === '(') {
							$node = $this->getFunctionNode($token->getValue(), $token->getLine());
						} else {
							$node = new NameExpression($token->getValue(), $token->getLine());
						}
				}

				break;

			case Token::NUMBER_TYPE:
				$this->parser->getStream()->next();
				$node = new ConstantExpression($token->getValue(), $token->getLine());
				break;

			case Token::STRING_TYPE:
			case Token::INTERPOLATION_START_TYPE:
				$node = $this->parseStringExpression();
				break;

			case Token::OPERATOR_TYPE:
				if (preg_match(Lexer::REGEX_NAME, $token->getValue(), $matches) && $matches[0] == $token->getValue()) {
					// in this context, string operators are variable names
					$this->parser->getStream()->next();
					$node = new NameExpression($token->getValue(), $token->getLine());
					break;
				} elseif (isset($this->unaryOperators[$token->getValue()])) {
					$class = $this->unaryOperators[$token->getValue()]['class'];
					if (!\in_array($class, [NegUnary::class, PosUnary::class], true)) {
						throw new SyntaxError(sprintf('Unexpected unary operator "%s".', $token->getValue()), $token->getLine(), $this->parser->getStream()->getSourceContext());
					}

					$this->parser->getStream()->next();
					$expr = $this->parsePrimaryExpression();

					$node = new $class($expr, $token->getLine());
					break;
				}

				// no break
				// break omitted
			default:
				if ($token->test(Token::PUNCTUATION_TYPE, '[')) {
					$node = $this->parseArrayExpression();
				} elseif ($token->test(Token::PUNCTUATION_TYPE, '{')) {
					$node = $this->parseHashExpression();
				} elseif (
					$token->test(Token::OPERATOR_TYPE, '=')
					&& (
						$this->parser->getStream()->look(-1)->getValue() === '=='
						|| $this->parser->getStream()->look(-1)->getValue() === '!='
					)
				) {
					throw new SyntaxError(sprintf('Unexpected operator of value "%s". Did you try to use "===" or "!==" for strict comparison? Use "is same as(value)" instead.', $token->getValue()), $token->getLine(), $this->parser->getStream()->getSourceContext());
				} else {
					throw new SyntaxError(sprintf('Unexpected token "%s" of value "%s".', Token::typeToEnglish($token->getType()), $token->getValue()), $token->getLine(), $this->parser->getStream()->getSourceContext());
				}
		}

		return $this->parsePostfixExpression($node);
	}


	public function parseStringExpression()
	{
		$stream = $this->parser->getStream();

		$nodes = [];
		// a string cannot be followed by another string in a single expression
		$nextCanBeString = true;
		while (true) {
			if ($nextCanBeString && $token = $stream->nextIf(Token::STRING_TYPE)) {
				$nodes[] = new ConstantExpression($token->getValue(), $token->getLine());
				$nextCanBeString = false;
			} elseif ($stream->nextIf(Token::INTERPOLATION_START_TYPE)) {
				$nodes[] = $this->parseExpression();
				$stream->expect(Token::INTERPOLATION_END_TYPE);
				$nextCanBeString = true;
			} else {
				break;
			}
		}

		$expr = array_shift($nodes);
		foreach ($nodes as $node) {
			$expr = new ConcatBinary($expr, $node, $node->getTemplateLine());
		}

		return $expr;
	}


	public function parseArrayExpression()
	{
		$stream = $this->parser->getStream();
		$stream->expect(Token::PUNCTUATION_TYPE, '[', 'An array element was expected');

		$node = new ArrayExpression([], $stream->getCurrent()->getLine());
		$first = true;
		while (!$stream->test(Token::PUNCTUATION_TYPE, ']')) {
			if (!$first) {
				$stream->expect(Token::PUNCTUATION_TYPE, ',', 'An array element must be followed by a comma');

				// trailing ,?
				if ($stream->test(Token::PUNCTUATION_TYPE, ']')) {
					break;
				}
			}
			$first = false;

			$node->addElement($this->parseExpression());
		}

		$stream->expect(Token::PUNCTUATION_TYPE, ']', 'An opened array is not properly closed');

		return $node;
	}


	public function parseHashExpression()
	{
		$stream = $this->parser->getStream();
		$stream->expect(Token::PUNCTUATION_TYPE, '{', 'A hash element was expected');

		$node = new ArrayExpression([], $stream->getCurrent()->getLine());
		$first = true;
		while (!$stream->test(Token::PUNCTUATION_TYPE, '}')) {
			if (!$first) {
				$stream->expect(Token::PUNCTUATION_TYPE, ',', 'A hash value must be followed by a comma');

				// trailing ,?
				if ($stream->test(Token::PUNCTUATION_TYPE, '}')) {
					break;
				}
			}
			$first = false;

			// a hash key can be:
			//
			//  * a number -- 12
			//  * a string -- 'a'
			//  * a name, which is equivalent to a string -- a
			//  * an expression, which must be enclosed in parentheses -- (1 + 2)
			if ($token = $stream->nextIf(Token::NAME_TYPE)) {
				$key = new ConstantExpression($token->getValue(), $token->getLine());

				// {a} is a shortcut for {a:a}
				if ($stream->test(Token::PUNCTUATION_TYPE, [',', '}'])) {
					$value = new NameExpression($key->getAttribute('value'), $key->getTemplateLine());
					$node->addElement($value, $key);
					continue;
				}
			} elseif (
				($token = $stream->nextIf(Token::STRING_TYPE))
				|| $token = $stream->nextIf(Token::NUMBER_TYPE)
			) {
				$key = new ConstantExpression($token->getValue(), $token->getLine());
			} elseif ($stream->test(Token::PUNCTUATION_TYPE, '(')) {
				$key = $this->parseExpression();
			} else {
				$current = $stream->getCurrent();

				throw new SyntaxError(sprintf('A hash key must be a quoted string, a number, a name, or an expression enclosed in parentheses (unexpected token "%s" of value "%s".', Token::typeToEnglish($current->getType()), $current->getValue()), $current->getLine(), $stream->getSourceContext());
			}

			$stream->expect(Token::PUNCTUATION_TYPE, ':', 'A hash key must be followed by a colon (:)');
			$value = $this->parseExpression();

			$node->addElement($value, $key);
		}

		$stream->expect(Token::PUNCTUATION_TYPE, '}', 'An opened hash is not properly closed');

		return $node;
	}


	public function parsePostfixExpression($node)
	{
		while (true) {
			$token = $this->parser->getCurrentToken();
			if ($token->getType() == Token::PUNCTUATION_TYPE) {
				if ($token->getValue() == '.' || $token->getValue() == '[') {
					$node = $this->parseSubscriptExpression($node);
				} elseif ($token->getValue() == '|') {
					$node = $this->parseFilterExpression($node);
				} else {
					break;
				}
			} else {
				break;
			}
		}

		return $node;
	}


	public function getFunctionNode($name, $line)
	{
		switch ($name) {
			case 'parent':
				$this->parseArguments();
				if (!\count($this->parser->getBlockStack())) {
					throw new SyntaxError('Calling "parent" outside a block is forbidden.', $line, $this->parser->getStream()->getSourceContext());
				}

				return new ParentExpression($this->parser->peekBlockStack(), $line);
			case 'block':
				$args = $this->parseArguments();
				if (\count($args) < 1) {
					throw new SyntaxError('The "block" function takes one argument (the block name).', $line, $this->parser->getStream()->getSourceContext());
				}

				return new BlockReferenceExpression($args->getNode(0), \count($args) > 1 ? $args->getNode(1) : null, $line);
			case 'attribute':
				$args = $this->parseArguments();
				if (\count($args) < 2) {
					throw new SyntaxError('The "attribute" function takes at least two arguments (the variable and the attributes).', $line, $this->parser->getStream()->getSourceContext());
				}

				return new GetAttrExpression($args->getNode(0), $args->getNode(1), \count($args) > 2 ? $args->getNode(2) : null, Template::ANY_CALL, $line);
			default:
				if (null !== $alias = $this->parser->getImportedSymbol('function', $name)) {
					$arguments = new ArrayExpression([], $line);
					foreach ($this->parseArguments() as $n) {
						$arguments->addElement($n);
					}

					$node = new MethodCallExpression($alias['node'], $alias['name'], $arguments, $line);
					$node->setAttribute('safe', true);

					return $node;
				}

				$args = $this->parseArguments(true);
				$class = $this->getFunctionNodeClass($name, $line);

				return new $class($name, $args, $line);
		}
	}


	public function parseSubscriptExpression($node)
	{
		$stream = $this->parser->getStream();
		$token = $stream->next();
		$lineno = $token->getLine();
		$arguments = new ArrayExpression([], $lineno);
		$type = Template::ANY_CALL;
		if ($token->getValue() == '.') {
			$token = $stream->next();
			if (
				$token->getType() == Token::NAME_TYPE
				||
				$token->getType() == Token::NUMBER_TYPE
				||
				($token->getType() == Token::OPERATOR_TYPE && preg_match(Lexer::REGEX_NAME, $token->getValue()))
			) {
				$arg = new ConstantExpression($token->getValue(), $lineno);

				if ($stream->test(Token::PUNCTUATION_TYPE, '(')) {
					$type = Template::METHOD_CALL;
					foreach ($this->parseArguments() as $n) {
						$arguments->addElement($n);
					}
				}
			} else {
				throw new SyntaxError('Expected name or number.', $lineno, $stream->getSourceContext());
			}

			if (
				$node instanceof NameExpression
				&& $this->parser->getImportedSymbol('template', $node->getAttribute('name')) !== null
			) {
				if (!$arg instanceof ConstantExpression) {
					throw new SyntaxError(sprintf('Dynamic macro names are not supported (called on "%s").', $node->getAttribute('name')), $token->getLine(), $stream->getSourceContext());
				}

				$name = $arg->getAttribute('value');

				$node = new MethodCallExpression($node, $name, $arguments, $lineno);
				$node->setAttribute('safe', true);

				return $node;
			}
		} else {
			$type = Template::ARRAY_CALL;

			// slice?
			$slice = false;
			if ($stream->test(Token::PUNCTUATION_TYPE, ':')) {
				$slice = true;
				$arg = new ConstantExpression(0, $token->getLine());
			} else {
				$arg = $this->parseExpression();
			}

			if ($stream->nextIf(Token::PUNCTUATION_TYPE, ':')) {
				$slice = true;
			}

			if ($slice) {
				if ($stream->test(Token::PUNCTUATION_TYPE, ']')) {
					$length = new ConstantExpression(null, $token->getLine());
				} else {
					$length = $this->parseExpression();
				}

				$class = $this->getFilterNodeClass('slice', $token->getLine());
				$arguments = new Node([$arg, $length]);
				$filter = new $class($node, new ConstantExpression('slice', $token->getLine()), $arguments, $token->getLine());

				$stream->expect(Token::PUNCTUATION_TYPE, ']');

				return $filter;
			}

			$stream->expect(Token::PUNCTUATION_TYPE, ']');
		}

		return new GetAttrExpression($node, $arg, $arguments, $type, $lineno);
	}


	public function parseFilterExpression($node)
	{
		$this->parser->getStream()->next();

		return $this->parseFilterExpressionRaw($node);
	}


	public function parseFilterExpressionRaw($node, $tag = null)
	{
		while (true) {
			$token = $this->parser->getStream()->expect(Token::NAME_TYPE);

			$name = new ConstantExpression($token->getValue(), $token->getLine());
			if (!$this->parser->getStream()->test(Token::PUNCTUATION_TYPE, '(')) {
				$arguments = new Node;
			} else {
				$arguments = $this->parseArguments(true, false, true);
			}

			$class = $this->getFilterNodeClass($name->getAttribute('value'), $token->getLine());

			$node = new $class($node, $name, $arguments, $token->getLine(), $tag);

			if (!$this->parser->getStream()->test(Token::PUNCTUATION_TYPE, '|')) {
				break;
			}

			$this->parser->getStream()->next();
		}

		return $node;
	}


	/**
	 * Parses arguments.
	 *
	 * @param bool $namedArguments Whether to allow named arguments or not
	 * @param bool $definition     Whether we are parsing arguments for a function definition
	 *
	 * @return Node
	 *
	 * @throws SyntaxError
	 */
	public function parseArguments($namedArguments = false, $definition = false, $allowArrow = false)
	{
		$args = [];
		$stream = $this->parser->getStream();

		$stream->expect(Token::PUNCTUATION_TYPE, '(', 'A list of arguments must begin with an opening parenthesis');
		while (!$stream->test(Token::PUNCTUATION_TYPE, ')')) {
			if (!empty($args)) {
				$stream->expect(Token::PUNCTUATION_TYPE, ',', 'Arguments must be separated by a comma');

				// if the comma above was a trailing comma, early exit the argument parse loop
				if ($stream->test(Token::PUNCTUATION_TYPE, ')')) {
					break;
				}
			}

			if ($definition) {
				$token = $stream->expect(Token::NAME_TYPE, null, 'An argument must be a name');
				$value = new NameExpression($token->getValue(), $this->parser->getCurrentToken()->getLine());
			} else {
				$value = $this->parseExpression(0, $allowArrow);
			}

			$name = null;
			if ($namedArguments && $token = $stream->nextIf(Token::OPERATOR_TYPE, '=')) {
				if (!$value instanceof NameExpression) {
					throw new SyntaxError(sprintf('A parameter name must be a string, "%s" given.', $value::class), $token->getLine(), $stream->getSourceContext());
				}
				$name = $value->getAttribute('name');

				if ($definition) {
					$value = $this->parsePrimaryExpression();

					if (!$this->checkConstantExpression($value)) {
						throw new SyntaxError(sprintf('A default value for an argument must be a constant (a boolean, a string, a number, or an array).'), $token->getLine(), $stream->getSourceContext());
					}
				} else {
					$value = $this->parseExpression(0, $allowArrow);
				}
			}

			if ($definition) {
				if ($name === null) {
					$name = $value->getAttribute('name');
					$value = new ConstantExpression(null, $this->parser->getCurrentToken()->getLine());
				}
				$args[$name] = $value;
			} else {
				if ($name === null) {
					$args[] = $value;
				} else {
					$args[$name] = $value;
				}
			}
		}

		$stream->expect(Token::PUNCTUATION_TYPE, ')', 'A list of arguments must be closed by a parenthesis');

		return new Node($args);
	}


	public function parseAssignmentExpression()
	{
		$stream = $this->parser->getStream();
		$targets = [];
		while (true) {
			$token = $this->parser->getCurrentToken();
			if ($stream->test(Token::OPERATOR_TYPE) && preg_match(Lexer::REGEX_NAME, $token->getValue())) {
				// in this context, string operators are variable names
				$this->parser->getStream()->next();
			} else {
				$stream->expect(Token::NAME_TYPE, null, 'Only variables can be assigned to');
			}
			$value = $token->getValue();
			if (\in_array(strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), ['true', 'false', 'none', 'null'], true)) {
				throw new SyntaxError(sprintf('You cannot assign a value to "%s".', $value), $token->getLine(), $stream->getSourceContext());
			}
			$targets[] = new AssignNameExpression($value, $token->getLine());

			if (!$stream->nextIf(Token::PUNCTUATION_TYPE, ',')) {
				break;
			}
		}

		return new Node($targets);
	}


	public function parseMultitargetExpression()
	{
		$targets = [];
		while (true) {
			$targets[] = $this->parseExpression();
			if (!$this->parser->getStream()->nextIf(Token::PUNCTUATION_TYPE, ',')) {
				break;
			}
		}

		return new Node($targets);
	}


	private function parseNotTestExpression(Node $node): NotUnary
	{
		return new NotUnary($this->parseTestExpression($node), $this->parser->getCurrentToken()->getLine());
	}


	private function parseTestExpression(Node $node): TestExpression
	{
		$stream = $this->parser->getStream();
		[$name, $test] = $this->getTest($node->getTemplateLine());

		$class = $this->getTestNodeClass($test);
		$arguments = null;
		if ($stream->test(Token::PUNCTUATION_TYPE, '(')) {
			$arguments = $this->parseArguments(true);
		} elseif ($test->hasOneMandatoryArgument()) {
			$arguments = new Node([0 => $this->parsePrimaryExpression()]);
		}

		if (
			$name === 'defined'
			&& $node instanceof NameExpression
			&& null !== $alias = $this->parser->getImportedSymbol('function', $node->getAttribute('name'))
		) {
			$node = new MethodCallExpression($alias['node'], $alias['name'], new ArrayExpression([], $node->getTemplateLine()), $node->getTemplateLine());
			$node->setAttribute('safe', true);
		}

		return new $class($node, $name, $arguments, $this->parser->getCurrentToken()->getLine());
	}


	private function getTest(int $line): array
	{
		$stream = $this->parser->getStream();
		$name = $stream->expect(Token::NAME_TYPE)->getValue();

		if ($test = $this->env->getTest($name)) {
			return [$name, $test];
		}

		if ($stream->test(Token::NAME_TYPE)) {
			// try 2-words tests
			$name .= ' ' . $this->parser->getCurrentToken()->getValue();

			if ($test = $this->env->getTest($name)) {
				$stream->next();

				return [$name, $test];
			}
		}

		$e = new SyntaxError(sprintf('Unknown "%s" test.', $name), $line, $stream->getSourceContext());
		$e->addSuggestions($name, array_keys($this->env->getTests()));

		throw $e;
	}


	private function getTestNodeClass(TwigTest $test): string
	{
		if ($test->isDeprecated()) {
			$stream = $this->parser->getStream();
			$message = sprintf('Twig Test "%s" is deprecated', $test->getName());

			if ($test->getDeprecatedVersion()) {
				$message .= sprintf(' since version %s', $test->getDeprecatedVersion());
			}
			if ($test->getAlternative()) {
				$message .= sprintf('. Use "%s" instead', $test->getAlternative());
			}
			$src = $stream->getSourceContext();
			$message .= sprintf(' in %s at line %d.', $src->getPath() ?: $src->getName(), $stream->getCurrent()->getLine());

			@trigger_error($message, E_USER_DEPRECATED);
		}

		return $test->getNodeClass();
	}


	private function getFunctionNodeClass(string $name, int $line): string
	{
		if (!$function = $this->env->getFunction($name)) {
			$e = new SyntaxError(sprintf('Unknown "%s" function.', $name), $line, $this->parser->getStream()->getSourceContext());
			$e->addSuggestions($name, array_keys($this->env->getFunctions()));

			throw $e;
		}

		if ($function->isDeprecated()) {
			$message = sprintf('Twig Function "%s" is deprecated', $function->getName());
			if ($function->getDeprecatedVersion()) {
				$message .= sprintf(' since version %s', $function->getDeprecatedVersion());
			}
			if ($function->getAlternative()) {
				$message .= sprintf('. Use "%s" instead', $function->getAlternative());
			}
			$src = $this->parser->getStream()->getSourceContext();
			$message .= sprintf(' in %s at line %d.', $src->getPath() ?: $src->getName(), $line);

			@trigger_error($message, E_USER_DEPRECATED);
		}

		return $function->getNodeClass();
	}


	private function getFilterNodeClass(string $name, int $line): string
	{
		if (!$filter = $this->env->getFilter($name)) {
			$e = new SyntaxError(sprintf('Unknown "%s" filter.', $name), $line, $this->parser->getStream()->getSourceContext());
			$e->addSuggestions($name, array_keys($this->env->getFilters()));

			throw $e;
		}

		if ($filter->isDeprecated()) {
			$message = sprintf('Twig Filter "%s" is deprecated', $filter->getName());
			if ($filter->getDeprecatedVersion()) {
				$message .= sprintf(' since version %s', $filter->getDeprecatedVersion());
			}
			if ($filter->getAlternative()) {
				$message .= sprintf('. Use "%s" instead', $filter->getAlternative());
			}
			$src = $this->parser->getStream()->getSourceContext();
			$message .= sprintf(' in %s at line %d.', $src->getPath() ?: $src->getName(), $line);

			@trigger_error($message, E_USER_DEPRECATED);
		}

		return $filter->getNodeClass();
	}


	// checks that the node only contains "constant" elements
	private function checkConstantExpression(Node $node): bool
	{
		if (!($node instanceof ConstantExpression || $node instanceof ArrayExpression
			|| $node instanceof NegUnary || $node instanceof PosUnary
		)) {
			return false;
		}

		foreach ($node as $n) {
			if (!$this->checkConstantExpression($n)) {
				return false;
			}
		}

		return true;
	}
}
