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

namespace LatteTools\Twiggy\Sandbox;

/**
 * Interface that all security policy classes must implements.
 */
interface SecurityPolicyInterface
{
	/**
	 * @throws SecurityError
	 */
	public function checkSecurity($tags, $filters, $functions): void;

	/**
	 * @throws SecurityNotAllowedMethodError
	 */
	public function checkMethodAllowed($obj, $method): void;

	/**
	 * @throws SecurityNotAllowedPropertyError
	 */
	public function checkPropertyAllowed($obj, $method): void;
}
