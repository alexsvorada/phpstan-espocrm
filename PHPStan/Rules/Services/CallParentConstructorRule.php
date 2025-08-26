<?php

declare(strict_types=1);

namespace AlexSvorada\EspoCRM\PHPStan\Rules\Services;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use AlexSvorada\EspoCRM\PHPStan\Rules\Core\HasIdentifierBuilder;

/**
 * @implements Rule<ClassMethod>
 */
final class CallParentConstructorRule implements Rule
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
		if ($node->name->toString() !== '__construct') {
			return [];
		}

		$filePath = $scope->getFile();
		if (strpos($filePath, '/src/backend/Services/') === false) {
			return [];
		}

		if ($node->stmts === null) {
			return [];
		}

		$finder = new NodeFinder();
		$parentCtorCall = $finder->findFirst($node->stmts, function (Node $n): bool {
			if (!$n instanceof StaticCall) {
				return false;
			}
			if (!($n->class instanceof Name)) {
				return false;
			}
			$calledClass = $n->class->toString();
			$calledNameIsConstructor = $n->name instanceof Node\Identifier && $n->name->toString() === '__construct';
			return $calledClass === 'parent' && $calledNameIsConstructor;
		});

		if ($parentCtorCall instanceof StaticCall) {
			return [];
		}

		return [
			RuleErrorBuilder::message('Service constructors must call parent::__construct().')
				->identifier($this->buildIdentifier())
				->build(),
		];
	}
}


