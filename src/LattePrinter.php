<?php declare(strict_types=1);

namespace LatteTools;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinterAbstract;


class LattePrinter extends PrettyPrinterAbstract
{
	public function prettyPrintFile(array $stmts): string
	{
		return $this->prettyPrint($stmts);
	}


	// Special nodes

	protected function pParam(Node\Param $node)
	{
		return $this->pAttrGroups($node->attrGroups, true)
			. $this->pModifiers($node->flags)
			. ($node->type ? $this->p($node->type) . ' ' : '')
			. ($node->byRef ? '&' : '')
			. ($node->variadic ? '...' : '')
			. $this->p($node->var)
			. ($node->default ? ' = ' . $this->p($node->default) : '');
	}


	protected function pArg(Node\Arg $node)
	{
		return ($node->name ? $node->name->toString() . ': ' : '')
			. ($node->byRef ? '&' : '') . ($node->unpack ? '...' : '')
			. $this->p($node->value);
	}


	protected function pConst(Node\Const_ $node)
	{
		return $node->name . ' = ' . $this->p($node->value);
	}


	protected function pNullableType(Node\NullableType $node)
	{
		return '?' . $this->p($node->type);
	}


	protected function pUnionType(Node\UnionType $node)
	{
		return $this->pImplode($node->types, '|');
	}


	protected function pIdentifier(Node\Identifier $node)
	{
		return $node->name;
	}


	protected function pVarLikeIdentifier(Node\VarLikeIdentifier $node)
	{
		return '$' . $node->name;
	}


	protected function pAttribute(Node\Attribute $node)
	{
		return '';
	}


	protected function pAttributeGroup(Node\AttributeGroup $node)
	{
		return '';
	}


	// Names

	protected function pName(Name $node)
	{
		return implode('\\', $node->parts);
	}


	protected function pName_FullyQualified(Name\FullyQualified $node)
	{
		return '\\' . implode('\\', $node->parts);
	}


	protected function pName_Relative(Name\Relative $node)
	{
		return '{* namespace\\' . implode('\\', $node->parts) . ' *}';
	}


	// Magic Constants

	protected function pScalar_MagicConst_Class(MagicConst\Class_ $node)
	{
		return '__CLASS__';
	}


	protected function pScalar_MagicConst_Dir(MagicConst\Dir $node)
	{
		return '__DIR__';
	}


	protected function pScalar_MagicConst_File(MagicConst\File $node)
	{
		return '__FILE__';
	}


	protected function pScalar_MagicConst_Function(MagicConst\Function_ $node)
	{
		return '__FUNCTION__';
	}


	protected function pScalar_MagicConst_Line(MagicConst\Line $node)
	{
		return '__LINE__';
	}


	protected function pScalar_MagicConst_Method(MagicConst\Method $node)
	{
		return '__METHOD__';
	}


	protected function pScalar_MagicConst_Namespace(MagicConst\Namespace_ $node)
	{
		return '__NAMESPACE__';
	}


	protected function pScalar_MagicConst_Trait(MagicConst\Trait_ $node)
	{
		return '__TRAIT__';
	}


	// Scalars

	protected function pScalar_String(Scalar\String_ $node)
	{
		$kind = $node->getAttribute('kind', Scalar\String_::KIND_SINGLE_QUOTED);
		switch ($kind) {
			case Scalar\String_::KIND_NOWDOC:
				$label = $node->getAttribute('docLabel');
				if ($label && !$this->containsEndLabel($node->value, $label)) {
					if ($node->value === '') {
						return "<<<'$label'\n$label" . $this->docStringEndToken;
					}

					return "<<<'$label'\n$node->value\n$label"
						. $this->docStringEndToken;
				}
				/* break missing intentionally */
				// break omitted
			case Scalar\String_::KIND_SINGLE_QUOTED:
				return $this->pSingleQuotedString($node->value);
			case Scalar\String_::KIND_HEREDOC:
				$label = $node->getAttribute('docLabel');
				if ($label && !$this->containsEndLabel($node->value, $label)) {
					if ($node->value === '') {
						return "<<<$label\n$label" . $this->docStringEndToken;
					}

					$escaped = $this->escapeString($node->value, null);
					return "<<<$label\n" . $escaped . "\n$label"
						. $this->docStringEndToken;
				}
				/* break missing intentionally */
				// break omitted
				case Scalar\String_::KIND_DOUBLE_QUOTED:
					return '"' . $this->escapeString($node->value, '"') . '"';
		}

		throw new \Exception('Invalid string kind');
	}


	protected function pScalar_Encapsed(Scalar\Encapsed $node)
	{
		if ($node->getAttribute('kind') === Scalar\String_::KIND_HEREDOC) {
			$label = $node->getAttribute('docLabel');
			if ($label && !$this->encapsedContainsEndLabel($node->parts, $label)) {
				if (count($node->parts) === 1
					&& $node->parts[0] instanceof Scalar\EncapsedStringPart
					&& $node->parts[0]->value === ''
				) {
					return "<<<$label\n$label" . $this->docStringEndToken;
				}

				return "<<<$label\n" . $this->pEncapsList($node->parts, null) . "\n$label"
					. $this->docStringEndToken;
			}
		}
		return '"' . $this->pEncapsList($node->parts, '"') . '"';
	}


	protected function pScalar_LNumber(Scalar\LNumber $node)
	{
		if ($node->value === -\PHP_INT_MAX - 1) {
			// PHP_INT_MIN cannot be represented as a literal,
			// because the sign is not part of the literal
			return '(-' . \PHP_INT_MAX . '-1)';
		}

		$kind = $node->getAttribute('kind', Scalar\LNumber::KIND_DEC);
		if ($kind === Scalar\LNumber::KIND_DEC) {
			return (string) $node->value;
		}

		if ($node->value < 0) {
			$sign = '-';
			$str = (string) -$node->value;
		} else {
			$sign = '';
			$str = (string) $node->value;
		}
		switch ($kind) {
			case Scalar\LNumber::KIND_BIN:
				return $sign . '0b' . base_convert($str, 10, 2);
			case Scalar\LNumber::KIND_OCT:
				return $sign . '0' . base_convert($str, 10, 8);
			case Scalar\LNumber::KIND_HEX:
				return $sign . '0x' . base_convert($str, 10, 16);
		}

		throw new \Exception('Invalid number kind');
	}


	protected function pScalar_DNumber(Scalar\DNumber $node)
	{
		if (!is_finite($node->value)) {
			if ($node->value === \INF) {
				return '\INF';
			} elseif ($node->value === -\INF) {
				return '-\INF';
			} else {
				return '\NAN';
			}
		}

		// Try to find a short full-precision representation
		$stringValue = sprintf('%.16G', $node->value);
		if ($node->value !== (float) $stringValue) {
			$stringValue = sprintf('%.17G', $node->value);
		}

		// %G is locale dependent and there exists no locale-independent alternative. We don't want
		// mess with switching locales here, so let's assume that a comma is the only non-standard
		// decimal separator we may encounter...
		$stringValue = str_replace(',', '.', $stringValue);

		// ensure that number is really printed as float
		return preg_match('/^-?[0-9]+$/', $stringValue)
			? $stringValue . '.0'
			: $stringValue;
	}


	protected function pScalar_EncapsedStringPart(Scalar\EncapsedStringPart $node)
	{
		throw new \LogicException('Cannot directly print EncapsedStringPart');
	}


	// Assignments

	protected function pExpr_Assign(Expr\Assign $node)
	{
		return $this->pInfixOp(Expr\Assign::class, $node->var, ' = ', $node->expr);
	}


