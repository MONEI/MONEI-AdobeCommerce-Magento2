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
      - [Option 2: Via Main Branch](#option-2-via-main-branch)
    - [Bitnami Installation](#bitnami-installation)
    - [Before You Begin](#before-you-begin)
  - [Configuration](#configuration)
  - [Configuring Cloudflare Tunnel for Payment Callbacks](#configuring-cloudflare-tunnel-for-payment-callbacks)
  - [Available Commands](#available-commands)
  - [MONEI PHP SDK](#monei-php-sdk)
  - [Demo](#demo)
  - [Development](#development)
  - [Troubleshooting](#troubleshooting)
  - [Contributing](#contributing)
  - [License](#license)
  - [Support](#support)
  - [Code Validation](#code-validation)

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
- Automatic Apple Pay domain verification when configuration is saved

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
bin/magento module:enable Monei_MoneiPayment
```

3. Run setup upgrade:

```bash
bin/magento setup:upgrade
```

4. Compile dependency injection:

```bash
bin/magento setup:di:compile
```

5. Flush cache:

```bash
bin/magento cache:flush
```

### Manual Installation

#### Option 1: Via GitHub Releases

1. Download the latest release from the [GitHub repository](https://github.com/MONEI/MONEI-AdobeCommerce-Magento2/releases)
2. Extract the contents to your `app/code/Monei/MoneiPayment` directory
3. Follow steps 2-5 from the Composer installation instructions
4. Install the MONEI PHP SDK:

```bash
composer require monei/monei-php-sdk:^2.4.3
```

5. Go to your Adobe Commerce (Magento 2) root directory and run:

```bash
bin/magento module:enable Monei_MoneiPayment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
bin/magento cache:clean
```

Alternatively, you can use this one-line command to download and extract the latest release:

```bash
curl -L https://github.com/MONEI/MONEI-AdobeCommerce-Magento2/archive/refs/heads/main.zip -o monei.zip && \
mkdir -p app/code/Monei/MoneiPayment && \
unzip -j monei.zip "*/app/code/Monei/MoneiPayment/*" -d app/code/Monei/MoneiPayment && \
rm monei.zip
```

#### Option 2: Via Main Branch

1. Download the [latest version](https://github.com/MONEI/MONEI-AdobeCommerce-Magento2/archive/refs/heads/main.zip) from the main branch
2. Create a directory called `app/code/Monei/MoneiPayment` inside your Magento 2 project
3. Unzip the downloaded archive in this directory
4. Install the MONEI PHP SDK:

```bash
composer require monei/monei-php-sdk:^2.4.3
```

5. Go to your Adobe Commerce (Magento 2) root directory and run:

```bash
bin/magento module:enable Monei_MoneiPayment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
bin/magento cache:clean
```

### Bitnami Installation

If you're running Magento on a Bitnami server, you'll need to use the `magento-cli` tool instead of the regular `magento` command. Follow these steps:

1. First, install the module using one of the methods above (Composer or Manual)
2. Navigate to your Magento installation directory:

```bash
cd ~/stack/magento
```

3. Run the required commands using `magento-cli`:

```bash
sudo bin/magento-cli module:enable Monei_MoneiPayment
sudo bin/magento-cli setup:upgrade
sudo bin/magento-cli setup:di:compile
sudo bin/magento-cli setup:static-content:deploy
sudo bin/magento-cli cache:clean
```

Note: The `magento-cli` tool is provided by Bitnami to handle permissions and ownership issues correctly in their server setup.

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

## Configuring Cloudflare Tunnel for Payment Callbacks

MONEI sends payment callbacks to your store to update order statuses and process payments. During local development or when your server isn't publicly accessible, you can use Cloudflare Tunnel to securely expose your local development environment.

For detailed setup instructions, see our [Cloudflare Tunnel Configuration Guide](docs/CLOUDFLARE_TUNNEL.md).

## Available Commands

This module provides several Magento CLI commands to help you manage MONEI payments and configurations. All commands are located in the `@Command` folder:

```bash
# Register a domain with Apple Pay through MONEI
bin/magento monei:verify-apple-pay-domain <domain>

# Update order status labels in the database
bin/magento monei:update-status-labels
```

These commands are useful for testing, troubleshooting, and automating various MONEI payment operations from the command line.

## MONEI PHP SDK

This module integrates with the official [MONEI PHP SDK](https://github.com/MONEI/monei-php-sdk) version 2.4.3 or higher. The SDK provides a reliable and well-maintained interface to the MONEI API, handling:

- Payment creation, retrieval, and management
- Webhook signature verification
- Error handling and exceptions
- Proper API authentication

The SDK is automatically installed when you install the module via Composer. If you installed the module manually, make sure to install the SDK separately as shown in the installation instructions.

For detailed information on how to use the MONEI PHP SDK in your custom code, see the [official MONEI PHP SDK documentation](https://github.com/MONEI/monei-php-sdk).

## Demo

Experience the module in action through our [live demo store](https://magento2-demo.monei.com/).

## Development

For comprehensive development information, please refer to our [Development Guide](docs/DEVELOPMENT.md). This includes instructions for setup, commands, Docker configuration, and troubleshooting.

If you're interested in contributing to this project, please see our [Contribution Guidelines](docs/CONTRIBUTING.md).

For code quality standards and tools, check our [Code Quality Guide](docs/CODE_QUALITY.md) and [pretty-php documentation](docs/PRETTY-PHP.md).

## Troubleshooting

If you encounter issues with the module:

1. Ensure your Magento and PHP versions meet the requirements
2. Check that the module is properly installed and enabled
3. Verify your MONEI API credentials are correct
4. Clear your Magento cache and run setup:upgrade again
5. Check the Magento logs for any error messages

For more detailed troubleshooting, visit our [Development Guide](docs/DEVELOPMENT.md#troubleshooting).

## Contributing

We welcome contributions to improve this module! Please see our [Contribution Guidelines](docs/CONTRIBUTING.md) for details.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For questions or issues:

- [Official Documentation](https://docs.monei.com/docs/e-commerce/adobe-commerce/)
- [GitHub Issues](https://github.com/MONEI/MONEI-AdobeCommerce-Magento2/issues)
- [Contact MONEI Support](https://monei.com/contact)

## Code Validation

The MONEI Payment Module for Adobe Commerce (Magento 2) adheres to Magento Marketplace standards. For detailed information about our code validation tools and processes, see our [Code Quality Guide](docs/CODE_QUALITY.md).

```bash
# Fix coding standards and style issues automatically
composer fix:all
```
