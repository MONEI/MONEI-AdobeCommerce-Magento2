# MONEI SDK Integration and Logging Standards

This document defines the standardized approach to SDK usage, request models, logging, and error handling across the module.

## Core Components

1. `MoneiApiClient` - Factory for SDK instances with caching and store-specific configurations
2. `ApiExceptionHandler` - Centralized SDK exception processing
3. `AbstractApiService` - Base service with standardized API call methods
4. `Logger` - Enhanced logging with standardized formats and data sanitization

## SDK Integration Pattern

### Key Guidelines

1. Always use `executeMoneiSdkCall()` for all SDK operations
2. Use SDK request models instead of raw data arrays
3. Leverage type-safe model properties and validation
4. Follow the dependency injection pattern via constructor
5. Keep service methods focused on business logic, not SDK interaction details

### Implementation Example

```php
public function execute(string $payment_id): array
{
    return $this->executeMoneiSdkCall(
        'getPayment',
        function (MoneiClient $moneiSdk) use ($payment_id) {
            return $moneiSdk->payments->get($payment_id);
        },
        ['payment_id' => $payment_id]
    );
}
```

### Request Models

Use SDK request models for all API requests:

```php
// Create with constructor
$request = new CreatePaymentRequest([
    'amount' => 1000,
    'currency' => 'EUR',
    'order_id' => '123456'
]);

// Set properties with fluent setters
$request->setCustomer(new PaymentCustomer([
    'email' => $data['customer']['email']
]));
```

## Logging Standards

The enhanced `Logger` class provides standardized methods for consistent logging:

1. **API Request**: `logApiRequest($operation, $data)`
   ```php
   $this->logger->logApiRequest('getPayment', ['payment_id' => $id]);
   ```

2. **API Response**: `logApiResponse($operation, $result)`
   ```php
   $this->logger->logApiResponse('getPayment', $result);
   ```

3. **API Error**: `logApiError($operation, $message, $context)`
   ```php
   $this->logger->logApiError('getPayment', $errorMessage, ['status' => 400]);
   ```

4. **Payment Events**: `logPaymentEvent($type, $orderId, $paymentId, $data)`
   ```php
   $this->logger->logPaymentEvent('create', $orderId, $paymentId, ['amount' => $amount]);
   ```

### Features

- **Consistent Format**: Standardized log structure across all components
- **JSON Formatting**: All arrays and objects are properly JSON-formatted
- **Data Sanitization**: Sensitive fields are automatically masked
- **Contextual Logging**: Operation names and relevant IDs always included

## Error Handling

Errors are handled consistently through the standardized pattern:

1. `ApiExceptionHandler` processes all SDK exceptions
2. HTTP status codes map to appropriate user-friendly messages
3. Detailed error context is logged but sensitive data is masked
4. LocalizedException is used for translatable user messages

## DI Configuration

Service classes should be configured with standard dependencies:

```xml
<type name="Monei\MoneiPayment\Service\CreatePayment">
    <arguments>
        <argument name="logger" xsi:type="object">Monei\MoneiPayment\Service\Logger</argument>
        <argument name="exceptionHandler" xsi:type="object">Monei\MoneiPayment\Service\Api\ApiExceptionHandler</argument>
        <argument name="apiClient" xsi:type="object">Monei\MoneiPayment\Service\Api\MoneiApiClient</argument>
        <!-- Service-specific arguments -->
    </arguments>
</type>
```

## Code Reduction Guidelines

1. Use standardized SDK request models instead of building arrays
2. Leverage the enhanced logging methods to reduce boilerplate
3. All error handling is centralized in ApiExceptionHandler
4. Use constructor property promotion where applicable
5. Leverage method argument typing and return type declarations

## Testing

Mock the core components to effectively test services:

```php
$apiClientMock = $this->createMock(MoneiApiClient::class);
$exceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);
$loggerMock = $this->createMock(Logger::class);

// Configure mocks...

$service = new PaymentService($loggerMock, $exceptionHandlerMock, $apiClientMock);
$result = $service->execute($testData);
```