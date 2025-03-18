# Using pretty-php for Code Formatting

This document provides information on how to use the pretty-php tool to format code in the MONEI Payment module for Adobe Commerce (Magento 2).

## Overview

pretty-php is a PHP code formatter that helps maintain consistent code style across the project. It's used alongside PHP_CodeSniffer and PHP-CS-Fixer to ensure high code quality.

## Installation

The pretty-php tool is included in the repository as a PHAR file and can be executed using the Composer scripts defined in `composer.json`.

## Configuration

The pretty-php tool is configured using a `prettyphp.json` file in the root of the project. This file defines the formatting rules and preferences.

Example configuration:

```json
{
  "preset": "magento2",
  "indent": "    ",
  "lineEnding": "\n"
}
```

## Usage

### Check Code Style

To check if your code follows the formatting standards without making changes:

```bash
composer pretty:check
```

This will report any formatting issues without modifying files.

### View Formatting Differences

To see what changes pretty-php would make:

```bash
composer pretty:diff
```

This shows a diff of the current code and how it would look after formatting.

### Automatically Fix Formatting

To automatically fix formatting issues:

```bash
composer pretty:fix
```

This will modify files to adhere to the formatting standards.

## Integration with CI/CD

pretty-php can be integrated into your CI/CD pipeline to enforce code style:

```yaml
# Example GitHub Actions workflow step
- name: Check code formatting
  run: composer pretty:check
```

## Common Formatting Rules

The pretty-php tool enforces several formatting rules:

- PSR-12 compatibility
- Consistent indentation
- Proper spacing around operators
- Consistent brace placement
- Organized imports

## Combining with Other Tools

For complete code quality checks, combine pretty-php with other tools:

```bash
# Run all code quality tools
composer check:all

# Fix all code style issues
composer fix:all
```

## Troubleshooting

### Permission Issues

If you encounter permission problems with the PHAR file:

```bash
chmod +x pretty-php.phar
```

### Conflicts with Other Tools

If pretty-php conflicts with PHP_CodeSniffer or PHP-CS-Fixer, review the configuration files to ensure they have compatible rules.
