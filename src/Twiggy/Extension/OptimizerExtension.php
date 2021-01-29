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

use LatteTools\Twiggy\NodeVisitor\OptimizerNodeVisitor;

final class OptimizerExtension extends AbstractExtension
{
	private $optimizers;


	public function __construct(int $optimizers = -1)
	{
		$this->optimizers = $optimizers;
	}


	public function getNodeVisitors(): array
	{
		return [new OptimizerNodeVisitor($this->optimizers)];
	}
}
