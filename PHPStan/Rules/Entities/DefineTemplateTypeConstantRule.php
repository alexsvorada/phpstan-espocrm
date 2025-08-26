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

/** @implements Rule<Class_> */
final class DefineTemplateTypeConstantRule implements Rule
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

		if ($node->name === null) {
			return [];
		}

		$found = false;
		$valid = false;
		foreach ($node->getConstants() as $constStmt) {
			if (!$constStmt->isPublic()) {
				continue;
			}

			foreach ($constStmt->consts as $const) {
				if ($const->name->toString() !== 'TEMPLATE_TYPE') {
					continue;
				}
				$found = true;
				$value = $const->value ?? null;
				if ($value instanceof StringScalar) {
					$valid = in_array($value->value, ['Base', 'BasePlus', 'Event'], true);
				}
			}
		}

		if (!$found) {
			return [
				RuleErrorBuilder::message("Entity must define public const string TEMPLATE_TYPE with value 'Base', 'BasePlus' or 'Event'.")
					->identifier($this->buildIdentifier())
					->build(),
			];
		}

		if (!$valid) {
			return [
				RuleErrorBuilder::message("Entity TEMPLATE_TYPE must be 'Base', 'BasePlus' or 'Event'.")
					->identifier($this->buildIdentifier())
					->build(),
			];
		}

		return [];
	}
}
