<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();

$converter = new LatteTools\TwigConverter;

foreach (Nette\Utils\Finder::findFiles('*.twig')->from(__DIR__ . '/fixtures-twig') as $file) {
	$file = (string) $file;
	echo $file, "\n";
	$code = file_get_contents($file);
	$expectedFile = str_replace('.twig', '.latte', $file);
	$expected = file_get_contents($expectedFile);

	$res = $converter->convert($code);
	Assert::match($expected, $res);
}
