<?php

/**
 * Rules for Nette Coding Standard
 * https://github.com/nette/coding-standard
 */

declare(strict_types=1);


return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $containerConfigurator): void {
	$containerConfigurator->import(PRESET_DIR . '/php74.php');

	$parameters = $containerConfigurator->parameters();

	$parameters->set('skip', [
		PHP_CodeSniffer\Standards\PSR1\Sniffs\Methods\CamelCapsMethodNameSniff::class => [
			'src/LattePrinter.php',
		],
	]);
};
