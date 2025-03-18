# Code Quality Tools

This document describes the code quality tools and standards used in the MONEI Payment module for Adobe Commerce (Magento 2).

## Overview

The MONEI Payment Module for Adobe Commerce (Magento 2) adheres to Magento Marketplace standards. We use multiple tools to ensure code quality and maintain high standards.

## Prerequisites

- PHP 8.1 or higher
- Composer 2

## Setting up development environment

```bash
# Clone the repository
git clone https://github.com/MONEI/MONEI-AdobeCommerce-Magento2.git
cd MONEI-AdobeCommerce-Magento2

# Install dependencies
composer install
```

## Available Commands

```bash
# Run all code quality checks (errors-only mode by default)
composer lint

# Fix coding standards automatically
composer fix

# Run PHP-CS-Fixer check
composer cs:check

# Run PHP-CS-Fixer auto-fix
composer cs:fix

# Run PHP_CodeSniffer check
composer phpcs

# Run PHP_CodeSniffer auto-fix
composer phpcbf

# Run pretty-php check
composer pretty:check

# Run pretty-php with diff output
composer pretty:diff

# Run pretty-php auto-fix
composer pretty:fix

# Run all fixers (PHP_CodeSniffer, PHP-CS-Fixer, and pretty-php)
composer fix:all

# Check for critical security issues
./scripts/check-critical.sh
```

## Code Standards

The code quality tools check for:

- PHP syntax errors
- PSR-12 and Magento 2 coding standards
- Code style and formatting
- Potential security vulnerabilities
- Critical issues and bugs
- PHPDoc comments compliance

## Tools Used

### PHP_CodeSniffer

Checks for PSR-12 and Magento 2 coding standards.

### PHP-CS-Fixer

Fixes code style issues automatically.

### pretty-php

Formats code for readability and consistency.

For more details on pretty-php usage, see the [pretty-php documentation](PRETTY-PHP.md).

## Fixing Code Issues

To automatically fix coding standards and style issues:

```bash
# Fix coding standards and style issues automatically
composer fix:all
```
