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

namespace LatteTools\Twiggy\Extension {
use LatteTools\Twiggy\TwigFunction;

final class DebugExtension extends AbstractExtension
{
	public function getFunctions(): array
	{
		// dump is safe if var_dump is overridden by xdebug
		$isDumpOutputHtmlSafe = \extension_loaded('xdebug')
			// false means that it was not set (and the default is on) or it explicitly enabled
			&& (ini_get('xdebug.overload_var_dump') === false || ini_get('xdebug.overload_var_dump'))
			// false means that it was not set (and the default is on) or it explicitly enabled
			// xdebug.overload_var_dump produces HTML only when html_errors is also enabled
			&& (ini_get('html_errors') === false || ini_get('html_errors'))
			|| \PHP_SAPI === 'cli'
		;

		return [
			new TwigFunction('dump', 'twig_var_dump', ['is_safe' => $isDumpOutputHtmlSafe ? ['html'] : [], 'needs_context' => true, 'needs_environment' => true, 'is_variadic' => true]),
		];
	}
}
}

namespace {
use LatteTools\Twiggy\Environment;
use LatteTools\Twiggy\Template;
use LatteTools\Twiggy\TemplateWrapper;

function twig_var_dump(Environment $env, $context, ...$vars)
{
	if (!$env->isDebug()) {
		return;
	}

	ob_start();

	if (!$vars) {
		$vars = [];
		foreach ($context as $key => $value) {
			if (!$value instanceof Template && !$value instanceof TemplateWrapper) {
				$vars[$key] = $value;
			}
		}
	} else {
	}

	return ob_get_clean();
}
}
