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