	protected function pExpr_AssignRef(Expr\AssignRef $node)
	{
		return $this->pInfixOp(Expr\AssignRef::class, $node->var, ' =& ', $node->expr);
	}


	protected function pExpr_AssignOp_Plus(AssignOp\Plus $node)
	{
		return $this->pInfixOp(AssignOp\Plus::class, $node->var, ' += ', $node->expr);
	}


	protected function pExpr_AssignOp_Minus(AssignOp\Minus $node)
	{
		return $this->pInfixOp(AssignOp\Minus::class, $node->var, ' -= ', $node->expr);
	}


	protected function pExpr_AssignOp_Mul(AssignOp\Mul $node)
	{
		return $this->pInfixOp(AssignOp\Mul::class, $node->var, ' *= ', $node->expr);
	}


	protected function pExpr_AssignOp_Div(AssignOp\Div $node)
	{
		return $this->pInfixOp(AssignOp\Div::class, $node->var, ' /= ', $node->expr);
	}


	protected function pExpr_AssignOp_Concat(AssignOp\Concat $node)
	{
		return $this->pInfixOp(AssignOp\Concat::class, $node->var, ' .= ', $node->expr);
	}


	protected function pExpr_AssignOp_Mod(AssignOp\Mod $node)
	{
		return $this->pInfixOp(AssignOp\Mod::class, $node->var, ' %= ', $node->expr);
	}


	protected function pExpr_AssignOp_BitwiseAnd(AssignOp\BitwiseAnd $node)
	{
		return $this->pInfixOp(AssignOp\BitwiseAnd::class, $node->var, ' &= ', $node->expr);
	}


	protected function pExpr_AssignOp_BitwiseOr(AssignOp\BitwiseOr $node)
	{
		return $this->pInfixOp(AssignOp\BitwiseOr::class, $node->var, ' |= ', $node->expr);
	}


	protected function pExpr_AssignOp_BitwiseXor(AssignOp\BitwiseXor $node)
	{
		return $this->pInfixOp(AssignOp\BitwiseXor::class, $node->var, ' ^= ', $node->expr);
	}


	protected function pExpr_AssignOp_ShiftLeft(AssignOp\ShiftLeft $node)
	{
		return $this->pInfixOp(AssignOp\ShiftLeft::class, $node->var, ' <<= ', $node->expr);
	}


	protected function pExpr_AssignOp_ShiftRight(AssignOp\ShiftRight $node)
	{
		return $this->pInfixOp(AssignOp\ShiftRight::class, $node->var, ' >>= ', $node->expr);
	}


	protected function pExpr_AssignOp_Pow(AssignOp\Pow $node)
	{
		return $this->pInfixOp(AssignOp\Pow::class, $node->var, ' **= ', $node->expr);
	}


	protected function pExpr_AssignOp_Coalesce(AssignOp\Coalesce $node)
	{
		return $this->pInfixOp(AssignOp\Coalesce::class, $node->var, ' ??= ', $node->expr);
	}


	// Binary expressions

	protected function pExpr_BinaryOp_Plus(BinaryOp\Plus $node)
	{
		return $this->pInfixOp(BinaryOp\Plus::class, $node->left, ' + ', $node->right);
	}


	protected function pExpr_BinaryOp_Minus(BinaryOp\Minus $node)
	{
		return $this->pInfixOp(BinaryOp\Minus::class, $node->left, ' - ', $node->right);
	}


	protected function pExpr_BinaryOp_Mul(BinaryOp\Mul $node)
	{
		return $this->pInfixOp(BinaryOp\Mul::class, $node->left, ' * ', $node->right);
	}


	protected function pExpr_BinaryOp_Div(BinaryOp\Div $node)
	{
		return $this->pInfixOp(BinaryOp\Div::class, $node->left, ' / ', $node->right);
	}


	protected function pExpr_BinaryOp_Concat(BinaryOp\Concat $node)
	{
		return $this->pInfixOp(BinaryOp\Concat::class, $node->left, ' . ', $node->right);
	}


	protected function pExpr_BinaryOp_Mod(BinaryOp\Mod $node)
	{
		return $this->pInfixOp(BinaryOp\Mod::class, $node->left, ' % ', $node->right);
	}


	protected function pExpr_BinaryOp_BooleanAnd(BinaryOp\BooleanAnd $node)
	{
		return $this->pInfixOp(BinaryOp\BooleanAnd::class, $node->left, ' && ', $node->right);
	}


	protected function pExpr_BinaryOp_BooleanOr(BinaryOp\BooleanOr $node)
	{
		return $this->pInfixOp(BinaryOp\BooleanOr::class, $node->left, ' || ', $node->right);
	}


	protected function pExpr_BinaryOp_BitwiseAnd(BinaryOp\BitwiseAnd $node)
	{
		return $this->pInfixOp(BinaryOp\BitwiseAnd::class, $node->left, ' & ', $node->right);
	}


	protected function pExpr_BinaryOp_BitwiseOr(BinaryOp\BitwiseOr $node)
	{
		return $this->pInfixOp(BinaryOp\BitwiseOr::class, $node->left, ' | ', $node->right);
	}


	protected function pExpr_BinaryOp_BitwiseXor(BinaryOp\BitwiseXor $node)
	{
		return $this->pInfixOp(BinaryOp\BitwiseXor::class, $node->left, ' ^ ', $node->right);
	}


	protected function pExpr_BinaryOp_ShiftLeft(BinaryOp\ShiftLeft $node)
	{
		return $this->pInfixOp(BinaryOp\ShiftLeft::class, $node->left, ' << ', $node->right);
	}


	protected function pExpr_BinaryOp_ShiftRight(BinaryOp\ShiftRight $node)
	{
		return $this->pInfixOp(BinaryOp\ShiftRight::class, $node->left, ' >> ', $node->right);
	}


	protected function pExpr_BinaryOp_Pow(BinaryOp\Pow $node)
	{
		return $this->pInfixOp(BinaryOp\Pow::class, $node->left, ' ** ', $node->right);
	}


	protected function pExpr_BinaryOp_LogicalAnd(BinaryOp\LogicalAnd $node)
	{
		return $this->pInfixOp(BinaryOp\LogicalAnd::class, $node->left, ' and ', $node->right);
	}


	protected function pExpr_BinaryOp_LogicalOr(BinaryOp\LogicalOr $node)
	{
		return $this->pInfixOp(BinaryOp\LogicalOr::class, $node->left, ' or ', $node->right);
	}


	protected function pExpr_BinaryOp_LogicalXor(BinaryOp\LogicalXor $node)
	{
		return $this->pInfixOp(BinaryOp\LogicalXor::class, $node->left, ' xor ', $node->right);
	}


	protected function pExpr_BinaryOp_Equal(BinaryOp\Equal $node)
	{
		return $this->pInfixOp(BinaryOp\Equal::class, $node->left, ' == ', $node->right);
	}


	protected function pExpr_BinaryOp_NotEqual(BinaryOp\NotEqual $node)
	{
		return $this->pInfixOp(BinaryOp\NotEqual::class, $node->left, ' != ', $node->right);
	}


	protected function pExpr_BinaryOp_Identical(BinaryOp\Identical $node)
	{
		return $this->pInfixOp(BinaryOp\Identical::class, $node->left, ' === ', $node->right);
	}


	protected function pExpr_BinaryOp_NotIdentical(BinaryOp\NotIdentical $node)
	{
		return $this->pInfixOp(BinaryOp\NotIdentical::class, $node->left, ' !== ', $node->right);
	}


