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

namespace LatteTools\Twiggy\Extension;

use LatteTools\Twiggy\NodeVisitor\NodeVisitorInterface;
use LatteTools\Twiggy\TokenParser\TokenParserInterface;
use LatteTools\Twiggy\TwigFilter;
use LatteTools\Twiggy\TwigFunction;
use LatteTools\Twiggy\TwigTest;

/**
 * Interface implemented by extension classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ExtensionInterface
{
	/**
	 * Returns the token parser instances to add to the existing list.
	 *
	 * @return TokenParserInterface[]
	 */
	public function getTokenParsers();

	/**
	 * Returns the node visitor instances to add to the existing list.
	 *
	 * @return NodeVisitorInterface[]
	 */
	public function getNodeVisitors();

	/**
	 * Returns a list of filters to add to the existing list.
	 *
	 * @return TwigFilter[]
	 */
	public function getFilters();

	/**
	 * Returns a list of tests to add to the existing list.
	 *
	 * @return TwigTest[]
	 */
	public function getTests();

	/**
	 * Returns a list of functions to add to the existing list.
	 *
	 * @return TwigFunction[]
	 */
	public function getFunctions();

	/**
	 * Returns a list of operators to add to the existing list.
	 *
	 * @return array<array> First array of unary operators, second array of binary operators
	 */
	public function getOperators();
}
