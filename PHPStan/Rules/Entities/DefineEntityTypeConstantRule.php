<?php

declare(strict_types=1);

namespace AlexSvorada\EspoCRM\PHPStan\Rules\Entities;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_ as StringScalar;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use AlexSvorada\EspoCRM\PHPStan\Rules\Core\HasIdentifierBuilder;

/**
 * Ensures entity classes under src/backend/Entities/ define
 *   public const string ENTITY_TYPE matching the class name
 *
 * @implements Rule<Class_>
 */
final class DefineEntityTypeConstantRule implements Rule
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
		if (strpos($filePath, '/src/backend/Entities/') === false) {
			return [];
		}

		// Skip anonymous classes
		if ($node->name === null) {
			return [];
		}

		$found = false;
		$valid = false;
		$className = $node->name->toString();

		foreach ($node->getConstants() as $constStmt) {
			if (!$constStmt->isPublic()) {
				continue;
			}

			foreach ($constStmt->consts as $const) {
				if ($const->name->toString() === 'ENTITY_TYPE') {
					$found = true;
					$value = $const->value ?? null;
					if ($value instanceof StringScalar && $value->value === $className) {
						$valid = true;
					}
				}
			}
		}

		if (!$found) {
			return [
				RuleErrorBuilder::message('Entity must define public const string ENTITY_TYPE matching the class name.')
					->identifier($this->buildIdentifier())
					->build(),
			];
		}

		if (!$valid) {
			return [
				RuleErrorBuilder::message(sprintf(
					"ENTITY_TYPE constant must match the class name: '%s'.",
					$className
				))
					->identifier($this->buildIdentifier())
					->build(),
			];
		}

		return [];
	}
}
