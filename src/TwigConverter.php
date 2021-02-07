<?php

declare(strict_types=1);

namespace LatteTools;


class TwigConverter
{
	public Twiggy\Environment $twiggy;


	public function __construct()
	{
		$loader = new Twiggy\Loader\ArrayLoader;
		$twiggy = new Twiggy\Environment($loader, ['cache' => false]);
		$twiggy->addExtension(new Twiggy\Extra\Cache\CacheExtension);
		$twiggy->addExtension(new Twiggy\Extra\Html\HtmlExtension);
		$twiggy->addExtension(new Twiggy\Extension\DebugExtension);
		$twiggy->addExtension(new Twiggy\Extension\SandboxExtension(new Twiggy\Sandbox\SecurityPolicy));
		$twiggy->registerUndefinedFilterCallback(fn ($name) => new Twiggy\TwigFilter($name, function () {}));
		$twiggy->registerUndefinedFunctionCallback(fn ($name) => new Twiggy\TwigFunction($name, function () {}));
		$twiggy->addNodeVisitor(new Twiggy\NodeVisitor\LatteNodeVisitor);
		$this->twiggy = $twiggy;
	}


	public function convert(string $code): string
	{
		$loader = $this->twiggy->getLoader();
		$loader->setTemplate('main', $code);
		$code = $this->twiggy->compileSource($loader->getSourceContext('main'));
		$code = $this->postProcess($code);
		return $code;
	}


	private function postProcess(string $code): string
	{
		$code = preg_replace('~\bclass=(["\']){html_classes\((.*)\)}~i', 'n:class=$1$2', $code);
		return $code;
	}
}
