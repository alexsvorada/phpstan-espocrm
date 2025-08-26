<?php

declare(strict_types=1);

namespace AlexSvorada\EspoCRM\PHPStan\Rules\Core;

trait HasIdentifierBuilder
{
	protected function buildIdentifier(): string
	{
		$fqn = static::class;
		$parts = explode('\\', $fqn);
		$className = end($parts) ?: $fqn;
		$domain = 'core';
		$rulesIndex = array_search('Rules', $parts, true);
		if ($rulesIndex !== false && isset($parts[$rulesIndex + 1])) {
			$domain = strtolower($parts[$rulesIndex + 1]);
		}
		return 'espocrm.' . $domain . '.' . lcfirst($className);
	}
}
