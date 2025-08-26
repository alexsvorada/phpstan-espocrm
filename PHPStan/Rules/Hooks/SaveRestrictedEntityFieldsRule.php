<?php

declare(strict_types=1);

namespace AlexSvorada\EspoCRM\PHPStan\Rules\Hooks;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_ as StringScalar;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use AlexSvorada\EspoCRM\PHPStan\Rules\Core\HasIdentifierBuilder;

/** @implements Rule<ClassMethod> */
final class SaveRestrictedEntityFieldsRule implements Rule
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
		if (strpos($filePath, '/src/backend/') === false) {
			return [];
		}

		if ($node->stmts === null) {
			return [];
		}

		$finder = new NodeFinder();

		$varToSelectedFields = [];
		$varToEntityType = [];

		$assignments = $finder->find($node->stmts, function (Node $n): bool {
			return $n instanceof Assign && $n->expr instanceof MethodCall;
		});

		foreach ($assignments as $assign) {
			if (!$assign instanceof Assign) {
				continue;
			}
			$var = $assign->var;
			if (!$var instanceof Variable || !is_string($var->name)) {
				continue;
			}
			$expr = $assign->expr;
			if (!$expr instanceof MethodCall) {
				continue;
			}
			$selected = $this->extractSelectedFieldsFromChain($expr);
			if ($selected !== null) {
				$varToSelectedFields[$var->name] = $selected;
			}
			$entityTypeFromChain = $this->inferEntityTypeFromChain($expr);
			if ($entityTypeFromChain !== null) {
				$varToEntityType[$var->name] = $entityTypeFromChain;
			}
		}

		$errors = [];
		$saves = $finder->find($node->stmts, function (Node $n): bool {
			return $n instanceof MethodCall;
		});

		foreach ($saves as $call) {
			if (!$call instanceof MethodCall) {
				continue;
			}
			if (!($call->name instanceof Node\Identifier) || $call->name->toString() !== 'saveEntity') {
				continue;
			}
			$entityArg = $call->args[0]->value ?? null;
			if (!$entityArg instanceof Variable || !is_string($entityArg->name)) {
				continue;
			}

			$hasOptionsArg = isset($call->args[1]);
			if ($hasOptionsArg) {
				continue;
			}

			$selectedFields = $varToSelectedFields[$entityArg->name] ?? null;
			if ($selectedFields === null) {
				continue;
			}

			$entityType = $this->inferEntityTypeFromVarType($scope, $entityArg);
			if ($entityType === null) {
				$entityType = $varToEntityType[$entityArg->name] ?? null;
			}
			$requiredFields = $entityType !== null ? $this->collectHookRequiredFields($filePath, $entityType) : [];

			if ($requiredFields === []) {
				continue;
			}

			$selectedSet = array_fill_keys(array_map('strval', $selectedFields), true);
			$selectedSet['id'] = true;
			$missing = [];
			foreach ($requiredFields as $f) {
				if (!isset($selectedSet[$f])) {
					$missing[] = $f;
				}
			}

			if ($missing !== []) {
				$errors[] = RuleErrorBuilder::message(
					'Saving partially-loaded ' . ($entityType ?? 'entity') . ' without skipHooks/silent may break hooks; missing fields: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ', …' : '') . '. Add fields to select() or pass SaveOptions with skipHooks=true or silent=true.'
				)
					->identifier($this->buildIdentifier())
					->build();
			}
		}

		return $errors;
	}

	/**
	 * Extract selected field names from a chained method call containing select([...]).
	 * @return list<string>|null
	 */
	private function extractSelectedFieldsFromChain(MethodCall $call): ?array
	{
		$selected = null;
		$curr = $call;
		while ($curr instanceof MethodCall) {
			if ($curr->name instanceof Node\Identifier && $curr->name->toString() === 'select') {
				$arg = isset($curr->args[0]) ? $curr->args[0] : null;
				if ($arg instanceof Arg) {
					$value = $arg->value;
					if ($value instanceof Array_) {
						$fields = [];
						foreach ($value->items as $arrayItem) {
							if (!$arrayItem instanceof ArrayItem) {
								continue;
							}
							$arrayItemValue = $arrayItem->value;
							if ($arrayItemValue instanceof StringScalar) {
								$fields[] = $arrayItemValue->value;
							}
						}
						$selected = $fields;
					} elseif ($value instanceof StringScalar) {
						$selected = [$value->value];
					}
				}
			}
			$curr = $curr->var instanceof MethodCall ? $curr->var : null;
		}
		return $selected;
	}

	private function inferEntityTypeFromVarType(Scope $scope, Variable $var): ?string
	{
		$type = $scope->getType($var);
		$classNames = method_exists($type, 'getObjectClassNames') ? $type->getObjectClassNames() : [];
		foreach ($classNames as $cn) {
			if (preg_match('/\\\\Entities\\\\([A-Za-z0-9_]+)$/', $cn, $m) === 1) {
				return $m[1];
			}
		}
		return null;
	}

	private function inferEntityTypeFromChain(MethodCall $call): ?string
	{
		$curr = $call;
		while ($curr instanceof MethodCall) {
			if ($curr->name instanceof Node\Identifier) {
				$methodName = $curr->name->toString();
				if (($methodName === 'getRDBRepositoryByClass' || $methodName === 'getRepository') && isset($curr->args[0])) {
					$arg = $curr->args[0];
					if ($arg instanceof Arg) {
						$val = $arg->value;
						if ($val instanceof ClassConstFetch) {
							$ccf = $val;
							if ($ccf->class instanceof Name) {
								$cn = $ccf->class->toString();
								if (preg_match('/\\\\Entities\\\\([A-Za-z0-9_]+)$/', $cn, $m) === 1) {
									return $m[1];
								}
							}
						}
					}
				}
			}
			$curr = $curr->var instanceof MethodCall ? $curr->var : null;
		}
		return null;
	}

	/**
	 * @return list<string>
	 */
	private function collectHookRequiredFields(string $contextFilePath, string $entityType): array
	{
		$backendPos = strpos($contextFilePath, '/src/backend/');
		if ($backendPos === false) {
			return [];
		}
		$moduleRoot = substr($contextFilePath, 0, $backendPos);
		$hooksDir = $moduleRoot . '/src/backend/Hooks/' . $entityType;
		if (!is_dir($hooksDir)) {
			return [];
		}

		$required = [];
		$files = glob($hooksDir . '/*.php') ?: [];
		$parserFactory = new ParserFactory();
		$parser = null;
		if (method_exists($parserFactory, 'createForNewestSupportedVersion')) {
			$parser = $parserFactory->createForNewestSupportedVersion();
		} elseif (method_exists($parserFactory, 'create')) {
			$mode = null;
			if (defined('PhpParser\\ParserFactory::PREFER_PHP7')) {
				$mode = constant('PhpParser\\ParserFactory::PREFER_PHP7');
			} elseif (defined('PhpParser\\ParserFactory::PREFER_PHP5')) {
				$mode = constant('PhpParser\\ParserFactory::PREFER_PHP5');
			}
			$parser = $parserFactory->create($mode);
		}
		$finder = new NodeFinder();

		foreach ($files as $file) {
			$code = @file_get_contents($file);
			if ($code === false) {
				continue;
			}
			try {
				$ast = $parser ? ($parser->parse($code) ?: []) : [];
			} catch (\Throwable) {
				continue;
			}

			$methods = $finder->findInstanceOf($ast, ClassMethod::class);
			foreach ($methods as $method) {
				if (!$method instanceof ClassMethod) {
					continue;
				}
				$name = $method->name->toString();
				if ($name !== 'afterSave' && $name !== 'beforeSave') {
					continue;
				}
				$entityParam = $method->params[0]->var ?? null;
				if (!$entityParam instanceof Variable || !is_string($entityParam->name)) {
					continue;
				}
				$paramName = $entityParam->name;
				if ($method->stmts === null) {
					continue;
				}
				$reads = $finder->find($method->stmts, function (Node $n) use ($paramName): bool {
					if (!$n instanceof MethodCall) {
						return false;
					}
					if (!($n->var instanceof Variable && is_string($n->var->name) && $n->var->name === $paramName)) {
						return false;
					}
					if (!($n->name instanceof Node\Identifier)) {
						return false;
					}
					$methodName = $n->name->toString();
					if ($methodName !== 'get' && $methodName !== 'getValue' && $methodName !== 'has') {
						return false;
					}
					$firstArg = $n->args[0] ?? null;
					if (!$firstArg instanceof Arg) {
						return false;
					}
					return $firstArg->value instanceof StringScalar;
				});
				foreach ($reads as $r) {
					if (!$r instanceof MethodCall) {
						continue;
					}
					$first = $r->args[0]->value ?? null;
					if ($first instanceof StringScalar) {
						$required[] = $first->value;
					}
				}
			}
		}

		return array_values(array_unique($required));
	}
}