	protected function pExpr_BinaryOp_Spaceship(BinaryOp\Spaceship $node)
	{
		return $this->pInfixOp(BinaryOp\Spaceship::class, $node->left, ' <=> ', $node->right);
	}


	protected function pExpr_BinaryOp_Greater(BinaryOp\Greater $node)
	{
		return $this->pInfixOp(BinaryOp\Greater::class, $node->left, ' > ', $node->right);
	}


	protected function pExpr_BinaryOp_GreaterOrEqual(BinaryOp\GreaterOrEqual $node)
	{
		return $this->pInfixOp(BinaryOp\GreaterOrEqual::class, $node->left, ' >= ', $node->right);
	}


	protected function pExpr_BinaryOp_Smaller(BinaryOp\Smaller $node)
	{
		return $this->pInfixOp(BinaryOp\Smaller::class, $node->left, ' < ', $node->right);
	}


	protected function pExpr_BinaryOp_SmallerOrEqual(BinaryOp\SmallerOrEqual $node)
	{
		return $this->pInfixOp(BinaryOp\SmallerOrEqual::class, $node->left, ' <= ', $node->right);
	}


	protected function pExpr_BinaryOp_Coalesce(BinaryOp\Coalesce $node)
	{
		return $this->pInfixOp(BinaryOp\Coalesce::class, $node->left, ' ?? ', $node->right);
	}


	protected function pExpr_Instanceof(Expr\Instanceof_ $node)
	{
		[$precedence, $associativity] = $this->precedenceMap[Expr\Instanceof_::class];
		return $this->pPrec($node->expr, $precedence, $associativity, -1)
			. ' instanceof '
			. $this->pNewVariable($node->class);
	}


	// Unary expressions

	protected function pExpr_BooleanNot(Expr\BooleanNot $node)
	{
		return $this->pPrefixOp(Expr\BooleanNot::class, '!', $node->expr);
	}


	protected function pExpr_BitwiseNot(Expr\BitwiseNot $node)
	{
		return $this->pPrefixOp(Expr\BitwiseNot::class, '~', $node->expr);
	}


	protected function pExpr_UnaryMinus(Expr\UnaryMinus $node)
	{
		if ($node->expr instanceof Expr\UnaryMinus || $node->expr instanceof Expr\PreDec) {
			// Enforce -(-$expr) instead of --$expr
			return '-(' . $this->p($node->expr) . ')';
		}
		return $this->pPrefixOp(Expr\UnaryMinus::class, '-', $node->expr);
	}


	protected function pExpr_UnaryPlus(Expr\UnaryPlus $node)
	{
		if ($node->expr instanceof Expr\UnaryPlus || $node->expr instanceof Expr\PreInc) {
			// Enforce +(+$expr) instead of ++$expr
			return '+(' . $this->p($node->expr) . ')';
		}
		return $this->pPrefixOp(Expr\UnaryPlus::class, '+', $node->expr);
	}


	protected function pExpr_PreInc(Expr\PreInc $node)
	{
		return $this->pPrefixOp(Expr\PreInc::class, '++', $node->var);
	}


	protected function pExpr_PreDec(Expr\PreDec $node)
	{
		return $this->pPrefixOp(Expr\PreDec::class, '--', $node->var);
	}


	protected function pExpr_PostInc(Expr\PostInc $node)
	{
		return $this->pPostfixOp(Expr\PostInc::class, $node->var, '++');
	}


	protected function pExpr_PostDec(Expr\PostDec $node)
	{
		return $this->pPostfixOp(Expr\PostDec::class, $node->var, '--');
	}


	protected function pExpr_ErrorSuppress(Expr\ErrorSuppress $node)
	{
		return $this->pPrefixOp(Expr\ErrorSuppress::class, '@', $node->expr);
	}


	protected function pExpr_YieldFrom(Expr\YieldFrom $node)
	{
		return $this->pPrefixOp(Expr\YieldFrom::class, 'yield from ', $node->expr);
	}


	protected function pExpr_Print(Expr\Print_ $node)
	{
		return $this->pPrefixOp(Expr\Print_::class, 'print ', $node->expr);
	}


	// Casts

	protected function pExpr_Cast_Int(Cast\Int_ $node)
	{
		return $this->pPrefixOp(Cast\Int_::class, '(int) ', $node->expr);
	}


	protected function pExpr_Cast_Double(Cast\Double $node)
	{
		$kind = $node->getAttribute('kind', Cast\Double::KIND_DOUBLE);
		if ($kind === Cast\Double::KIND_DOUBLE) {
			$cast = '(double)';
		} elseif ($kind === Cast\Double::KIND_FLOAT) {
			$cast = '(float)';
		} elseif ($kind === Cast\Double::KIND_REAL) {
			$cast = '(real)';
		}
		return $this->pPrefixOp(Cast\Double::class, $cast . ' ', $node->expr);
	}


	protected function pExpr_Cast_String(Cast\String_ $node)
	{
		return $this->pPrefixOp(Cast\String_::class, '(string) ', $node->expr);
	}


	protected function pExpr_Cast_Array(Cast\Array_ $node)
	{
		return $this->pPrefixOp(Cast\Array_::class, '(array) ', $node->expr);
	}


	protected function pExpr_Cast_Object(Cast\Object_ $node)
	{
		return $this->pPrefixOp(Cast\Object_::class, '(object) ', $node->expr);
	}


	protected function pExpr_Cast_Bool(Cast\Bool_ $node)
	{
		return $this->pPrefixOp(Cast\Bool_::class, '(bool) ', $node->expr);
	}


	protected function pExpr_Cast_Unset(Cast\Unset_ $node)
	{
		return $this->pPrefixOp(Cast\Unset_::class, '(unset) ', $node->expr);
	}


	// Function calls and similar constructs

	protected function pExpr_FuncCall(Expr\FuncCall $node)
	{
		return $this->pCallLhs($node->name)
			. '(' . $this->pMaybeMultiline($node->args) . ')';
	}


	protected function pExpr_MethodCall(Expr\MethodCall $node)
	{
		return $this->pDereferenceLhs($node->var) . '->' . $this->pObjectProperty($node->name)
			. '(' . $this->pMaybeMultiline($node->args) . ')';
	}


	protected function pExpr_NullsafeMethodCall(Expr\NullsafeMethodCall $node)
	{
		return $this->pDereferenceLhs($node->var) . '?->' . $this->pObjectProperty($node->name)
			. '(' . $this->pMaybeMultiline($node->args) . ')';
	}


	protected function pExpr_StaticCall(Expr\StaticCall $node)
	{
		return $this->pDereferenceLhs($node->class) . '::'
			. ($node->name instanceof Expr
				? ($node->name instanceof Expr\Variable
					? $this->p($node->name)
					: '{' . $this->p($node->name) . '}')
				: $node->name)
			. '(' . $this->pMaybeMultiline($node->args) . ')';
	}


	protected function pExpr_Empty(Expr\Empty_ $node)
	{
		return 'empty(' . $this->p($node->expr) . ')';
	}


	protected function pExpr_Isset(Expr\Isset_ $node)
	{
		return 'isset(' . $this->pCommaSeparated($node->vars) . ')';
	}


	protected function pExpr_Eval(Expr\Eval_ $node)
	{
		return 'eval(' . $this->p($node->expr) . ')';
	}


	protected function pExpr_Include(Expr\Include_ $node)
	{
		return 'include ' . $this->p($node->expr) . '';
	}


