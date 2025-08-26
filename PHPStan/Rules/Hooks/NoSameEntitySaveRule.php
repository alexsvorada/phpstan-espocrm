<?php

declare(strict_types=1);

namespace AlexSvorada\EspoCRM\PHPStan\Rules\Hooks;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier as NodeIdentifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use AlexSvorada\EspoCRM\PHPStan\Rules\Core\AutocrmIdentifierTrait;

/** @implements Rule<ClassMethod> */
final class NoSameEntitySaveRule implements Rule
{
	use AutocrmIdentifierTrait;

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
			return $firstArg instanceof Variable && is_string($firstArg->name) && $firstArg->name === $entityParamName;
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
}
