<?php

declare(strict_types=1);

namespace AlexSvorada\EspoCRM\PHPStan\Rules\Services;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use AlexSvorada\EspoCRM\PHPStan\Rules\Core\HasIdentifierBuilder;

/**
 * @implements Rule<Class_>
 */
final class DependencyVisibilityOrFinalRule implements Rule
{
	use HasIdentifierBuilder;

	public function getNodeType(): string
	{
		return Class_::class;
	}

	/**
	 * @param Class_ $node
	 * @return array<int, \PHPStan\Rules\RuleError>
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		$filePath = $scope->getFile();
		if (strpos($filePath, '/src/backend/Services/') === false) {
			return [];
		}

		// If class is final, no restriction applies
		if ($node->isFinal()) {
			return [];
		}

		// Find constructor
		$finder = new NodeFinder();
		/** @var ClassMethod|null $ctor */
		$ctor = $finder->findFirst($node->getMethods(), function (Node $n): bool {
			return $n instanceof ClassMethod && $n->name->toString() === '__construct';
		});

		if (!$ctor instanceof ClassMethod) {
			return [];
		}

		$hasViolation = false;
		foreach ($ctor->params as $param) {
			// Constructor property promotion: visibility flags on param
			if (($param->flags & (Class_::MODIFIER_PRIVATE | Class_::MODIFIER_PROTECTED | Class_::MODIFIER_PUBLIC)) === 0) {
				continue; // not promoted
			}
			if (($param->flags & Class_::MODIFIER_PRIVATE) !== 0) {
				$hasViolation = true;
				break;
			}
		}

		if (!$hasViolation) {
			return [];
		}

		return [
			RuleErrorBuilder::message('Non-final service class must not use private promoted dependencies; use protected visibility (preferred) or make the class final.')
				->identifier($this->buildIdentifier())
				->build(),
		];
	}
}


