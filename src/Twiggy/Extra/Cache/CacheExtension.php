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

namespace LatteTools\Twiggy\Extra\Cache;

use LatteTools\Twiggy\Extension\AbstractExtension;
use LatteTools\Twiggy\Extra\Cache\TokenParser\CacheTokenParser;

final class CacheExtension extends AbstractExtension
{
	public function getTokenParsers()
	{
		return [
			new CacheTokenParser,
		];
	}
}
