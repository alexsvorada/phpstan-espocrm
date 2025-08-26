<?php

declare(strict_types=1);

namespace AlexSvorada\EspoCRM\PHPStan\Rules\Hooks;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier as NodeIdentifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use AlexSvorada\EspoCRM\PHPStan\Rules\Core\HasIdentifierBuilder;

/** @implements Rule<ClassMethod> */
final class NoSameEntitySaveRule implements Rule
{
	use HasIdentifierBuilder;

	public function getNodeType(): string
	{
		return ClassMethod::class;
	}

	/**
	 * @param ClassMethod $node
	 * @return array<int, \PHPStan\Rules\RuleError>
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		$filePath = $scope->getFile();
		if (strpos($filePath, '/src/backend/Hooks/') === false) {
			return [];
		}

		$methodName = $node->name->toString();
		if ($methodName !== 'afterSave' && $methodName !== 'beforeSave') {
			return [];
		}

		$entityParamName = null;
		if (isset($node->params[0]) && $node->params[0]->var instanceof Variable && is_string($node->params[0]->var->name)) {
			$entityParamName = $node->params[0]->var->name;
		}

		if ($entityParamName === null || $node->stmts === null) {
			return [];
		}

		$finder = new NodeFinder();
		$violations = $finder->find($node->stmts, function (Node $n) use ($entityParamName): bool {
			if (!$n instanceof MethodCall) {
				return false;
			}
			$callName = $n->name instanceof NodeIdentifier ? $n->name->toString() : null;
			if ($callName !== 'saveEntity' && $callName !== 'save') {
				return false;
			}
			$firstArg = $n->args[0]->value ?? null;
			if (!($firstArg instanceof Variable && is_string($firstArg->name) && $firstArg->name === $entityParamName)) {
				return false;
			}
			
			// Check if skip options are provided that would prevent hook retriggering
			$secondArg = $n->args[1]->value ?? null;
			if ($secondArg instanceof Array_) {
				return !$this->hasSkipOption($secondArg);
			}
			
			// No options provided, this is a violation
			return true;
		});

		if ($violations === []) {
			return [];
		}

		$message = $methodName === 'afterSave'
			? 'Do not save the same entity inside afterSave: this re-triggers hooks and can cause an infinite loop. If saving is required, pass SaveOptions with skipHooks=true or silent=true.'
			: 'Avoid saving the same entity inside beforeSave: saving is already in progress and this re-enters the save flow. If saving is required, pass SaveOptions with skipHooks=true or silent=true.';

		return [
			RuleErrorBuilder::message($message)
				->identifier($this->buildIdentifier())
				->build(),
		];
	}

	/**
	 * Check if the options array contains any SKIP_ option that would prevent hook retriggering
	 */
	private function hasSkipOption(Array_ $options): bool
	{
		foreach ($options->items as $item) {
			if (!$item instanceof ArrayItem || $item->key === null) {
				continue;
			}

			$key = null;
			$value = $item->value;

			// Handle string keys like 'skipHooks' => true
			if ($item->key instanceof String_) {
				$key = $item->key->value;
			}
			// Handle class constant keys like SaveOption::SKIP_HOOKS => true
			elseif ($item->key instanceof ClassConstFetch) {
				if ($item->key->class instanceof Name && $item->key->name instanceof NodeIdentifier) {
					$className = $item->key->class->toString();
					$constName = $item->key->name->toString();
					
					// Check if it's a SaveOption class and a SKIP_ constant
					if (str_contains($className, 'SaveOption') && str_starts_with($constName, 'SKIP_')) {
						$key = $constName;
					}
				}
			}

			if ($key !== null && str_starts_with($key, 'skip') && $this->isTruthyValue($value)) {
				return true;
			}
			if ($key !== null && str_starts_with($key, 'SKIP_') && $this->isTruthyValue($value)) {
				return true;
			}
			if ($key !== null && $key === 'silent' && $this->isTruthyValue($value)) {
				return true;
			}
			if ($key !== null && $key === 'SILENT' && $this->isTruthyValue($value)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a value is truthy (true, non-zero number, non-empty string)
	 */
	private function isTruthyValue(Node $value): bool
	{
		// Handle boolean constants
		if ($value instanceof ConstFetch && $value->name instanceof Name) {
			$name = strtolower($value->name->toString());
			if ($name === 'true') {
				return true;
			}
			if ($name === 'false') {
				return false;
			}
			if ($name === 'null') {
				return false;
			}
		}

		// For numbers, strings, and other values - be conservative and assume truthy
		// unless explicitly false/null
		return true;
	}
}
