<?php declare(strict_types = 1);

namespace PHPStan\Rules\Variables;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\StringType;
use PHPStan\Type\TrueOrFalseBooleanType;
use PHPStan\Type\UnionType;
use PHPStan\Type\Type;


class VariableCertaintyInIssetRule implements \PHPStan\Rules\Rule
{

	public function getNodeType(): string
	{
		return Node\Expr\Isset_::class;
	}

	/**
	 * @param \PhpParser\Node\Expr\Isset_ $node
	 * @param \PHPStan\Analyser\Scope $scope
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		$messages = [];
		foreach ($node->vars as $var) {
			$isSubNode = false;
			while (
				$var instanceof Node\Expr\ArrayDimFetch
				|| $var instanceof Node\Expr\PropertyFetch
			) {
				$var = $var->var;
				$isSubNode = true;
			}

			while (
				$var instanceof Node\Expr\StaticPropertyFetch
				&& $var->class instanceof Node\Expr
			) {
				$var = $var->class;
				$isSubNode = true;
			}

			if (!$var instanceof Node\Expr\Variable || !is_string($var->name)) {
				continue;
			}

			if (DefinedVariableRule::isGlobalVariable($var->name)) {
				continue;
			}

			$certainty = $scope->hasVariableType($var->name);
			if ($certainty->no()) {
				$messages[] = sprintf('Variable $%s in isset() is never defined.', $var->name);
			} elseif ($certainty->yes() && !$isSubNode) {
				$variableType = $scope->getVariableType($var->name);
				if (!$variableType->accepts(new NullType())) {

				    // pewa: in PHP5 scalar types are always nullable (strong typing exists only for objects)
				    $scalarTypes = [
    				    TrueOrFalseBooleanType::class,
    				    FloatType::class,
    				    IntegerType::class,
    				    StringType::class,
				    ];
				    $types = $variableType instanceof UnionType ? $variableType->getTypes() : [$variableType];
				    $types = array_map(function(Type $type) { return get_class($type); }, $types);

				    if (!array_intersect($scalarTypes, $types)) {
					   $messages[] = sprintf('Variable $%s in isset() always exists and is not nullable.', $var->name);
				    }
				}
			}
		}

		return $messages;
	}

}
