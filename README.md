# MONEI Payments for Adobe Commerce (Magento 2)

[![Magento Marketplace](https://img.shields.io/badge/Magento-Marketplace-orange.svg)](https://marketplace.magento.com/monei-module-monei-payment.html)
[![Latest Version](https://img.shields.io/packagist/v/monei/module-monei-payment.svg)](https://packagist.org/packages/monei/module-monei-payment)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Accept payments through [MONEI](https://monei.com) in your Adobe Commerce (Magento 2) store with our official extension.

## Table of Contents

- [MONEI Payments for Adobe Commerce (Magento 2)](#monei-payments-for-adobe-commerce-magento-2)
  - [Table of Contents](#table-of-contents)
  - [Overview](#overview)
  - [Features](#features)
  - [Requirements](#requirements)
  - [Installation](#installation)
    - [Via Composer (Recommended)](#via-composer-recommended)
    - [Manual Installation](#manual-installation)
      - [Option 1: Via GitHub Releases](#option-1-via-github-releases)
      - [Option 2: Direct Download from Main Branch](#option-2-direct-download-from-main-branch)
    - [Before You Begin](#before-you-begin)
  - [Configuration](#configuration)
  - [MONEI PHP SDK](#monei-php-sdk)
  - [Demo](#demo)
  - [Development](#development)
    - [Docker Setup](#docker-setup)
    - [Code Quality Tools](#code-quality-tools)
      - [Prerequisites](#prerequisites)
      - [Setting up development environment](#setting-up-development-environment)
      - [Available Commands](#available-commands)
    - [Environment Variables](#environment-variables)
  - [Troubleshooting](#troubleshooting)
  - [Contributing](#contributing)
  - [License](#license)
  - [Support](#support)
  - [Code Validation](#code-validation)
    - [Validation](#validation)
    - [Fixing Code Issues](#fixing-code-issues)

## Overview

MONEI Payments for Adobe Commerce (Magento 2) allows you to seamlessly integrate MONEI's payment processing capabilities into your Magento store. This official module provides a secure, reliable, and user-friendly payment experience for your customers.

## Features

- Accept payments via credit cards, Apple Pay, Google Pay, and more
- Seamless checkout experience with embedded payment forms
- Secure payment processing with full PCI compliance
- Real-time payment notifications and order status updates
- Detailed transaction reporting in your MONEI dashboard
- Customizable payment experience to match your store's design
- Integration with the official MONEI PHP SDK for reliable API communication

## Requirements

- PHP: ^8.1.0
- Magento: >=2.4.5
- MONEI Account ([Sign up here](https://monei.com/signup))
- MONEI PHP SDK: ^2.4.3 (automatically installed with Composer)

## Installation

### Via Composer (Recommended)

1. Add the package to your Magento installation:

```bash
composer require monei/module-monei-payment
```

2. Enable the module:

```bash
php magento/bin/magento module:enable Monei_MoneiPayment
```

3. Run setup upgrade:

```bash
php magento/bin/magento setup:upgrade
```

4. Flush cache:

```bash
php magento/bin/magento cache:flush
```

### Manual Installation

#### Option 1: Via GitHub Releases

1. Download the latest release from the [GitHub repository](https://github.com/MONEI/MONEI-AdobeCommerce-Magento2/releases)
2. Extract the contents to your `app/code/Monei/MoneiPayment` directory
3. Follow steps 2-4 from the Composer installation instructions
4. Install the MONEI PHP SDK:

```bash
composer require monei/monei-php-sdk:^2.4.3
```

#### Option 2: Direct Download from Main Branch

1. Download the [latest version](https://github.com/MONEI/MONEI-AdobeCommerce-Magento2/archive/refs/heads/main.zip) from the main branch
2. Create a directory called `app/code/Monei/MoneiPayment` inside your Magento 2 project
3. Unzip the downloaded archive in this directory
4. Install the MONEI PHP SDK:

```bash
composer require monei/monei-php-sdk:^2.4.3
```

5. Go to your Adobe Commerce (Magento 2) root directory and run:

```bash
php bin/magento module:enable Monei_MoneiPayment
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
php bin/magento cache:clean
```

### Before You Begin

When testing your integration:

- Use your [test mode](https://docs.monei.com/docs/testing) API Key from [MONEI Dashboard → Settings → API Access](https://dashboard.monei.com/settings/api)
- Check the status of test payments in your [MONEI Dashboard → Payments](https://dashboard.monei.com/payments) (in test mode)

## Configuration

1. Log in to your Magento Admin Panel
2. Navigate to **Stores > Configuration > Sales > Payment Methods**
3. Find and expand the **MONEI Payments** section
4. Enter your MONEI API key and configure your preferred settings
5. Save the configuration

For detailed configuration instructions, please refer to our [official documentation](https://docs.monei.com/docs/e-commerce/adobe-commerce/).

## MONEI PHP SDK

This module integrates with the official [MONEI PHP SDK](https://github.com/MONEI/monei-php-sdk) version 2.4.3 or higher. The SDK provides a reliable and well-maintained interface to the MONEI API, handling:

- Payment creation, retrieval, and management
- Webhook signature verification
- Error handling and exceptions
- Proper API authentication

The SDK is automatically installed when you install the module via Composer. If you installed the module manually, make sure to install the SDK separately as shown in the installation instructions.

For detailed information on how to use the MONEI PHP SDK in your custom code, see our [SDK Integration Guide](docs/MONEI_PHP_SDK.md).

## Demo

Experience the module in action through our [live demo store](https://magento2-demo.monei.com/).

## Development

### Docker Setup

This module includes a Docker setup for local development with PHP 8.3:

```bash
# Start the Docker containers
docker-compose up -d

# Execute commands in the PHP container
docker-compose exec php bash
```

For detailed Docker setup instructions, see the [Docker README](docker/README.md).

### Code Quality Tools

This module includes several code quality tools to maintain high standards:

#### Prerequisites

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

#### Available Commands

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

The code quality tools check for:

- PHP syntax errors
- PSR-12 and Magento 2 coding standards
- Code style and formatting
- Potential security vulnerabilities
- Critical issues and bugs
- PHPDoc comments compliance

### Environment Variables

Customize the linting and fixing scripts with these environment variables:

```bash
# Skip PHP-CS-Fixer checks when running the lint script
SKIP_CS_FIXER=1 composer lint

# Run fix script with only CS Fixer (skips PHP_CodeSniffer)
FIX_CS_ONLY=1 composer fix
```

## Troubleshooting

If you encounter issues with the module:

1. Ensure your Magento and PHP versions meet the requirements
2. Check that the module is properly installed and enabled
3. Verify your MONEI API credentials are correct
4. Clear your Magento cache and run setup:upgrade again
5. Check the Magento logs for any error messages

For more detailed troubleshooting, visit our [developer documentation](docs/DEVELOPMENT.md).

## Contributing

We welcome contributions to improve this module! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes and commit (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please ensure your code follows our coding standards and passes all tests.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For questions or issues:

- [Official Documentation](https://docs.monei.com/docs/e-commerce/adobe-commerce/)
- [GitHub Issues](https://github.com/MONEI/MONEI-AdobeCommerce-Magento2/issues)
- [Contact MONEI Support](https://monei.com/contact)

## Code Validation

The MONEI Payment Module for Adobe Commerce (Magento 2) adheres to Magento Marketplace standards. We provide simple commands to validate and fix code:

### Validation

We use multiple tools to ensure code quality:

- **PHP_CodeSniffer**: Checks for PSR-12 and Magento 2 coding standards
- **PHP-CS-Fixer**: Fixes code style issues automatically
- **pretty-php**: Formats code for readability and consistency (see [pretty-php documentation](docs/PRETTY-PHP.md))

### Fixing Code Issues

```bash
# Fix coding standards and style issues automatically
composer fix
```

For detailed information about Magento Marketplace validation, please see [docs/MARKETPLACE_VALIDATION.md](docs/MARKETPLACE_VALIDATION.md).
