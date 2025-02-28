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
# Run all code quality checks
composer lint

# Fix coding standards automatically
composer fix

# Run PHPStan static analysis
composer phpstan

# Run PHP-CS-Fixer check
composer cs-check

# Run PHP-CS-Fixer auto-fix
composer cs-fix

# Run PHP_CodeSniffer check
composer phpcs

# Run PHP_CodeSniffer auto-fix
composer phpcbf
```

The code quality tools check for:
- PHP syntax errors
- PSR-12 and Magento 2 coding standards
- Proper code style and formatting
- Potential bugs and issues

