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

namespace LatteTools\Twiggy;

/**
 * Holds information about a non-compiled Twig template.
 */
final class Source
{
	private $code;
	private $name;
	private $path;


	/**
	 * @param string $code The template source code
	 * @param string $name The template logical name
	 * @param string $path The filesystem path of the template if any
	 */
	public function __construct(string $code, string $name, string $path = '')
	{
		$this->code = $code;
		$this->name = $name;
		$this->path = $path;
	}


	public function getCode(): string
	{
		return $this->code;
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function getPath(): string
	{
		return $this->path;
	}
}
