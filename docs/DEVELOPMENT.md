# Development Guide

This document provides comprehensive information for developers working with the MONEI Payment module for Adobe Commerce (Magento 2).

## Local Development Setup

For local development, we recommend using [markshust/docker-magento](https://github.com/markshust/docker-magento) which provides a robust Docker setup for Magento 2 development.

1. First, set up docker-magento:

```bash
# Download the setup script
curl -s https://raw.githubusercontent.com/markshust/docker-magento/master/lib/onelinesetup | bash -s -- magento.test 2.4.6-p3 community
```

2. Clone the MONEI Payment module into the correct directory:

```bash
# Navigate to the module directory
cd src/app/code
mkdir -p Monei/MoneiPayment
git clone https://github.com/MONEI/MONEI-AdobeCommerce-Magento2.git MoneiPayment
cd MoneiPayment
```

3. Install module dependencies and enable it:

```bash
# Install MONEI SDK
bin/composer require monei/monei-php-sdk:^2.4.3

# Enable module and run setup
bin/magento module:enable Monei_MoneiPayment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
bin/magento cache:clean
```

4. The module should now be installed in your local Magento instance at https://magento.test

## Available Commands

For development, you can use the following commands:

```bash
# Code quality
bin/composer cs:lint                   # Run PHPCS code sniffer
bin/composer cs:fix                    # Fix coding standards with PHPCBF
bin/composer analyze                   # Run PHPStan static analysis
bin/composer format:check             # Check formatting with pretty-php
bin/composer format:fix               # Fix formatting with pretty-php
bin/composer fix:all                  # Run all fixers (cs:fix and format:fix)
bin/composer check:all               # Run all code quality checks (cs:lint, analyze, format:check)

# Frontend
yarn format                        # Format frontend code with prettier

# Magento commands (using helper script)
bin/magento setup:di:compile      # Compile dependency injection
bin/magento setup:upgrade         # Run module upgrades
bin/magento cache:clean           # Clean caches
bin/magento cache:flush           # Flush all caches
bin/magento module:enable Monei_MoneiPayment  # Enable module

# Module specific commands
bin/magento monei:verify-apple-pay-domain <domain>  # Register domain with Apple Pay
bin/magento monei:update-status-labels              # Update order status labels
```

## Docker Setup

This module includes a standalone Docker setup for isolated development with PHP 8.3:

```bash
# Start the Docker containers
docker-compose up -d

# Execute commands in the PHP container
docker-compose exec php bash
```

For detailed Docker setup instructions, see the [Docker README](DOCKER.md).

## Troubleshooting

If you encounter issues with the module:

1. Ensure your Magento and PHP versions meet the requirements
2. Check that the module is properly installed and enabled
3. Verify your MONEI API credentials are correct
4. Clear your Magento cache and run setup:upgrade again
5. Check the Magento logs for any error messages

### Common Issues

- **Payment form not loading**: Verify your API key is correctly set in the admin configuration
- **Webhook errors**: Check your server's firewall settings and ensure Cloudflare Tunnel is properly configured
- **Compilation errors**: Run `bin/magento setup:di:compile` and check the error log for detailed information