	protected function pExpr_List(Expr\List_ $node)
	{
		return '[' . $this->pCommaSeparated($node->items) . ']';
	}


	// Other

	protected function pExpr_Error(Expr\Error $node)
	{
		throw new \LogicException('Cannot pretty-print AST with Error nodes');
	}


	protected function pExpr_Variable(Expr\Variable $node)
	{
		if ($node->name instanceof Expr) {
			return '${' . $this->p($node->name) . '}';
		} else {
			return '$' . $node->name;
		}
	}


	protected function pExpr_Array(Expr\Array_ $node)
	{
		$syntax = $node->getAttribute(
			'kind',
			$this->options['shortArraySyntax'] ? Expr\Array_::KIND_SHORT : Expr\Array_::KIND_LONG,
		);
		if ($syntax === Expr\Array_::KIND_SHORT) {
			return '[' . $this->pMaybeMultiline($node->items, true) . ']';
		} else {
			return 'array(' . $this->pMaybeMultiline($node->items, true) . ')';
		}
	}


	protected function pExpr_ArrayItem(Expr\ArrayItem $node)
	{
		return ($node->key !== null ? $this->p($node->key) . ' => ' : '')
			. ($node->byRef ? '&' : '')
			. ($node->unpack ? '...' : '')
			. $this->p($node->value);
	}


	protected function pExpr_ArrayDimFetch(Expr\ArrayDimFetch $node)
	{
		return $this->pDereferenceLhs($node->var)
			. '[' . ($node->dim !== null ? $this->p($node->dim) : '') . ']';
	}


	protected function pExpr_ConstFetch(Expr\ConstFetch $node)
	{
		return $this->p($node->name);
	}


	protected function pExpr_ClassConstFetch(Expr\ClassConstFetch $node)
	{
		return $this->pDereferenceLhs($node->class) . '::' . $this->p($node->name);
	}


	protected function pExpr_PropertyFetch(Expr\PropertyFetch $node)
	{
		return $this->pDereferenceLhs($node->var) . '->' . $this->pObjectProperty($node->name);
	}


	protected function pExpr_NullsafePropertyFetch(Expr\NullsafePropertyFetch $node)
	{
		return $this->pDereferenceLhs($node->var) . '?->' . $this->pObjectProperty($node->name);
	}


	protected function pExpr_StaticPropertyFetch(Expr\StaticPropertyFetch $node)
	{
		return $this->pDereferenceLhs($node->class) . '::$' . $this->pObjectProperty($node->name);
	}


	protected function pExpr_ShellExec(Expr\ShellExec $node)
	{
		return '`' . $this->pEncapsList($node->parts, '`') . '`';
	}


	protected function pExpr_Closure(Expr\Closure $node)
	{
		return $this->pAttrGroups($node->attrGroups, true)
			. ($node->static ? 'static ' : '')
			. 'function ' . ($node->byRef ? '&' : '')
			. '(' . $this->pCommaSeparated($node->params) . ')'
			. (!empty($node->uses) ? ' use(' . $this->pCommaSeparated($node->uses) . ')' : '')
			. ($node->returnType !== null ? ' : ' . $this->p($node->returnType) : '')
			. ' {' . $this->pStmts($node->stmts) . $this->nl . '}';
	}


	protected function pExpr_Match(Expr\Match_ $node)
	{
		return 'match (' . $this->p($node->cond) . ') {'
			. $this->pCommaSeparatedMultiline($node->arms, true)
			. $this->nl
			. '}';
	}


	protected function pMatchArm(Node\MatchArm $node)
	{
		return ($node->conds ? $this->pCommaSeparated($node->conds) : 'default')
			. ' => ' . $this->p($node->body);
	}


	protected function pExpr_ArrowFunction(Expr\ArrowFunction $node)
	{
		return $this->pAttrGroups($node->attrGroups, true)
			. ($node->static ? 'static ' : '')
			. 'fn' . ($node->byRef ? '&' : '')
			. '(' . $this->pCommaSeparated($node->params) . ')'
			. ($node->returnType !== null ? ': ' . $this->p($node->returnType) : '')
			. ' => '
			. $this->p($node->expr);
	}


	protected function pExpr_ClosureUse(Expr\ClosureUse $node)
	{
		return ($node->byRef ? '&' : '') . $this->p($node->var);
	}


	protected function pExpr_New(Expr\New_ $node)
	{
		if ($node->class instanceof Stmt\Class_) {
			$args = $node->args ? '(' . $this->pMaybeMultiline($node->args) . ')' : '';
			return 'new ' . $this->pClassCommon($node->class, $args);
		}
		return 'new ' . $this->pNewVariable($node->class)
			. '(' . $this->pMaybeMultiline($node->args) . ')';
	}


	protected function pExpr_Clone(Expr\Clone_ $node)
	{
		return 'clone ' . $this->p($node->expr);
	}


	protected function pExpr_Ternary(Expr\Ternary $node)
	{
		// a bit of cheating: we treat the ternary as a binary op where the ?...: part is the operator.
		// this is okay because the part between ? and : never needs parentheses.
		return $this->pInfixOp(
			Expr\Ternary::class,
			$node->cond,
			' ?' . ($node->if !== null ? ' ' . $this->p($node->if) . ' ' : '') . ': ',
			$node->else,
		);
	}


	protected function pExpr_Exit(Expr\Exit_ $node)
	{
		$kind = $node->getAttribute('kind', Expr\Exit_::KIND_DIE);
		return ($kind === Expr\Exit_::KIND_EXIT ? 'exit' : 'die')
			. ($node->expr !== null ? '(' . $this->p($node->expr) . ')' : '');
	}


	protected function pExpr_Throw(Expr\Throw_ $node)
	{
		return 'throw ' . $this->p($node->expr);
	}


	protected function pExpr_Yield(Expr\Yield_ $node)
	{
		if ($node->value === null) {
			return 'yield';
		} else {
			// this is a bit ugly, but currently there is no way to detect whether the parentheses are necessary
			return '(yield '
				. ($node->key !== null ? $this->p($node->key) . ' => ' : '')
				. $this->p($node->value)
				. ')';
		}
	}


	// Declarations

	protected function pStmt_Namespace(Stmt\Namespace_ $node)
	{
		return '';
	}


	protected function pStmt_Use(Stmt\Use_ $node)
	{
		return 'use ' . $this->pUseType($node->type)
			. $this->pCommaSeparated($node->uses) . ';';
	}


	protected function pStmt_GroupUse(Stmt\GroupUse $node)
	{
		return 'use ' . $this->pUseType($node->type) . $this->pName($node->prefix)
			. '\{' . $this->pCommaSeparated($node->uses) . '};';
	}


	protected function pStmt_UseUse(Stmt\UseUse $node)
	{
		return $this->pUseType($node->type) . $this->p($node->name)
			. ($node->alias !== null ? ' as ' . $node->alias : '');
	}


	protected function pUseType($type)
	{
		return $type === Stmt\Use_::TYPE_FUNCTION ? 'function '
			: ($type === Stmt\Use_::TYPE_CONSTANT ? 'const ' : '');
	}


	protected function pStmt_Interface(Stmt\Interface_ $node)
	{
		return '{* interface ' . $node->name . ' *}';
	}


	protected function pStmt_Class(Stmt\Class_ $node)
	{
		return '{* class ' . $node->name . ' *}';
	}


	protected function pStmt_Trait(Stmt\Trait_ $node)
	{
		return '{* trait ' . $node->name . ' *}';
	}


