<?php declare(strict_types=1);

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
		$this->mergeNestedIfs(); // Merge nested ifs before converting to n:attr
		$this->convertConditionalAttributes(); // Run before stringToHtml to catch Echo patterns
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
						&& $node->exprs[0]->name instanceof Node\Name
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
		// But DON'T expand if the concatenation contains string literals (likely HTML)
		$traverser = new NodeTraverser;
		$traverser->addVisitor(
			new class extends NodeVisitorAbstract {
				public function leaveNode(Node $node)
				{
					if ($node instanceof Node\Stmt\Echo_
						&& $node->exprs[0] instanceof Node\Expr\BinaryOp\Concat
						&& !$this->hasStringLiteral($node->exprs[0])
					) {
						return $this->explodeConcat($node->exprs[0]);
					}
				}

				private function hasStringLiteral(Node\Expr $expr): bool
				{
					if ($expr instanceof Node\Scalar\String_) {
						return true;
					} elseif ($expr instanceof Node\Expr\BinaryOp\Concat) {
						return $this->hasStringLiteral($expr->left) || $this->hasStringLiteral($expr->right);
					} else {
						return false;
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
					if ($node instanceof Node\Stmt\Echo_) {
						// Handle simple strings, numbers
						if ($node->exprs[0] instanceof Node\Scalar\String_
							|| $node->exprs[0] instanceof Node\Scalar\DNumber
							|| $node->exprs[0] instanceof Node\Scalar\LNumber
						) {
							return new Node\Stmt\InlineHTML((string) $node->exprs[0]->value);
						}

						// Handle Encapsed strings (strings with embedded variables)
						if ($node->exprs[0] instanceof Node\Scalar\Encapsed
							&& $node->exprs[0]->getAttribute('kind') === Node\Scalar\String_::KIND_DOUBLE_QUOTED
						) {
							$html = $this->convertEncapsedToHtml($node->exprs[0]);
							if ($html !== null) {
								return new Node\Stmt\InlineHTML($html);
							}
						}

						// Handle Concat expressions (e.g., echo '<div>' . $var . '</div>')
						if ($node->exprs[0] instanceof Node\Expr\BinaryOp\Concat) {
							$html = $this->convertConcatToHtml($node->exprs[0]);
							if ($html !== null) {
								return new Node\Stmt\InlineHTML($html);
							}
						}

						// Handle simple variable echoes (e.g., echo $user->id)
						if ($node->exprs[0] instanceof Node\Expr\Variable
							|| $node->exprs[0] instanceof Node\Expr\PropertyFetch
							|| $node->exprs[0] instanceof Node\Expr\ArrayDimFetch
						) {
							$html = $this->buildExprString($node->exprs[0]);
							return new Node\Stmt\InlineHTML('{' . $html . '}');
						}
					}
				}

				private function convertConcatToHtml(Node\Expr\BinaryOp\Concat $concat): ?string
				{
					$html = '';

					// Process left side
					$leftHtml = $this->exprToHtml($concat->left);
					if ($leftHtml === null) {
						return null;
					}
					$html .= $leftHtml;

					// Process right side
					$rightHtml = $this->exprToHtml($concat->right);
					if ($rightHtml === null) {
						return null;
					}
					$html .= $rightHtml;

					return $html;
				}

				private function exprToHtml(Node\Expr $expr): ?string
				{
					if ($expr instanceof Node\Scalar\String_) {
						return $expr->value;
					} elseif ($expr instanceof Node\Scalar\DNumber || $expr instanceof Node\Scalar\LNumber) {
						return (string) $expr->value;
					} elseif ($expr instanceof Node\Scalar\Encapsed
						&& $expr->getAttribute('kind') === Node\Scalar\String_::KIND_DOUBLE_QUOTED
					) {
						return $this->convertEncapsedToHtml($expr);
					} elseif ($expr instanceof Node\Expr\BinaryOp\Concat) {
						return $this->convertConcatToHtml($expr);
					} elseif ($expr instanceof Node\Expr\Variable) {
						return '{$' . $expr->name . '}';
					} elseif ($expr instanceof Node\Expr\PropertyFetch) {
						return '{' . $this->buildExprString($expr) . '}';
					} elseif ($expr instanceof Node\Expr\ArrayDimFetch) {
						return '{' . $this->buildExprString($expr) . '}';
					} elseif ($expr instanceof Node\Expr\FuncCall) {
						// Handle htmlspecialchars() and other function calls
						// For htmlspecialchars($var), we want {$var} (the htmlspecialchars is redundant in Latte)
						if ($expr->name instanceof Node\Name
							&& $expr->name->toLowerString() === 'htmlspecialchars'
							&& count($expr->args) > 0
						) {
							$arg = $expr->args[0]->value;
							return $this->exprToHtml($arg);
						}
						// For other function calls, use Latte syntax
						return '{' . $this->buildExprString($expr) . '}';
					} else {
						// For other expressions, we can't safely convert to InlineHTML
						return null;
					}
				}

				private function convertEncapsedToHtml(Node\Scalar\Encapsed $encapsed): ?string
				{
					$html = '';
					foreach ($encapsed->parts as $part) {
						if ($part instanceof Node\Scalar\EncapsedStringPart) {
							$html .= $part->value;
						} elseif ($part instanceof Node\Expr\Variable) {
							// Convert $var to {$var}
							$html .= '{$' . $part->name . '}';
						} elseif ($part instanceof Node\Expr\PropertyFetch) {
							// Convert $obj->prop to {$obj->prop}
							$html .= '{' . $this->buildExprString($part) . '}';
						} else {
							// For other expressions, we can't safely convert to InlineHTML
							return null;
						}
					}
					return $html;
				}

				private function buildExprString(Node $expr): string
				{
					if ($expr instanceof Node\Expr\Variable) {
						return '$' . $expr->name;
					} elseif ($expr instanceof Node\Expr\PropertyFetch) {
						return $this->buildExprString($expr->var) . '->' . $expr->name->name;
					} elseif ($expr instanceof Node\Expr\ArrayDimFetch) {
						$key = $expr->dim !== null ? '[' . $this->buildExprString($expr->dim) . ']' : '';
						return $this->buildExprString($expr->var) . $key;
					} elseif ($expr instanceof Node\Scalar\String_) {
						return "'" . addcslashes($expr->value, "'\\") . "'";
					} elseif ($expr instanceof Node\Expr\FuncCall) {
						$args = [];
						foreach ($expr->args as $arg) {
							$args[] = $this->buildExprString($arg->value);
						}
						return $this->buildExprString($expr->name) . '(' . implode(', ', $args) . ')';
					} elseif ($expr instanceof Node\Name) {
						return $expr->toString();
					} else {
						// For other expressions, use a placeholder
						return '...';
					}
				}
			},
		);
		$this->stmts = $traverser->traverse($this->stmts);
	}


	private function convertConditionalAttributes(): void
	{
		// Transform conditional attributes inside HTML elements to n:attr notation
		// Pattern: InlineHTML → If (isset/!empty) → InlineHTML
		// Where the If outputs a value inside an HTML attribute
		// Process recursively to handle nested structures
		$this->convertConditionalAttributesRecursive($this->stmts);
	}


	/**
	 * Recursively convert conditional attributes in a statement array
	 * @param Node\Stmt[] $stmts
	 */
	private function convertConditionalAttributesRecursive(array &$stmts): void
	{
		// First, recursively process all nested statement blocks
		foreach ($stmts as $stmt) {
			if ($stmt instanceof Node\Stmt\If_) {
				$this->convertConditionalAttributesRecursive($stmt->stmts);
				foreach ($stmt->elseifs as $elseif) {
					$this->convertConditionalAttributesRecursive($elseif->stmts);
				}
				if ($stmt->else !== null) {
					$this->convertConditionalAttributesRecursive($stmt->else->stmts);
				}
			} elseif ($stmt instanceof Node\Stmt\Foreach_) {
				$this->convertConditionalAttributesRecursive($stmt->stmts);
			} elseif ($stmt instanceof Node\Stmt\For_) {
				$this->convertConditionalAttributesRecursive($stmt->stmts);
			} elseif ($stmt instanceof Node\Stmt\While_) {
				$this->convertConditionalAttributesRecursive($stmt->stmts);
			} elseif ($stmt instanceof Node\Stmt\TryCatch) {
				$this->convertConditionalAttributesRecursive($stmt->stmts);
				foreach ($stmt->catches as $catch) {
					$this->convertConditionalAttributesRecursive($catch->stmts);
				}
				if ($stmt->finally !== null) {
					$this->convertConditionalAttributesRecursive($stmt->finally->stmts);
				}
			}
		}

		// Now process this level's statements
		// We need to use a temporary variable since we're modifying the array we're iterating
		$this->convertConditionalAttributesInCurrentScope($stmts);
	}


	/**
	 * Convert conditional attributes at the current scope level
	 * @param Node\Stmt[] $stmts
	 */
	private function convertConditionalAttributesInCurrentScope(array &$stmts): void
	{
		// We need to repeat the scan because transforming one pattern may create
		// a new pattern at the end of the last HTML
		$changed = true;
		while ($changed) {
			$changed = false;
			$newStmts = [];
			$i = 0;

			while ($i < count($stmts)) {
				$stmt = $stmts[$i];

				// Check if this is the start of a conditional attribute pattern
				if ($stmt instanceof Node\Stmt\InlineHTML) {
					$pattern = $this->findConditionalAttributePatternInArray($stmts, $i);

					if ($pattern !== null) {
						// Look for additional consecutive conditional attributes on the same element
						$pattern = $this->findAdditionalConditionalAttributesInArray($stmts, $pattern);

						// We found a pattern, transform it
						$transformed = $this->transformConditionalAttributes($pattern);
						foreach ($transformed as $newStmt) {
							$newStmts[] = $newStmt;
						}
						$i = $pattern['endIndex'] + 1;
						$changed = true;
						continue;
					}
				}

				$newStmts[] = $stmt;
				$i++;
			}

			$stmts = $newStmts;
		}
	}


	/**
	 * Find conditional attribute pattern starting at given index in a specific array
	 * @param Node\Stmt[] $stmts
	 */
	private function findConditionalAttributePatternInArray(array $stmts, int $startIndex): ?array
	{
		$count = count($stmts);

		if ($startIndex >= $count) {
			return null;
		}

		$firstHtml = $stmts[$startIndex];
		if (!$firstHtml instanceof Node\Stmt\InlineHTML) {
			return null;
		}

		$html = $firstHtml->value;

		// Check if next statement is an If with isset/!empty
		if ($startIndex + 1 >= $count) {
			return null;
		}

		$nextStmt = $stmts[$startIndex + 1];
		if (!$nextStmt instanceof Node\Stmt\If_) {
			return null;
		}

		$ifStmt = $nextStmt;

		// Check if this is isset() or !empty() condition
		$condition = $this->extractIssetOrEmptyCondition($ifStmt);
		if ($condition === null) {
			return null;
		}

		// Extract the expression being echoed inside the If
		$echoExpr = $this->extractEchoExpression($ifStmt);
		if ($echoExpr === null) {
			return null;
		}

		// Extract attribute name from the HTML ending
		$attrName = $this->extractAttributeName($html);
		if ($attrName === null) {
			return null;
		}

		// Check if there's HTML after the If (closing quote)
		if ($startIndex + 2 >= $count) {
			return null;
		}

		$afterHtml = $stmts[$startIndex + 2];
		if (!$afterHtml instanceof Node\Stmt\InlineHTML) {
			return null;
		}

		// Check that the HTML after starts with a quote
		$afterValue = ltrim($afterHtml->value);
		if (!str_starts_with($afterValue, '"') && !str_starts_with($afterValue, "'")) {
			return null;
		}

		// We found a single conditional attribute
		return [
			'startIndex' => $startIndex,
			'endIndex' => $startIndex + 2,
			'firstHtml' => $stmts[$startIndex],
			'lastHtml' => $stmts[$startIndex + 2],
			'attributes' => [
				[
					'attrName' => $attrName,
					'expression' => $echoExpr,
					'condition' => $condition,
				],
			],
		];
	}


	/**
	 * Find additional consecutive conditional attributes on the same element
	 * @param Node\Stmt[] $stmts
	 */
	private function findAdditionalConditionalAttributesInArray(array $stmts, array $pattern): array
	{
		$currentEndIndex = $pattern['endIndex'];
		$lastHtml = $pattern['lastHtml'];

		// Continue looking for more patterns as long as we haven't closed the element
		while (true) {
			// Check if we're still inside the same HTML element
			// The lastHtml should not contain '>' (element close)
			if (strpos($lastHtml->value, '>') !== false) {
				break;
			}

			// Try to find another conditional attribute pattern starting from current position
			$nextPattern = $this->findConditionalAttributePatternAtInArray($stmts, $currentEndIndex);

			if ($nextPattern === null) {
				break;
			}

			// Merge the attributes
			$pattern['attributes'] = array_merge($pattern['attributes'], $nextPattern['attributes']);
			$pattern['endIndex'] = $nextPattern['endIndex'];
			// Use the next pattern's lastHtml for the merged pattern
			$pattern['lastHtml'] = $nextPattern['lastHtml'];
			$lastHtml = $nextPattern['lastHtml'];
			$currentEndIndex = $nextPattern['endIndex'];
		}

		return $pattern;
	}


	/**
	 * Find conditional attribute pattern at a specific index
	 * Used when continuing to scan within the same HTML element
	 * @param Node\Stmt[] $stmts
	 */
	private function findConditionalAttributePatternAtInArray(array $stmts, int $startIndex): ?array
	{
		$count = count($stmts);

		if ($startIndex >= $count) {
			return null;
		}

		// The HTML at startIndex is the continuation of the element
		$firstHtml = $stmts[$startIndex];
		if (!$firstHtml instanceof Node\Stmt\InlineHTML) {
			return null;
		}

		$html = $firstHtml->value;

		// Must have an attribute pattern ending with =" or ='
		$attrName = $this->extractAttributeName($html);
		if ($attrName === null) {
			return null;
		}

		// Check if next statement is an If with isset/!empty
		if ($startIndex + 1 >= $count) {
			return null;
		}

		$nextStmt = $stmts[$startIndex + 1];
		if (!$nextStmt instanceof Node\Stmt\If_) {
			return null;
		}

		$ifStmt = $nextStmt;

		// Check if this is isset() or !empty() condition
		$condition = $this->extractIssetOrEmptyCondition($ifStmt);
		if ($condition === null) {
			return null;
		}

		// Extract the expression being echoed inside the If
		$echoExpr = $this->extractEchoExpression($ifStmt);
		if ($echoExpr === null) {
			return null;
		}

		// Check if there's HTML after the If (closing quote)
		if ($startIndex + 2 >= $count) {
			return null;
		}

		$afterHtml = $stmts[$startIndex + 2];
		if (!$afterHtml instanceof Node\Stmt\InlineHTML) {
			return null;
		}

		// Check that the HTML after starts with a quote
		$afterValue = ltrim($afterHtml->value);
		if (!str_starts_with($afterValue, '"') && !str_starts_with($afterValue, "'")) {
			return null;
		}

		return [
			'startIndex' => $startIndex,
			'endIndex' => $startIndex + 2,
			'firstHtml' => $stmts[$startIndex],
			'lastHtml' => $stmts[$startIndex + 2],
			'attributes' => [
				[
					'attrName' => $attrName,
					'expression' => $echoExpr,
					'condition' => $condition,
				],
			],
		];
	}


	/**
	 * Extract isset() or !empty() condition from an If statement
	 * Returns the variable expression being checked, or null
	 */
	private function extractIssetOrEmptyCondition(Node\Stmt\If_ $ifStmt): ?Node\Expr
	{
		$cond = $ifStmt->cond;

		// Check for isset($var) or !empty($var)
		if ($cond instanceof Node\Expr\Isset_) {
			return $cond->vars[0] ?? null;
		}

		if ($cond instanceof Node\Expr\BooleanNot
			&& $cond->expr instanceof Node\Expr\Empty_
		) {
			return $cond->expr->expr;
		}

		return null;
	}


	/**
	 * Extract the expression being echoed from an If statement body
	 */
	private function extractEchoExpression(Node\Stmt\If_ $ifStmt): ?Node\Expr
	{
		$stmts = $ifStmt->stmts;

		// Filter out Nop statements
		$realStmts = array_filter($stmts, fn($s) => !$s instanceof Node\Stmt\Nop);

		if (count($realStmts) !== 1) {
			return null;
		}

		$stmt = $realStmts[0];

		// Must be a single echo statement
		if (!$stmt instanceof Node\Stmt\Echo_) {
			return null;
		}

		if (count($stmt->exprs) !== 1) {
			return null;
		}

		return $stmt->exprs[0];
	}


	/**
	 * Extract attribute name from HTML ending with attribute="
	 *
	 * This method parses HTML that ends with an attribute assignment to extract
	 * the attribute name. It's used when converting conditional attribute patterns
	 * like: <input value="<?php if (isset($val)) echo $val; ?>">
	 *
	 * The regex pattern '/\s([a-zA-Z_:][a-zA-Z0-9_:\-]*)\s*=\s*["\']$/' matches:
	 * - \s - leading whitespace (space, tab, newline)
	 * - ([a-zA-Z_:][a-zA-Z0-9_:\-]*) - capture group for the attribute name:
	 *   - [a-zA-Z_:] - must start with letter, underscore, or colon
	 *   - [a-zA-Z0-9_:\-]* - followed by letters, digits, underscores, colons, or hyphens
	 * - \s*=\s* - equals sign with optional surrounding whitespace
	 * - ["\'] - opening quote (single or double)
	 * - $ - end of string
	 *
	 * Examples:
	 * - ' value="'          -> matches 'value'
	 * - '  class="'         -> matches 'class'
	 * - 'data-id="'         -> matches 'data-id'
	 * - 'xlink:href="'      -> matches 'xlink:href'
	 * - ' aria-label="'     -> matches 'aria-label'
	 * - ' style = "'        -> matches 'style' (with spaces around =)
	 * - " type='"           -> matches 'type' (single quote)
	 *
	 * Non-matches (returns null):
	 * - '<input'           -> no attribute pattern
	 * - ' value'           -> no equals sign
	 * - ' value='          -> no opening quote
	 * - ' 123attr="'       -> starts with digit (invalid attribute name)
	 *
	 * @param string $html The HTML string ending with an attribute assignment
	 * @return string|null The attribute name if matched, null otherwise
	 */
	private function extractAttributeName(string $html): ?string
	{
		// Match attribute name at end of string: ... name=" or ... name='
		if (preg_match('/\s([a-zA-Z_:][a-zA-Z0-9_:\-]*)\s*=\s*["\']$/', $html, $matches)) {
			return $matches[1];
		}

		return null;
	}


	/**
	 * Transform conditional attribute pattern to n:attr notation
	 */
	private function transformConditionalAttributes(array $pattern): array
	{
		$firstHtml = $pattern['firstHtml'];
		$lastHtml = $pattern['lastHtml'];
		$attributes = $pattern['attributes'];

		// Build n:attr value
		$attrParts = [];
		foreach ($attributes as $attr) {
			$exprStr = $this->buildExprString($attr['expression']);
			$attrParts[] = $attr['attrName'] . ': ' . $exprStr;
		}

		$nAttrValue = implode(', ', $attrParts);

		// Build the first HTML without the attribute and opening quote
		$firstValue = $firstHtml->value;

		// Find and remove the entire attribute=" or attribute=' at the end
		// The pattern is: ... attribute="
		// We remove the whole attribute name and equals sign
		$firstValue = preg_replace('/\s+[a-zA-Z_:][a-zA-Z0-9_:\-]*\s*=\s*["\']$/', '', $firstValue);

		// Build result statements
		$result = [];

		// First HTML (without the quote)
		$result[] = new Node\Stmt\InlineHTML($firstValue);

		// n:attr="..."
		$nAttrHtml = ' n:attr="' . $nAttrValue . '"';
		$result[] = new Node\Stmt\InlineHTML($nAttrHtml);

		// Last HTML (after the closing quote)
		$lastValue = $lastHtml->value;
		// Remove the leading quote
		$lastValue = preg_replace('/^\s*["\']/', '', $lastValue);
		$result[] = new Node\Stmt\InlineHTML($lastValue);

		return $result;
	}


	private function buildExprString(Node $expr): string
	{
		if ($expr instanceof Node\Expr\Variable) {
			return '$' . $expr->name;
		} elseif ($expr instanceof Node\Expr\PropertyFetch) {
			return $this->buildExprString($expr->var) . '->' . $expr->name->name;
		} elseif ($expr instanceof Node\Expr\ArrayDimFetch) {
			$key = $expr->dim !== null ? '[' . $this->buildExprString($expr->dim) . ']' : '';
			return $this->buildExprString($expr->var) . $key;
		} elseif ($expr instanceof Node\Scalar\String_) {
			return "'" . addcslashes($expr->value, "'\\") . "'";
		} elseif ($expr instanceof Node\Expr\FuncCall) {
			$args = [];
			foreach ($expr->args as $arg) {
				$args[] = $this->buildExprString($arg->value);
			}
			return $this->buildExprString($expr->name) . '(' . implode(', ', $args) . ')';
		} elseif ($expr instanceof Node\Name) {
			return $expr->toString();
		} elseif ($expr instanceof Node\Expr\MethodCall) {
			$args = [];
			foreach ($expr->args as $arg) {
				$args[] = $this->buildExprString($arg->value);
			}
			return $this->buildExprString($expr->var) . '->' . $expr->name->name . '(' . implode(', ', $args) . ')';
		} else {
			// For other expressions, use a placeholder
			return '...';
		}
	}


	private function mergeNestedIfs(): void
	{
		// Merge nested if statements into a single if with combined conditions
		// Pattern: if (A) { if (B) { <element> } } -> if (A && B) { <element> }

		$traverser = new NodeTraverser;
		$traverser->addVisitor(
			new class($this) extends NodeVisitorAbstract {
				private $converter;

				public function __construct($converter)
				{
					$this->converter = $converter;
				}

				public function leaveNode(Node $node)
				{
					if (!$node instanceof Node\Stmt\If_) {
						return null;
					}

					// Check if this if has no elseif/else and contains a single inner if
					if (!empty($node->elseifs) || $node->else !== null) {
						return null;
					}

					// Get real statements (filter out Nop and whitespace-only InlineHTML)
					$stmts = array_filter($node->stmts, function($s) {
						if ($s instanceof Node\Stmt\Nop) {
							return false;
						}
						if ($s instanceof Node\Stmt\InlineHTML && trim($s->value) === '') {
							return false;
						}
						return true;
					});

					// Must have exactly one statement which is an If
					if (count($stmts) !== 1) {
						return null;
					}

					$innerStmt = reset($stmts);
					if (!$innerStmt instanceof Node\Stmt\If_) {
						return null;
					}

					$innerIf = $innerStmt;

					// Inner if must also have no elseif/else
					if (!empty($innerIf->elseifs) || $innerIf->else !== null) {
						return null;
					}

					// Create combined condition: outer && inner
					$combinedCond = new Node\Expr\BinaryOp\BooleanAnd(
						$node->cond,
						$innerIf->cond
					);

					// Return new if with combined condition and inner body
					return new Node\Stmt\If_($combinedCond, [
						'stmts' => $innerIf->stmts,
					]);
				}
			},
		);
		$this->stmts = $traverser->traverse($this->stmts);
	}
}
