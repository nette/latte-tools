# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Latte Tools** is a library for converting PHP templates and Twig templates to Latte (Nette's templating engine). The project provides two main converters:

- **PhpConverter**: Transforms PHP templates with echo statements into Latte syntax
- **TwigConverter**: Transforms Twig templates into Latte templates

The project includes "Twiggy" - a complete custom implementation of the Twig parser/compiler (~4,650 lines of code) that allows parsing Twig templates without depending on the actual Twig library.

## Essential Commands

```bash
# Run all tests
composer run tester

# Run a specific test file directly
php tests/PhpTest.php
php tests/TwigTest.php

# Run static analysis
composer run phpstan

# Convert a PHP file to Latte
php php-to-latte.php input.php [output.latte]

# Convert a Twig file to Latte
php twig-to-latte.php input.twig [output.latte]
```

## Architecture

### PhpConverter Pipeline

The PHP converter uses nikic/php-parser to parse and transform PHP code through multiple AST transformation passes:

1. **Parse**: PHP code → AST using nikic/php-parser
2. **Transform** (multiple passes):
   - `expandEcho()`: Split `echo a, b;` → `echo a; echo b;`
   - `removeHtmlSpecialChars()`: Remove redundant `htmlspecialchars()` calls (runs twice)
   - `expandConcat()`: Expand string concatenations for better Latte output
   - `stringToHtml()`: Convert literal strings to inline HTML
3. **Print**: Use custom `LattePrinter` (extends nikic's PrettyPrinter) to output Latte syntax

**Key class**: `src/PhpConverter.php` - orchestrates the transformation pipeline

### TwigConverter Pipeline

The Twig converter uses the custom Twiggy parser implementation:

1. **Initialize**: Set up Twiggy environment with extensions (Cache, Html, Debug, Sandbox)
2. **Parse**: Twig template → AST using Twiggy lexer/parser
3. **Transform**: `LatteNodeVisitor` walks the AST converting Twig constructs to Latte
4. **Compile**: Generate Latte output
5. **Post-process**: Pattern replacements (e.g., `class="{html_classes(...)}"` → `n:class="..."`)

**Key classes**:
- `src/TwigConverter.php`: Main converter orchestration
- `src/Twiggy/`: Complete Twig parser implementation
  - `Environment.php`: Twig environment setup
  - `Lexer.php`: Tokenization
  - `Parser.php`: AST generation
  - `Compiler.php`: Compilation logic
  - `NodeVisitor/LatteNodeVisitor.php`: Twig→Latte AST transformation

### Twiggy Architecture

Twiggy is organized into several subsystems:

- **Core parsing**: `Lexer.php`, `Parser.php`, `Compiler.php`
- **Node types**: `Node/` directory contains all AST node types (expressions, statements, operators)
- **Extensions**: `Extension/` provides Core, Debug, Escaper, Optimizer, Sandbox functionality
- **Token parsers**: `TokenParser/` handles parsing of specific Twig tags (if, for, block, etc.)
- **Node visitors**: `NodeVisitor/` for AST transformation and optimization
- **Loaders**: `Loader/` for template loading (ArrayLoader used for conversion)
- **Extra features**: `Extra/` for Cache and HTML support

### Custom Printer

`src/LattePrinter.php` extends nikic/php-parser's `PrettyPrinterAbstract` to customize PHP AST printing for Latte output. This is where PHP syntax gets transformed into Latte template syntax during the printing phase.

## Testing Strategy

Tests use **fixture-based comparison**:
- Each test has paired input/output files (`.php`/`.latte` or `.twig`/`.latte`)
- Test runners iterate through fixtures, convert, and compare output using `Assert::match()`
- Fixtures are organized by category in `tests/fixtures-php/` and `tests/fixtures-twig/`

**Fixture categories for Twig**:
- `expressions/`: Expression syntax tests
- `filters/`: Filter conversion tests
- `functions/`: Function conversion tests
- `macros/`: Macro conversion tests
- `tags/`: Tag conversion tests (if, for, block, extends, etc.)
- `tests/`: Test expression tests

**Adding new test cases**:
1. Create input file (`.php` or `.twig`) in appropriate fixtures directory
2. Create expected output file (`.latte`) with same name
3. Run tests - the test runner automatically picks up new fixtures

## Code Standards

- PHP 8.0+ with `declare(strict_types=1)` in every file
- Follows Nette Coding Standard (PSR-12 based)
- Use TABS for indentation
- Type declarations required for all properties, parameters, and return values
- PHPStan static analysis enforced

## Dependencies

**Core**:
- `nikic/php-parser` (^4.10): AST parsing for PhpConverter

**Development**:
- `nette/tester` (^2.3): Test framework
- `phpstan/phpstan` (^0.12): Static analysis
- `nette/finder` (^2.5): File iteration utilities
- `tracy/tracy` (^2.8): Debugging/error handling

**Note**: The project does NOT depend on the actual Twig library - it implements its own Twig parser (Twiggy) for conversion purposes.