	protected function pStmt_Function(Stmt\Function_ $node)
	{
		return $this->pAttrGroups($node->attrGroups)
			. '{* function ' . ($node->byRef ? '&' : '') . $node->name
			. '(' . $this->pCommaSeparated($node->params) . ')'
			. ($node->returnType !== null ? ' : ' . $this->p($node->returnType) : '')
			. ' *}';
	}


	protected function pStmt_Const(Stmt\Const_ $node)
	{
		return '{* const ' . $this->pCommaSeparated($node->consts) . ' *}';
	}


	protected function pStmt_Declare(Stmt\Declare_ $node)
	{
		return '';
	}


	protected function pStmt_DeclareDeclare(Stmt\DeclareDeclare $node)
	{
		return '';
	}


	// Control flow

	protected function pStmt_If(Stmt\If_ $node)
	{
		// Check if we can generate n:if instead of {if...}
		if ($this->canGenerateNAttribute($node)) {
			return $this->pStmt_If_NAttribute($node);
		}

		// For regular if statements, we need to handle elseif/else specially
		// to avoid generating empty strings when they're single HTML elements
		$result = '{if ' . $this->p($node->cond) . '}' . $this->pStmts($node->stmts, true, true) . $this->nl;

		// Handle elseif clauses
		if ($node->elseifs) {
			foreach ($node->elseifs as $elseif) {
				$elseifOutput = $this->pStmt_ElseIf($elseif);
				if ($elseifOutput !== '') {
					$result .= ' ' . $elseifOutput;
				}
			}
		}

		// Handle else clause
		if ($node->else !== null) {
			$elseOutput = $this->pStmt_Else($node->else);
			if ($elseOutput !== '') {
				$result .= ' ' . $elseOutput;
			}
		}

		$result .= '{/if}';
		return $result;
	}

	/**
	 * Check if an if statement can be converted to n:attribute
	 */
	protected function canGenerateNAttribute(Stmt\If_ $node): bool
	{
		// Must have a single HTML element OR a valid HTML pattern in the main block
		if (!$this->isSingleHTMLElement($node->stmts) && !$this->isHTMLElementPattern($node->stmts)) {
			return false;
		}

		// Check if elseif/else blocks are also suitable (single HTML, HTML pattern, or empty)
		foreach ($node->elseifs as $elseif) {
			if (!$this->isSingleHTMLElement($elseif->stmts) && !$this->isHTMLElementPattern($elseif->stmts) && count($elseif->stmts) > 0) {
				return false;
			}
		}

		if ($node->else !== null && !$this->isSingleHTMLElement($node->else->stmts) && !$this->isHTMLElementPattern($node->else->stmts) && count($node->else->stmts) > 0) {
			return false;
		}

		return true;
	}

	/**
	 * Generate n:if attribute for if statement
	 */
	protected function pStmt_If_NAttribute(Stmt\If_ $node): string
	{
		// Build n:if element from statements
		$condition = $this->p($node->cond);
		$result = $this->buildNAttributeElement($node->stmts, 'if', $condition);

		if ($result === '') {
			// Fallback to regular if statement
			return '{if ' . $this->p($node->cond) . '}'
				. $this->pStmts($node->stmts) . $this->nl
				. ($node->elseifs ? ' ' . $this->pImplode($node->elseifs, ' ') : '')
				. ($node->else !== null ? ' ' . $this->p($node->else) : '') . '{/if}';
		}

		// Handle elseif clauses
		if ($node->elseifs) {
			foreach ($node->elseifs as $elseif) {
				$elseifCondition = $this->p($elseif->cond);
				$result .= $this->nl . $this->buildNAttributeElement($elseif->stmts, 'elseif', $elseifCondition);
			}
		}

		// Handle else clause
		if ($node->else !== null) {
			$result .= $this->nl . $this->buildNAttributeElement($node->else->stmts, 'else', '');
		}

		return $result;
	}


	protected function pStmt_ElseIf(Stmt\ElseIf_ $node)
	{
		// Check if we should skip this (when part of n:attribute conversion)
		// We detect this by checking if the statement is a single HTML element or HTML pattern
		// and if it's being printed as part of an if statement that could be n:attribute
		// This is a heuristic - if it's a single HTML element/pattern, we assume it's part of n:attribute
		if ($this->isSingleHTMLElement($node->stmts) || $this->isHTMLElementPattern($node->stmts)) {
			// Return empty string - the parent pStmt_If_NAttribute will handle it
			return '';
		}

		return '{elseif ' . $this->p($node->cond) . '}'
			. $this->pStmts($node->stmts) . $this->nl;
	}


	protected function pStmt_Else(Stmt\Else_ $node)
	{
		// Check if we should skip this (when part of n:attribute conversion)
		if ($this->isSingleHTMLElement($node->stmts) || $this->isHTMLElementPattern($node->stmts)) {
			// Return empty string - the parent pStmt_If_NAttribute will handle it
			return '';
		}

		return '{else}' . $this->pStmts($node->stmts) . $this->nl;
	}


	protected function pStmt_For(Stmt\For_ $node)
	{
		// Check if we can generate n:for instead of {for...}
		if ($this->canGenerateNAttributeFor($node)) {
			return $this->pStmt_For_NAttribute($node);
		}

		return '{for '
			. $this->pCommaSeparated($node->init) . ';' . (!empty($node->cond) ? ' ' : '')
			. $this->pCommaSeparated($node->cond) . ';' . (!empty($node->loop) ? ' ' : '')
			. $this->pCommaSeparated($node->loop)
			. '}' . $this->pStmts($node->stmts) . $this->nl . '{/for}';
	}


	/**
	 * Check if a for statement can be converted to n:attribute
	 */
	protected function canGenerateNAttributeFor(Stmt\For_ $node): bool
	{
		// Must have a single HTML element or HTML pattern in the body
		return $this->isSingleHTMLElement($node->stmts) || $this->isHTMLElementPattern($node->stmts);
	}


	/**
	 * Generate n:for attribute for for statement
	 */
	protected function pStmt_For_NAttribute(Stmt\For_ $node): string
	{
		// Build n:for loop expression
		$loop = $this->pCommaSeparated($node->init) . ';' . (!empty($node->cond) ? ' ' : '')
			. $this->pCommaSeparated($node->cond) . ';' . (!empty($node->loop) ? ' ' : '')
			. $this->pCommaSeparated($node->loop);

		// Build n:for element from statements
		$result = $this->buildNAttributeElement($node->stmts, 'for', $loop);

		if ($result === '') {
			// Fallback to regular for statement
			return '{for '
				. $this->pCommaSeparated($node->init) . ';' . (!empty($node->cond) ? ' ' : '')
				. $this->pCommaSeparated($node->cond) . ';' . (!empty($node->loop) ? ' ' : '')
				. $this->pCommaSeparated($node->loop)
				. '}' . $this->pStmts($node->stmts) . $this->nl . '{/for}';
		}

		return $result;
	}


	protected function pStmt_Foreach(Stmt\Foreach_ $node)
	{
		// Check if we can generate n:foreach instead of {foreach...}
		if ($this->canGenerateNAttributeForeach($node)) {
			return $this->pStmt_Foreach_NAttribute($node);
		}

		return '{foreach ' . $this->p($node->expr) . ' as '
			. ($node->keyVar !== null ? $this->p($node->keyVar) . ' => ' : '')
			. ($node->byRef ? '&' : '') . $this->p($node->valueVar) . '}'
			. $this->pStmts($node->stmts) . $this->nl . '{/foreach}';
	}

