# MONEI Payment Module for Magento 2

## Commands
```bash
# Code quality
composer lint              # Run all code quality checks
composer fix               # Fix coding standards automatically
composer cs:check          # Run PHP-CS-Fixer check
composer cs:fix            # Run PHP-CS-Fixer auto-fix
composer pretty:check      # Check formatting with pretty-php
composer pretty:fix        # Auto-fix with pretty-php

# Frontend
yarn format                # Format code with prettier

# Magento commands (using helper script)
./run-magento.sh setup:di:compile              # Compile dependency injection
./run-magento.sh setup:upgrade                 # Run module upgrades
./run-magento.sh cache:clean                   # Clean caches
./run-magento.sh cache:flush                   # Flush all caches
./run-magento.sh module:enable Monei_MoneiPayment  # Enable the module
```

## Code Style
- Follow PSR-12 with Magento 2 specifics
- 4 spaces for indentation
- Classes: StudlyCaps (e.g., `MoneiApiClient`)
- Methods: camelCase (e.g., `processPayment`)
- Interfaces: StudlyCaps with Interface suffix
- Proper PHPDoc for all classes, methods, and properties
- Strong typing with parameter and return type hints
- Use dependency injection, no direct instantiation

## Project Structure
- `Api`: Interfaces for service contracts
- `Model`: Core business logic and data
- `Service`: Payment processing and API integration
- `Controller`: HTTP request handling
- `view`: Frontend templates and JS components

## MONEI SDK Integration Patterns
- Use `MoneiApiClient` for SDK initialization and management
- Implement proper error handling with detailed logging
- Follow standardized response formatting
- Ensure proper handling of API responses with type checking
- Use enhanced error recovery strategies