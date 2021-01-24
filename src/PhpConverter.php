<?php

declare(strict_types=1);

namespace LatteTools;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;


class PhpConverter
{
	/** @var Node\Stmt[] */
	private $stmts;


	public function convert(string $code): string
	{
		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
		$this->stmts = $parser->parse($code);

		$this->expandEcho();
		$this->removeHtmlSpecialChars();
		$this->expandConcat();
		$this->removeHtmlSpecialChars();
		$this->stringToHtml();

		$printer = new LattePrinter;
		$code = $printer->prettyPrintFile($this->stmts);
		return $code;
	}


	private function expandEcho(): void
	{
		// echo a, b; => echo a; echo b;
		$traverser = new NodeTraverser;
		$traverser->addVisitor(
			new class extends NodeVisitorAbstract {
				public function leaveNode(Node $node)
				{
					if ($node instanceof Node\Stmt\Echo_
						&& count($node->exprs) > 1
					) {
						$res = [];
						foreach ($node->exprs as $expr) {
							$res[] = new Node\Stmt\Echo_([$expr]);
						}
						return $res;
					}
				}
			},
		);
		$this->stmts = $traverser->traverse($this->stmts);
	}


	private function removeHtmlSpecialChars(): void
	{
		// echo htmlspecialchars(...) => echo ...
		$traverser = new NodeTraverser;
		$traverser->addVisitor(
			new class extends NodeVisitorAbstract {
				public function leaveNode(Node $node)
				{
					if ($node instanceof Node\Stmt\Echo_
						&& $node->exprs[0] instanceof Node\Expr\FuncCall
						&& $node->exprs[0]->name->toLowerString() === 'htmlspecialchars'
					) {
						$res = [];
						foreach ($node->exprs[0]->args as $expr) {
							$res[] = new Node\Stmt\Echo_([$expr->value]);
						}
						return $res;
					}
				}
			},
		);
		$this->stmts = $traverser->traverse($this->stmts);
	}


	private function expandConcat(): void
	{
		// echo a . b; => echo a; echo b;
		$traverser = new NodeTraverser;
		$traverser->addVisitor(
			new class extends NodeVisitorAbstract {
				public function leaveNode(Node $node)
				{
					if ($node instanceof Node\Stmt\Echo_
						&& $node->exprs[0] instanceof Node\Expr\BinaryOp\Concat
					) {
						return $this->explodeConcat($node->exprs[0]);
					}
				}


				public function explodeConcat(Node\Expr\BinaryOp\Concat $expr): array
				{
					$res = $expr->left instanceof Node\Expr\BinaryOp\Concat
						? $this->explodeConcat($expr->left)
						: [new Node\Stmt\Echo_([$expr->left])];
					$res[] = new Node\Stmt\Echo_([$expr->right]);
					return $res;
				}
			},
		);
		$this->stmts = $traverser->traverse($this->stmts);
	}


	private function stringToHtml(): void
	{
		// echo 'string' => ? >string< ?php
		$traverser = new NodeTraverser;
		$traverser->addVisitor(
			new class extends NodeVisitorAbstract {
				public function leaveNode(Node $node)
				{
					if ($node instanceof Node\Stmt\Echo_
						&& ($node->exprs[0] instanceof Node\Scalar\String_
							|| $node->exprs[0] instanceof Node\Scalar\DNumber
							|| $node->exprs[0] instanceof Node\Scalar\LNumber)
					) {
						return new Node\Stmt\InlineHTML((string) $node->exprs[0]->value);
					}
				}
			},
		);
		$this->stmts = $traverser->traverse($this->stmts);
	}
}
