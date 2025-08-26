<?php

declare(strict_types=1);

namespace AlexSvorada\EspoCRM\PHPStan\Rules\Entities;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use AlexSvorada\EspoCRM\PHPStan\Rules\Core\AutocrmIdentifierTrait;

/** @implements Rule<ClassMethod> */
final class NoEntityManagerInEntityRule implements Rule
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
		if (strpos($filePath, '/src/backend/Entities/') === false) {
			return [];
		}

		if ($node->stmts === null) {
			return [];
		}

		$finder = new NodeFinder();
		$violations = $finder->find($node->stmts, function (Node $n): bool {
			// $this->entityManager->... OR $this->getEntityManager()->...
			if ($n instanceof PropertyFetch) {
				return $n->name instanceof Node\Identifier && $n->name->toString() === 'entityManager';
			}
			if ($n instanceof MethodCall) {
				return $n->name instanceof Node\Identifier && $n->name->toString() === 'getEntityManager';
			}
			return false;
		});

		if ($violations === []) {
			return [];
		}

		return [
			RuleErrorBuilder::message('Entities must not use EntityManager, move business logic to the corresponding Service class.')
				->identifier($this->buildIdentifier())
				->build(),
		];
	}
}
