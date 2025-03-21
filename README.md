# MONEI Payments for Adobe Commerce (Magento 2)

[![Magento Marketplace](https://img.shields.io/badge/Magento-Marketplace-orange.svg)](https://marketplace.magento.com/monei-module-monei-payment.html)
[![Latest Version](https://img.shields.io/packagist/v/monei/module-monei-payment.svg)](https://packagist.org/packages/monei/module-monei-payment)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg)](https://php.net/)

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
    - [Bitnami Installation](#bitnami-installation)
    - [Before You Begin](#before-you-begin)

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
- Automatic Apple Pay domain verification through configuration settings

## Requirements

- PHP: ^8.1.0
- Magento: >=2.4.4 (Support for Magento 2.4.0-2.4.3 release line ended on November 28, 2022) ([Adobe Commerce Release Versions](https://experienceleague.adobe.com/en/docs/commerce-operations/release/versions))
- MONEI Account ([Sign up here](https://monei.com/signup))
- MONEI PHP SDK: ^2.4.3 (automatically installed with Composer)

## Installation

### Via Composer (Recommended)

Go to your Adobe Commerce (Magento 2) root directory and run the following commands:

1. Add the package to your Magento installation:

```bash
composer require monei/module-monei-payment
```

2. Enable the module:

```bash
bin/magento module:enable Monei_MoneiPayment
```

3. Run the following commands to upgrade and compile the module:

```bash
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
bin/magento cache:clean
```

### Manual Installation

Go to your Adobe Commerce (Magento 2) root directory.

1. Download the latest release from the [GitHub repository](https://github.com/MONEI/MONEI-AdobeCommerce-Magento2/releases)
2. Extract the contents to your `app/code/Monei/MoneiPayment` directory
3. Install the MONEI PHP SDK:

```bash
composer require monei/monei-php-sdk:^2.6
```

4. Go to your Adobe Commerce (Magento 2) root directory and run:

```bash
bin/magento module:enable Monei_MoneiPayment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
bin/magento cache:clean
```

Alternatively, you can use this one-line command to download and extract the latest release:

```bash
curl -L https://github.com/MONEI/MONEI-AdobeCommerce-Magento2/releases/latest/download/monei-module-monei-payment.zip -o monei.zip && \
mkdir -p app/code/Monei/MoneiPayment && \
unzip monei.zip -d app/code/Monei/MoneiPayment && \
rm monei.zip
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
