# EspoCRM PHPStan Rules

> PHPStan extension providing custom rules for EspoCRM development to enforce coding standards and best practices.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Rules](#rules)
- [Usage](#usage)

## Requirements

- [PHP](https://www.php.net/) (>= 8.1)
- [PHPStan](https://phpstan.org/) (>= 2.1)

## Installation

### 1. Install via Composer

```bash
composer require --dev alexsvorada/phpstan-espocrm
```

### 2. Add to your phpstan.neon

```neon
includes:
    - vendor/alexsvorada/phpstan-espocrm/extension.neon
```

## Rules

This extension provides the following PHPStan rules for EspoCRM:

### Entity Rules
- **DefineEntityTypeConstantRule**: Ensures entities define the `ENTITY_TYPE` constant
- **DefineTemplateTypeConstantRule**: Ensures entities define template type constants when needed
- **NoEntityManagerInEntityRule**: Prevents direct EntityManager usage in entity classes

### Service Rules
- **CallParentConstructorRule**: Ensures parent constructor is called in service classes
- **ServiceMustExtendRecordRule**: Ensures services extend the appropriate Record class

### Hook Rules
- **NoSameEntitySaveRule**: Prevents saving the same entity within hooks to avoid infinite loops
- **SaveRestrictedEntityFieldsRule**: Prevents saving restricted entity fields in hooks
- **RequireOrderPropertyRule**: Ensures hooks define the required `$order` property

### Controller Rules
- **OnlyOverrideParentMethodsRule**: Ensures controllers only override parent methods, not create arbitrary ones

## Usage

After installation, run PHPStan as usual:

```bash
vendor/bin/phpstan analyse
```

The rules will automatically be applied to your EspoCRM codebase, helping maintain code quality and prevent common issues.

## Development

This extension follows EspoCRM coding standards and provides rules to enforce best practices in EspoCRM module development.