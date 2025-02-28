# MONEI Payments Adobe Commerce (Magento 2) Official Module

## Description
To accept payments through [MONEI](https://monei.com) in your Adobe Commerce (Magento 2) store you simply need to install and configure MONEI Adobe Commerce Extension.

## [Live Demo](https://magento2-demo.monei.com/)

## Minimum Compatibility
- PHP: ^8.1.0
- Magento: >=2.4.5

## Installation Instructions
#### You can install this package using composer by adding it to your composer file using following command:

`composer require monei/module-monei-payment`

#### Enable module 

`php magento/bin/magento module:enable Monei_MoneiPayment`

#### Finally, run setup upgrade to enable new modules:

`php magento/bin/magento setup:upgrade`

For more information, please refer to the [official documentation](https://docs.monei.com/docs/e-commerce/adobe-commerce/).

## Development

### Docker Setup

This module includes a Docker setup for local development with PHP 8.3. To use Docker:

```bash
# Start the Docker containers
docker-compose up -d

# Execute commands in the PHP container
docker-compose exec php bash
```

For detailed Docker setup instructions, see the [Docker README](docker/README.md).

### Code Quality Tools

This module includes code quality tools to ensure code standards are maintained. The following tools are available:

#### Requirements
- PHP 8.1 or higher
- Composer 2

#### Setting up development environment

```bash
# Clone the repository
git clone https://github.com/MONEI/MONEI-AdobeCommerce-Magento2.git
cd MONEI-AdobeCommerce-Magento2

# Install dependencies
composer install
```

#### Code Quality Tools

```bash
# Run all code quality checks (runs in errors-only mode by default)
composer lint

# Fix coding standards automatically
composer fix

# Run PHP-CS-Fixer check
composer cs-check

# Run PHP-CS-Fixer auto-fix
composer cs-fix

# Run PHP_CodeSniffer check
composer phpcs

# Run PHP_CodeSniffer auto-fix
composer phpcbf

# Check for critical security issues
./scripts/check-critical.sh
```

The code quality tools check for:
- PHP syntax errors
- PSR-12 and Magento 2 coding standards
- Proper code style and formatting
- Potential security vulnerabilities
- Critical issues and bugs
- Missing PHPDoc comments (handled by PHP_CodeSniffer)

#### Environment Variables

The following environment variables can be used with the linting and fixing scripts:

```bash
# Skip PHP-CS-Fixer checks when running the lint script
SKIP_CS_FIXER=1 composer lint

# Run fix script with only CS Fixer (skips PHP_CodeSniffer)
FIX_CS_ONLY=1 composer fix
```

Note: The lint script already runs in errors-only mode by default (ignores warnings).

### Developer Documentation

For detailed information about common issues, coding standards, and best practices, see the [Developer Documentation](docs/DEVELOPMENT.md).