	/**
	 * Check if a foreach statement can be converted to n:attribute
	 */
	protected function canGenerateNAttributeForeach(Stmt\Foreach_ $node): bool
	{
		// Must have a single HTML element or HTML pattern in the body
		return $this->isSingleHTMLElement($node->stmts) || $this->isHTMLElementPattern($node->stmts);
	}

	/**
	 * Generate n:foreach attribute for foreach statement
	 */
	protected function pStmt_Foreach_NAttribute(Stmt\Foreach_ $node): string
	{
		// Build n:foreach loop expression
		$loop = $this->p($node->expr) . ' as '
			. ($node->keyVar !== null ? $this->p($node->keyVar) . ' => ' : '')
			. ($node->byRef ? '&' : '') . $this->p($node->valueVar);

		// Build n:foreach element from statements
		$result = $this->buildNAttributeElement($node->stmts, 'foreach', $loop);

		if ($result === '') {
			// Fallback to regular foreach statement
			return '{foreach ' . $this->p($node->expr) . ' as '
				. ($node->keyVar !== null ? $this->p($node->keyVar) . ' => ' : '')
				. ($node->byRef ? '&' : '') . $this->p($node->valueVar) . '}'
				. $this->pStmts($node->stmts) . $this->nl . '{/foreach}';
		}

		return $result;
	}


	protected function pStmt_While(Stmt\While_ $node)
	{
		// Check if we can generate n:while instead of {while...}
		if ($this->canGenerateNAttributeWhile($node)) {
			return $this->pStmt_While_NAttribute($node);
		}

		return '{while ' . $this->p($node->cond) . '}'
			. $this->pStmts($node->stmts) . $this->nl . '{/while}';
	}


	/**
	 * Check if a while statement can be converted to n:attribute
	 */
	protected function canGenerateNAttributeWhile(Stmt\While_ $node): bool
	{
		// Must have a single HTML element or HTML pattern in the body
		return $this->isSingleHTMLElement($node->stmts) || $this->isHTMLElementPattern($node->stmts);
	}


	/**
	 * Generate n:while attribute for while statement
	 */
	protected function pStmt_While_NAttribute(Stmt\While_ $node): string
	{
		$condition = $this->p($node->cond);

		// Build n:while element from statements
		$result = $this->buildNAttributeElement($node->stmts, 'while', $condition);

		if ($result === '') {
			// Fallback to regular while statement
			return '{while ' . $this->p($node->cond) . '}'
				. $this->pStmts($node->stmts) . $this->nl . '{/while}';
		}

		return $result;
	}


	protected function pStmt_Do(Stmt\Do_ $node)
	{
		return '{while}' . $this->pStmts($node->stmts) . $this->nl
			. '{/while ' . $this->p($node->cond) . '}';
	}


	protected function pStmt_Switch(Stmt\Switch_ $node)
	{
		return '{switch ' . $this->p($node->cond) . '}'
			. $this->pStmts($node->cases) . $this->nl . '{/switch}';
	}


	protected function pStmt_Case(Stmt\Case_ $node)
	{
		return ($node->cond !== null ? '{case ' . $this->p($node->cond) : '{default') . '}'
			. $this->pStmts($node->stmts);
	}


	protected function pStmt_TryCatch(Stmt\TryCatch $node)
	{
		// Check if we can generate n:try instead of {try...}
		if ($this->canGenerateNAttributeTry($node)) {
			return $this->pStmt_TryCatch_NAttribute($node);
		}

		return '{try' . $this->pStmts($node->stmts) . $this->nl
			. ($node->catches ? ' ' . $this->pImplode($node->catches, ' ') : '')
			. ($node->finally !== null ? ' ' . $this->p($node->finally) : '') . '{/try}';
	}


	/**
	 * Check if a try-catch statement can be converted to n:attribute
	 */
	protected function canGenerateNAttributeTry(Stmt\TryCatch $node): bool
	{
		// Must have a single HTML element or HTML pattern in the try body
		if (!$this->isSingleHTMLElement($node->stmts) && !$this->isHTMLElementPattern($node->stmts)) {
			return false;
		}

		// Check if catch blocks are also suitable (single HTML, HTML pattern, or empty)
		foreach ($node->catches as $catch) {
			if (!$this->isSingleHTMLElement($catch->stmts) && !$this->isHTMLElementPattern($catch->stmts) && count($catch->stmts) > 0) {
				return false;
			}
		}

		if ($node->finally !== null && !$this->isSingleHTMLElement($node->finally->stmts) && !$this->isHTMLElementPattern($node->finally->stmts) && count($node->finally->stmts) > 0) {
			return false;
		}

		return true;
	}


	/**
	 * Generate n:try attribute for try-catch statement
	 */
	protected function pStmt_TryCatch_NAttribute(Stmt\TryCatch $node): string
	{
		// Build n:try element from statements
		$result = $this->buildNAttributeElement($node->stmts, 'try', '');

		if ($result === '') {
			// Fallback to regular try statement
			return '{try' . $this->pStmts($node->stmts) . $this->nl
				. ($node->catches ? ' ' . $this->pImplode($node->catches, ' ') : '')
				. ($node->finally !== null ? ' ' . $this->p($node->finally) : '') . '{/try}';
		}

		// Handle catch clauses
		if ($node->catches) {
			foreach ($node->catches as $catch) {
				$result .= $this->nl . $this->buildNAttributeElement($catch->stmts, 'else', '');
			}
		}

		// Handle finally clause
		if ($node->finally !== null) {
			$result .= $this->nl . $this->buildNAttributeElement($node->finally->stmts, 'else', '');
		}

		return $result;
	}


	protected function pStmt_Catch(Stmt\Catch_ $node)
	{
		// Check if we should skip this (when part of n:attribute conversion)
		if ($this->isSingleHTMLElement($node->stmts) || $this->isHTMLElementPattern($node->stmts)) {
			// Return empty string - the parent pStmt_TryCatch_NAttribute will handle it
			return '';
		}

		return '{* catch (' . $this->pImplode($node->types, '|')
			. ($node->var !== null ? ' ' . $this->p($node->var) : '')
			. ') {' . $this->pStmts($node->stmts) . $this->nl . ' *}';
	}


	protected function pStmt_Finally(Stmt\Finally_ $node)
	{
		// Check if we should skip this (when part of n:attribute conversion)
		if ($this->isSingleHTMLElement($node->stmts) || $this->isHTMLElementPattern($node->stmts)) {
			// Return empty string - the parent pStmt_TryCatch_NAttribute will handle it
			return '';
		}

		return '{* finally {' . $this->pStmts($node->stmts) . $this->nl . ' *}';
	}


	protected function pStmt_Break(Stmt\Break_ $node)
	{
		return '{breakIf true}';
	}


	protected function pStmt_Continue(Stmt\Continue_ $node)
	{
		return '{continueIf true}';
	}


	protected function pStmt_Return(Stmt\Return_ $node)
	{
		return '{* return' . ($node->expr !== null ? ' ' . $this->p($node->expr) : '') . ' *}';
	}


	protected function pStmt_Throw(Stmt\Throw_ $node)
	{
		return '{* throw ' . $this->p($node->expr) . ' *}';
	}


	protected function pStmt_Label(Stmt\Label $node)
	{
		return '{* ' . $node->name . ': *}';
	}


	protected function pStmt_Goto(Stmt\Goto_ $node)
	{
		return '{* goto ' . $node->name . ' *}';
	}


	// Other

	protected function pStmt_InlineHTML(Stmt\InlineHTML $node)
	{
		return $node->value;
	}


	// n:attribute helpers

