<?php

declare(strict_types=1);

namespace AlexSvorada\EspoCRM\PHPStan\Rules\Services;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use AlexSvorada\EspoCRM\PHPStan\Rules\Core\HasIdentifierBuilder;

/** @implements Rule<Class_> */
final class ServiceMustExtendRecordRule implements Rule
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
		if ($node->name === null) {
			return [];
		}

		$extends = $node->extends;
		$extendsFqn = $extends instanceof FullyQualified ? $extends->toString() : ($extends?->toString() ?? '');
		$extendsRecord = $extendsFqn === 'Espo\\Services\\Record';

		if ($extendsRecord) {
			return [];
		}

		return [
			RuleErrorBuilder::message('Services under src/backend/Services/ must extend Espo\\Services\\Record. Place non-entity utilities under src/backend/Tools/.')
				->identifier($this->buildIdentifier())
				->build(),
		];
	}
}
