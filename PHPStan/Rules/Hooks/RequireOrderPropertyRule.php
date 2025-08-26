<?php

declare(strict_types=1);

namespace AlexSvorada\EspoCRM\PHPStan\Rules\Hooks;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use AlexSvorada\EspoCRM\PHPStan\Rules\Core\AutocrmIdentifierTrait;

/** @implements Rule<Class_> */
final class RequireOrderPropertyRule implements Rule
{
	use AutocrmIdentifierTrait;

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
		if (strpos($filePath, '/src/backend/Hooks/') === false) {
			return [];
		}

		if ($node->name === null) {
			return [];
		}

		$hasOrder = false;
		foreach ($node->getProperties() as $property) {
			if (!$property instanceof Property) {
				continue;
			}
			$firstProp = $property->props[0] ?? null;
			if ($firstProp === null) {
				continue;
			}
			if ($firstProp->name->toString() !== 'order') {
				continue;
			}
			if (!$property->isPublic() || !$property->isStatic()) {
				continue;
			}
			$type = $property->type;
			if (!$type instanceof Identifier || $type->toString() !== 'int') {
				continue;
			}
			$hasOrder = true;
			break;
		}

		if ($hasOrder) {
			return [];
		}

		return [
			RuleErrorBuilder::message('Hook classes must define public static int $order.')
				->identifier($this->buildIdentifier())
				->build(),
		];
	}
}