	/**
	 * Check if statements contain a single HTML element that can be wrapped with n:attribute
	 */
	protected function isSingleHTMLElement(array $stmts): bool
	{
		// Filter out Nop (whitespace) statements
		$realStmts = array_filter($stmts, fn($s) => !$s instanceof Stmt\Nop);

		if (count($realStmts) !== 1) {
			return false;
		}

		$stmt = reset($realStmts);
		return $stmt instanceof Stmt\InlineHTML;
	}

	/**
	 * Build n:attribute element from AST statements
	 * Constructs: <tag n:attr="value" original-attrs>inner-content</tag>
	 */
	protected function buildNAttributeElement(array $stmts, string $nAttrName, string $nAttrValue): string
	{
		// Filter out Nop statements
		$relevantStmts = [];
		foreach ($stmts as $stmt) {
			if (!$stmt instanceof Stmt\Nop) {
				$relevantStmts[] = $stmt;
			}
		}

		if (count($relevantStmts) === 0) {
			return '';
		}

		// Build the content by processing all statements
		$content = '';
		foreach ($relevantStmts as $stmt) {
			if ($stmt instanceof Stmt\InlineHTML) {
				$content .= $stmt->value;
			} elseif ($stmt instanceof Stmt\Echo_) {
				$content .= $this->pStmt_Echo($stmt);
			} elseif ($stmt instanceof Stmt\Expression) {
				$content .= $this->pStmt_Expression($stmt);
			}
		}

		$content = trim($content);

		// Parse the content to insert n:attribute at the right place
		return $this->insertNAttributeIntoElement($content, $nAttrName, $nAttrValue);
	}


	/**
	 * Insert n:attribute into HTML element content using character-by-character parsing
	 */
	protected function insertNAttributeIntoElement(string $content, string $nAttrName, string $nAttrValue): string
	{
		if ($content === '') {
			return '';
		}

		$len = strlen($content);
		$pos = 0;

		// Skip whitespace at start
		while ($pos < $len && ctype_space($content[$pos])) {
			$pos++;
		}

		// Must start with <
		if ($pos >= $len || $content[$pos] !== '<') {
			return $content;
		}

		// Skip past <
		$pos++;

		// Skip whitespace
		while ($pos < $len && ctype_space($content[$pos])) {
			$pos++;
		}

		// Read tag name
		$tagStart = $pos;
		while ($pos < $len && (ctype_alnum($content[$pos]) || $content[$pos] === '-')) {
			$pos++;
		}
		$tagName = substr($content, $tagStart, $pos - $tagStart);

		if ($tagName === '') {
			return $content;
		}

		// Build result: <tag + n:attribute + rest-of-element
		$result = '<' . $tagName;

		// Add n:attribute
		if ($nAttrValue === '') {
			$result .= ' n:' . $nAttrName;
		} else {
			$escapedValue = addcslashes($nAttrValue, '"');
			$result .= ' n:' . $nAttrName . '="' . $escapedValue . '"';
		}

		// Append the rest of the element (from after tag name to end)
		$result .= substr($content, $pos);

		return $result;
	}




	/**
	 * Check if statements form a single HTML element pattern (opening tag + content + closing tag)
	 * Uses AST analysis instead of regex for robustness
	 */
	protected function isHTMLElementPattern(array $stmts): bool
	{
		if (count($stmts) < 1) {
			return false;
		}

		// Filter out Nop (whitespace) statements
		$realStmts = array_filter($stmts, fn($s) => !$s instanceof Stmt\Nop);

		if (count($realStmts) < 1) {
			return false;
		}

		// Collect relevant statements (InlineHTML at start/end, anything in between)
		// First, find the first and last InlineHTML statements
		$firstInlineIdx = null;
		$lastInlineIdx = null;

		foreach ($realStmts as $i => $stmt) {
			if ($stmt instanceof Stmt\InlineHTML) {
				if ($firstInlineIdx === null) {
					$firstInlineIdx = $i;
				}
				$lastInlineIdx = $i;
			}
		}

		if ($firstInlineIdx === null || $lastInlineIdx === null) {
			return false;
		}

		$firstStmt = $stmts[$firstInlineIdx];
		$lastStmt = $stmts[$lastInlineIdx];

		// Extract tag name from first statement using AST-aware parsing
		$firstHTML = ltrim($firstStmt->value);
		$tagName = $this->extractTagNameFromStart($firstHTML);
		if ($tagName === null) {
			return false;
		}

		// Check if this is a self-closing (void) element
		// These don't require closing tags
		$voidElements = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
		if (in_array(strtolower($tagName), $voidElements, true)) {
			// For void elements, check if the merged content forms a complete self-closing tag
			// The content might contain '>' characters inside Latte tags like {$var->prop}
			// So we check if it starts with <tagname and ends with >
			$merged = $this->mergeStatementsToHTMLElement($stmts);
			$merged = trim($merged);
			return (bool) (preg_match('/^<' . preg_quote($tagName, '/') . '\b/i', $merged) && substr($merged, -1) === '>');
		}

		// Check if last statement contains matching closing tag
		// The closing tag should be present, possibly followed by whitespace/comments
		$lastHTML = trim($lastStmt->value);
		$expectedClosing = '</' . $tagName . '>';
		if (!preg_match('/<\/\s*' . preg_quote($tagName, '/') . '\s*>/i', $lastHTML)) {
			return false;
		}

		return true;
	}


	/**
	 * Extract tag name from the start of HTML content
	 * Returns null if content doesn't start with a valid HTML tag
	 */
	protected function extractTagNameFromStart(string $html): ?string
	{
		$html = ltrim($html);
		if ($html === '' || $html[0] !== '<') {
			return null;
		}

		// Skip past <
		$pos = 1;
		$len = strlen($html);

		// Skip whitespace after <
		while ($pos < $len && ctype_space($html[$pos])) {
			$pos++;
		}

		// Read tag name
		$tagName = '';
		while ($pos < $len && (ctype_alnum($html[$pos]) || $html[$pos] === '-')) {
			$tagName .= $html[$pos];
			$pos++;
		}

		if ($tagName === '' || strtolower($tagName) === '!--') {
			return null;
		}

		return $tagName;
	}


	/**
	 * Merge multiple statements into a single HTML element string
	 * Combines: <tag> + {content} + </tag> into <tag>{content}</tag>
	 * Handles both simple 3-statement patterns and complex multi-statement patterns
	 */
	protected function mergeStatementsToHTMLElement(array $stmts): string
	{
		if (count($stmts) === 1 && $stmts[0] instanceof Stmt\InlineHTML) {
			return $stmts[0]->value;
		}

		// Filter out Nop statements
		$relevantStmts = [];
		foreach ($stmts as $stmt) {
			if (!$stmt instanceof Stmt\Nop) {
				$relevantStmts[] = $stmt;
			}
		}

		if (count($relevantStmts) === 1 && $relevantStmts[0] instanceof Stmt\InlineHTML) {
			return $relevantStmts[0]->value;
		}

		// Build the full content by processing all statements in order
		$result = '';
		foreach ($relevantStmts as $stmt) {
			if ($stmt instanceof Stmt\InlineHTML) {
				$result .= $stmt->value;
			} elseif ($stmt instanceof Stmt\Echo_) {
				$result .= $this->pStmt_Echo($stmt);
			} elseif ($stmt instanceof Stmt\Expression) {
				$result .= $this->pStmt_Expression($stmt);
			}
		}

		// Trim the result to clean up whitespace
		return trim($result);
	}


