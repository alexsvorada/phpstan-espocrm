<?php

declare(strict_types=1);

namespace AlexSvorada\EspoCRM\PHPStan\Rules\Controllers;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use AlexSvorada\EspoCRM\PHPStan\Rules\Core\HasIdentifierBuilder;

/**
 * Controllers under src/backend/Controllers/ may only override parent methods.
 * New methods should not be added – use Api actions under src/backend/Api/ instead.
 *
 * @implements Rule<ClassMethod>
 */
final class OnlyOverrideParentMethodsRule implements Rule
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
		if (strpos($filePath, '/src/backend/Controllers/') === false) {
			return [];
		}

		$classReflection = $scope->getClassReflection();
		if (!$classReflection instanceof ClassReflection) {
			return [];
		}

		$parent = $classReflection->getParentClass();
		if ($parent === null) {
			return [];
		}

		$methodName = $node->name->toString();
		// Only allow overriding methods that exist in parent hierarchy
		if ($parent->hasMethod($methodName)) {
			return [];
		}

		return [
			RuleErrorBuilder::message('Controllers should not declare new methods, only override methods defined by the parent. For new endpoints, implement API actions under src/backend/Api/ instead.')
				->identifier($this->buildIdentifier())
				->build(),
		];
	}
}


