<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

if (!isset($_SERVER['argv'][1])) {
	echo <<<'XX'
	Converts PHP to Latte.
	Usage: php-to-latte.php input.php [output.latte]

	XX;
	exit(1);
}


$inputFile = $_SERVER['argv'][1];
$outputFile = $_SERVER['argv'][2] ?? (basename($inputFile, '.php') . '.latte');

if (!is_file($inputFile)) {
	echo "File not found $inputFile\n";
	exit(1);
}

if (is_file($outputFile)) {
	rename($outputFile, $outputFile . '.bak');
}


$converter = new LatteTools\PhpConverter;
$code = file_get_contents($inputFile);
$res = $converter->convert($code);
file_put_contents($outputFile, $res);

echo "Saved to $outputFile\n";
