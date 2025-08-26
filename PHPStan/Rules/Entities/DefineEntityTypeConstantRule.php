<?php

declare(strict_types=1);

namespace AlexSvorada\EspoCRM\PHPStan\Rules\Entities;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use AlexSvorada\EspoCRM\PHPStan\Rules\Core\HasIdentifierBuilder;

/**
 * Ensures entity classes under src/backend/Entities/ define
 *   public const string ENTITY_TYPE
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

		foreach ($node->getConstants() as $constStmt) {
			if (!$constStmt->isPublic()) {
				continue;
			}

			// Require typed string constant (PHP 8.3+)
			if (!$constStmt->type instanceof Identifier || $constStmt->type->toString() !== 'string') {
				continue;
			}

			foreach ($constStmt->consts as $const) {
				if ($const->name->toString() === 'ENTITY_TYPE') {
					return [];
				}
			}
		}

		return [
			RuleErrorBuilder::message('Entity must define public const string ENTITY_TYPE.')
				->identifier($this->buildIdentifier())
				->build(),
		];
	}
}