	/**
	 * Extract attributes from an opening HTML tag string
	 */
	protected function extractAttributesFromOpeningTag(string $html): string
	{
		// Match <tag attributes> or <tag attributes>... - stop at >
		if (preg_match('/^<[a-zA-Z][a-zA-Z0-9]*\b([^>]*)>/', $html, $matches)) {
			return trim($matches[1]);
		}
		return '';
	}


	protected function pStmt_Echo(Stmt\Echo_ $node)
	{
		$expr = $this->pCommaSeparated($node->exprs);
		return '{' . (preg_match('~^[$a-z]~', $expr) ? '' : '=') . $expr . '}';
	}


	protected function pStmt_Static(Stmt\Static_ $node)
	{
		return '{* static ' . $this->pCommaSeparated($node->vars) . ' *}';
	}


	protected function pStmt_Global(Stmt\Global_ $node)
	{
		return '{* global ' . $this->pCommaSeparated($node->vars) . ' *}';
	}


	protected function pStmt_StaticVar(Stmt\StaticVar $node)
	{
		return '{do ' . $this->p($node->var)
			. ($node->default !== null ? ' = ' . $this->p($node->default) : '') . '}';
	}


	protected function pStmt_Unset(Stmt\Unset_ $node)
	{
		return '{do unset(' . $this->pCommaSeparated($node->vars) . ')}';
	}


	protected function pStmt_Expression(Stmt\Expression $node)
	{
		if ($node->expr instanceof Expr\Assign && $node->expr->var instanceof Expr\Variable) {
			return '{var ' . $this->p($node->expr) . '}';
		}
		if ($node->expr instanceof Expr\Include_) {
			return '{include ' . $this->p($node->expr->expr) . '}';
		}
		return '{do ' . $this->p($node->expr) . '}';
	}


	protected function pStmt_HaltCompiler(Stmt\HaltCompiler $node)
	{
		return '{* __halt_compiler(); *}' . $node->remaining;
	}


	protected function pStmt_Nop(Stmt\Nop $node)
	{
		return '';
	}


	protected function pStmts(array $nodes, bool $indent = true, bool $trimFirstIndent = false): string
	{
		if ($indent) {
			$this->indent();
		}

		$result = '';
		$lastEcho = false;
		$firstNode = true;
		foreach ($nodes as $node) {
			$comments = $node->getComments();
			if ($comments) {
				$result .= $this->nl . $this->pComments($comments);
				if ($node instanceof Stmt\Nop) {
					continue;
				}
			}

			$nodeOutput = $this->p($node);

			// Trim leading whitespace from the first InlineHTML node if requested
			if ($trimFirstIndent && $firstNode && $node instanceof Stmt\InlineHTML) {
				$nodeOutput = ltrim($nodeOutput);
			}

			// Determine if we need a newline before this node
			$isCurrentEchoOrInline = $node instanceof Stmt\Echo_ || $node instanceof Stmt\InlineHTML;

			// Always add newline for non-echo/inline nodes (closures, functions, etc. need this)
			// Only skip newlines between consecutive Echo/InlineHTML nodes
			if (!$isCurrentEchoOrInline || !$lastEcho || $firstNode) {
				$result .= $this->nl;
			}

			$result .= $nodeOutput;
			$lastEcho = $isCurrentEchoOrInline;
			$firstNode = false;
		}

		if ($indent) {
			$this->outdent();
		}

		return $result;
	}


	protected function pComments(array $comments): string
	{
		$formattedComments = [];

		foreach ($comments as $comment) {
			$formattedComments[] = str_replace("\n", $this->nl, $comment->getText());
		}

		return '{* ' . implode($this->nl, $formattedComments) . ' *}';
	}


	// Helpers

	protected function pClassCommon(Stmt\Class_ $node, $afterClassToken)
	{
		return '';
	}


	protected function pObjectProperty($node)
	{
		if ($node instanceof Expr) {
			return '{' . $this->p($node) . '}';
		} else {
			return $node;
		}
	}


	protected function pEncapsList(array $encapsList, $quote)
	{
		$return = '';
		foreach ($encapsList as $element) {
			if ($element instanceof Scalar\EncapsedStringPart) {
				$return .= $this->escapeString($element->value, $quote);
			} else {
				$return .= '{' . $this->p($element) . '}';
			}
		}

		return $return;
	}


	protected function pSingleQuotedString(string $string)
	{
		if (preg_match('#^\w+(?:-+\w+)*\z#', $string)
			&& !preg_match('#^(true|false|null|TRUE|FALSE|NULL|INF|NAN|and|or|xor|AND|OR|XOR|clone|new|instanceof|return|continue|break|\d+)\z#', $string)
		) {
			return $string;
		}
		return '\'' . addcslashes($string, '\'\\') . '\'';
	}


	protected function escapeString($string, $quote)
	{
		if ($quote === null) {
			// For doc strings, don't escape newlines
			$escaped = addcslashes($string, "\t\f\v$\\");
		} else {
			$escaped = addcslashes($string, "\n\r\t\f\v$" . $quote . '\\');
		}

		// Escape other control characters
		return preg_replace_callback('/([\0-\10\16-\37])(?=([0-7]?))/', function ($matches) {
			$oct = decoct(ord($matches[1]));
			if ($matches[2] !== '') {
				// If there is a trailing digit, use the full three character form
				return '\\' . str_pad($oct, 3, '0', \STR_PAD_LEFT);
			}
			return '\\' . $oct;
		}, $escaped);
	}


	protected function containsEndLabel($string, $label, $atStart = true, $atEnd = true)
	{
		$start = $atStart ? '(?:^|[\r\n])' : '[\r\n]';
		$end = $atEnd ? '(?:$|[;\r\n])' : '[;\r\n]';
		return str_contains($string, $label)
			&& preg_match('/' . $start . $label . $end . '/', $string);
	}


	protected function encapsedContainsEndLabel(array $parts, $label)
	{
		foreach ($parts as $i => $part) {
			$atStart = $i === 0;
			$atEnd = $i === count($parts) - 1;
			if ($part instanceof Scalar\EncapsedStringPart
				&& $this->containsEndLabel($part->value, $label, $atStart, $atEnd)
			) {
				return true;
			}
		}

		return false;
	}


	protected function pDereferenceLhs(Node $node)
	{
		if (!$this->dereferenceLhsRequiresParens($node)) {
			return $this->p($node);
		} else {
			return '(' . $this->p($node) . ')';
		}
	}


	protected function pCallLhs(Node $node)
	{
		if (!$this->callLhsRequiresParens($node)) {
			return $this->p($node);
		} else {
			return '(' . $this->p($node) . ')';
		}
	}


	protected function pNewVariable(Node $node)
	{
		// TODO: This is not fully accurate.
		return $this->pDereferenceLhs($node);
	}


	/**
	 * @param Node[] $nodes
	 * @return bool
	 */
	protected function hasNodeWithComments(array $nodes)
	{
		foreach ($nodes as $node) {
			if ($node && $node->getComments()) {
				return true;
			}
		}

		return false;
	}


	protected function pMaybeMultiline(array $nodes, bool $trailingComma = false)
	{
		if (!$this->hasNodeWithComments($nodes)) {
			return $this->pCommaSeparated($nodes);
		} else {
			return $this->pCommaSeparatedMultiline($nodes, $trailingComma) . $this->nl;
		}
	}


	protected function pAttrGroups(array $nodes, bool $inline = false): string
	{
		$result = '';
		$sep = $inline ? ' ' : $this->nl;
		foreach ($nodes as $node) {
			$result .= $this->p($node) . $sep;
		}

		return $result;
	}
}
