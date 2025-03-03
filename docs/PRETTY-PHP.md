# Using pretty-php in MONEI Payment Module

This project uses [pretty-php](https://github.com/lkrms/pretty-php) as an additional code formatter to ensure consistent code style across the codebase.

## What is pretty-php?

pretty-php is a fast, deterministic, minimally configurable code formatter for PHP, written in PHP. It's inspired by [Black](https://github.com/psf/black) for Python and aims to produce the smallest diffs possible while ensuring code looks the same regardless of the project you're working on.

Key features:
- Formats code for readability, consistency, and small diffs
- Previous formatting is ignored, and nothing other than whitespace is changed (with some exceptions)
- Compliant with PSR-12 and PER coding standards
- Runs without configuration (though we provide a basic configuration)

## Installation

The pretty-php PHAR file is not included in the repository. You need to download it:

```bash
curl -L -o pretty-php.phar https://github.com/lkrms/pretty-php/releases/latest/download/pretty-php.phar
chmod +x pretty-php.phar
```

## Usage

We've added several Composer scripts to make using pretty-php easier:

```bash
# Check if files need formatting (without making changes)
composer pretty:check

# Show diff of changes that would be made
composer pretty:diff

# Format files
composer pretty:fix

# Run all fixers (PHP_CodeSniffer, PHP-CS-Fixer, and pretty-php)
composer fix:all
```

## Configuration

We use a minimal configuration for pretty-php in the `.prettyphp` file at the root of the project. The configuration:

- Uses 4 spaces for indentation
- Sorts imports by name
- Aligns comments
- Follows PSR-12 standards
- Excludes vendor, node_modules, and other non-source directories

## CI Integration

pretty-php is integrated into our GitHub Actions workflow. The `code-quality.yml` workflow runs pretty-php checks on pull requests and pushes to main branches.

## Differences from PHP-CS-Fixer

While we also use PHP-CS-Fixer, pretty-php offers some advantages:

1. It's faster and more deterministic
2. It focuses solely on formatting (whitespace, indentation, etc.)
3. It's more opinionated, reducing the need for extensive configuration
4. It produces more consistent results across different files

We use both tools together to ensure the highest code quality standards.

## Troubleshooting

If you encounter issues with pretty-php:

1. Make sure you have the latest version of the PHAR file
2. Check that your PHP version is compatible (PHP 7.4 or higher)
3. Verify that the `.prettyphp` configuration file is valid
4. Try running with the `--verbose` flag for more detailed output:
   ```bash
   ./pretty-php.phar --verbose .
   ```

For more information, see the [pretty-php documentation](https://github.com/lkrms/pretty-php). 
