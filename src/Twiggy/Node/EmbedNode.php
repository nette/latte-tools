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

namespace LatteTools\Twiggy\Node;

use LatteTools\Twiggy\Compiler;
use LatteTools\Twiggy\Node\Expression\AbstractExpression;
use LatteTools\Twiggy\Node\Expression\ConstantExpression;

/**
 * Represents an embed node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EmbedNode extends IncludeNode
{
	public function __construct(
		string $name,
		int $index,
		?AbstractExpression $variables,
		bool $only,
		bool $ignoreMissing,
		int $lineno,
		string $tag = null,
		Node $body,
		Node $parent
	) {
		parent::__construct(new ConstantExpression('not_used', $lineno), $variables, $only, $ignoreMissing, $lineno, $tag);

		$this->setAttribute('name', $name);
		$this->setAttribute('index', $index);
		$this->setAttribute('parent', $parent);
		$this->setAttribute('body', $body);
	}


	protected function addGetTemplate(Compiler $compiler): void
	{
		$compiler
			->raw('{embed ')
			->filename($this->getAttribute('parent'));

		$this->addTemplateArguments($compiler);

		$compiler
			->raw("}\n")
			->subcompile($this->getAttribute('body'))
			->raw('{/embed}')
		;
	}
}
