# Using the MONEI PHP SDK in Magento 2

This document provides guidance on how to use the MONEI PHP SDK within the MONEI Payments module for Adobe Commerce (Magento 2).

## Overview

The MONEI PHP SDK provides a convenient and reliable way to interact with the MONEI API. It handles authentication, request formatting, error handling, and response parsing, making it easier to integrate MONEI payments into your Magento store.

## Installation

The SDK is automatically installed when you install the MONEI Payments module via Composer. If you installed the module manually, you need to install the SDK separately:

```bash
composer require monei/monei-php-sdk:^2.4.3
```

## SDK Integration in the Module

The MONEI PHP SDK is integrated into the module through the `MoneiApiClient` class, which serves as a wrapper around the SDK. This class provides methods for common payment operations and handles the configuration of the SDK based on your store settings.

### Key Components

1. **MoneiApiClient**: Located at `Monei\MoneiPayment\Service\Api\MoneiApiClient.php`, this class initializes and uses the MONEI SDK.
2. **ApiPaymentDataProvider**: Uses the MoneiApiClient to fetch payment data from the MONEI API.
3. **WebhookPaymentDataProvider**: Processes webhook data and verifies signatures using the SDK.

## Using the SDK in Custom Code

If you need to extend the module or create custom integrations, you can use the SDK directly or through the `MoneiApiClient`.

### Using MoneiApiClient

```php
<?php
// Inject the MoneiApiClient in your constructor
public function __construct(
    \Monei\MoneiPayment\Service\Api\MoneiApiClient $apiClient,
    // other dependencies
) {
    $this->apiClient = $apiClient;
    // other initialization
}

// Use the client to interact with the MONEI API
public function someMethod()
{
    try {
        // Get payment details
        $payment = $this->apiClient->getPayment('payment_id');
        
        // Create a new payment
        $paymentData = [
            'amount' => 1000, // Amount in cents
            'currency' => 'EUR',
            'orderId' => 'order_123',
            // other payment parameters
        ];
        $newPayment = $this->apiClient->createPayment($paymentData);
        
        // Capture a payment
        $capturedPayment = $this->apiClient->capturePayment('payment_id', 1000);
        
        // Refund a payment
        $refundedPayment = $this->apiClient->refundPayment('payment_id', 1000);
        
        // Cancel a payment
        $cancelledPayment = $this->apiClient->cancelPayment('payment_id');
        
        // Get available payment methods
        $methods = $this->apiClient->getPaymentMethods();
        
        // Verify webhook signature
        $isValid = $this->apiClient->verifyWebhookSignature($payload, $signatureHeader);
    } catch (\Magento\Framework\Exception\LocalizedException $e) {
        // Handle localized exceptions
    } catch (\Exception $e) {
        // Handle other exceptions
    }
}
```

### Using the SDK Directly

If you need more direct access to the SDK, you can use the `getMoneiSdk()` method from the `MoneiApiClient` class:

```php
<?php
// Inject the MoneiApiClient in your constructor
public function __construct(
    \Monei\MoneiPayment\Service\Api\MoneiApiClient $apiClient,
    // other dependencies
) {
    $this->apiClient = $apiClient;
    // other initialization
}

// Use the SDK directly for advanced operations
public function advancedMethod()
{
    try {
        // Get the SDK instance
        $moneiSdk = $this->apiClient->getMoneiSdk();
        
        // Use the SDK directly for operations not covered by the client
        $result = $moneiSdk->someAdvancedOperation();
        
        return $result;
    } catch (\Monei\Exception\ApiErrorException $e) {
        // Handle API errors
    } catch (\Exception $e) {
        // Handle other exceptions
    }
}
```

## Error Handling

The SDK throws `\Monei\Exception\ApiErrorException` for API-related errors. The `MoneiApiClient` wraps these exceptions in Magento's `LocalizedException` for better integration with Magento's error handling system.

When using the SDK, it's recommended to catch both types of exceptions:

```php
try {
    // SDK operations
} catch (\Monei\Exception\ApiErrorException $e) {
    // Handle API-specific errors
} catch (\Magento\Framework\Exception\LocalizedException $e) {
    // Handle localized exceptions
} catch (\Exception $e) {
    // Handle other exceptions
}
```

## Webhook Handling

The SDK provides methods for verifying webhook signatures. This is important for ensuring that webhook requests are authentic and come from MONEI.

```php
// In your webhook controller
public function execute()
{
    $payload = $this->getRequest()->getContent();
    $signatureHeader = $this->getRequest()->getHeader('MONEI-Signature');
    
    try {
        $isValid = $this->apiClient->verifyWebhookSignature($payload, $signatureHeader);
        
        if ($isValid) {
            // Process the webhook
            $data = json_decode($payload, true);
            // Handle the webhook data
        } else {
            // Invalid signature
            return $this->resultFactory->create(ResultFactory::TYPE_RAW)
                ->setHttpResponseCode(400)
                ->setContents('Invalid signature');
        }
    } catch (\Exception $e) {
        // Handle verification errors
        return $this->resultFactory->create(ResultFactory::TYPE_RAW)
            ->setHttpResponseCode(500)
            ->setContents('Error: ' . $e->getMessage());
    }
}
```

## Testing

When testing your integration with the MONEI SDK, use the test mode API key from your MONEI Dashboard. This allows you to simulate payments without processing real transactions.

The module automatically configures the SDK to use the appropriate API key and environment based on your store configuration.

## Additional Resources

- [MONEI PHP SDK GitHub Repository](https://github.com/MONEI/monei-php-sdk)
- [MONEI API Documentation](https://docs.monei.com/api)
- [MONEI Magento Module Documentation](https://docs.monei.com/docs/e-commerce/adobe-commerce/)

## Troubleshooting

If you encounter issues with the SDK:

1. Ensure you have the correct version of the SDK installed (^2.4.3)
2. Check that your API keys are correctly configured in the Magento admin
3. Verify that your server meets the requirements for the SDK (PHP ^8.1)
4. Check the Magento logs for any error messages related to the MONEI API
5. For webhook issues, ensure your webhook endpoint is publicly accessible and properly configured in your MONEI Dashboard 
