# MONEI Payment Processing Implementation Details

This document provides detailed implementation instructions for the core components of the MONEI Payment Processing refactoring.

## 1. Data Transfer Objects

### PaymentDTO

Create this class in `Model/Data/PaymentDTO.php`:

- **Purpose**: Standardize payment data from different sources (webhook or API)
- **Properties**:
  - `id`: MONEI payment ID
  - `status`: Payment status (SUCCEEDED, AUTHORIZED, FAILED, etc.)
  - `amount`: Payment amount
  - `currency`: Payment currency
  - `orderId`: Merchant reference (order increment ID)
  - `createdAt`: Payment creation timestamp
  - `updatedAt`: Payment update timestamp
  - `metadata`: Additional payment metadata
- **Implementation Notes**:
  - Make all properties private with public getters
  - Add validation for required fields
  - Implement a factory method to create from array data
  - Add methods to check status (isSucceeded, isAuthorized, etc.)

### PaymentDataProviderInterface

Create this interface in `Api/PaymentDataProviderInterface.php`:

- **Purpose**: Abstract the source of payment data
- **Methods**:
  - `getPaymentData(string $paymentId): PaymentDTO`
  - `validatePaymentData(array $data): bool`
- **Implementation Notes**:
  - Throw exceptions for missing or invalid data
  - Include detailed error messages for validation failures

### WebhookPaymentDataProvider

Create this class in `Model/PaymentDataProvider/WebhookPaymentDataProvider.php`:

- **Purpose**: Extract payment data from webhook request
- **Implementation Notes**:
  - Parse JSON request body
  - Validate required fields
  - Convert to PaymentDTO
  - Handle malformed data gracefully

### ApiPaymentDataProvider

Create this class in `Model/PaymentDataProvider/ApiPaymentDataProvider.php`:

- **Purpose**: Fetch payment data from MONEI API
- **Implementation Notes**:
  - Use existing API client
  - Add caching to prevent duplicate API calls
  - Handle API errors gracefully
  - Convert API response to PaymentDTO

## 2. Locking Mechanism

### LockManagerInterface

Create this interface in `Api/LockManagerInterface.php`:

- **Purpose**: Define a unified contract for locking operations that combines the functionality of both existing lock implementations
- **Methods**:
  - Order locking: `lockOrder()`, `unlockOrder()`, `isOrderLocked()`
  - Payment locking: `lockPayment()`, `unlockPayment()`, `isPaymentLocked()`
  - Wait functionality: `waitForPaymentUnlock()`
  - Transaction-style: `executeWithPaymentLock()`, `executeWithOrderLock()`
- **Implementation Notes**:
  - Include timeout parameters for lock acquisition
  - Return boolean success indicators for lock operations
  - Document thread safety requirements

### LockManager

Create this class in `Model/LockManager.php`:

- **Purpose**: Implement a unified locking mechanism that consolidates the existing `ProcessingLock` and `OrderLockManager` classes
- **Implementation Notes**:
  - Use Magento's built-in `LockManagerInterface` for the underlying lock mechanism
  - Standardize lock naming conventions between order locks and payment locks
  - Implement consistent timeout handling for all lock operations
  - Add comprehensive logging for all lock operations
  - Ensure locks are always released in finally blocks
  - Use unique lock names based on order ID and payment ID
  - Implement polling for wait functionality
  - Maintain backward compatibility with existing lock usage patterns
  - Reuse the existing lock prefixes and timeout constants from both classes

## 3. Payment Processing Service

### PaymentProcessorInterface

Create this interface in `Api/PaymentProcessorInterface.php`:

- **Purpose**: Define contract for payment processing
- **Methods**:
  - `process(string $orderId, string $paymentId, array $paymentData): PaymentProcessingResultInterface`
  - `isProcessing(string $orderId, string $paymentId): bool`
  - `waitForProcessing(string $orderId, string $paymentId, int $timeout): bool`
  - `getPaymentStatus(string $paymentId): array`
- **Implementation Notes**:
  - Document thread safety requirements
  - Define clear error handling expectations

### PaymentProcessingResultInterface

Create this interface in `Api/PaymentProcessingResultInterface.php`:

- **Purpose**: Standardize payment processing result
- **Methods**:
  - Status getters: `getStatus()`, `getOrderId()`, `getPaymentId()`
  - Result indicators: `isSuccessful()`
  - Error information: `getErrorMessage()`, `getStatusCode()`, `getFullErrorResponse()`
- **Implementation Notes**:
  - Include methods for user-friendly error messages
  - Document serialization requirements

### ProcessPaymentService

Create this class in `Service/ProcessPaymentService.php`:

- **Purpose**: Unified payment processing for both controllers
- **Implementation Notes**:
  - Use LockManager for concurrency control
  - Handle different payment statuses:
    - SUCCEEDED: Create and capture invoice
    - AUTHORIZED: Create pending invoice
    - FAILED/CANCELED: Update order status
  - Implement proper error handling
  - Add comprehensive logging
  - Use PaymentDataProvider for data access
  - Ensure idempotent processing

## 4. Invoice Service

### InvoiceService

Create this class in `Service/InvoiceService.php`:

- **Purpose**: Handle all invoice-related operations
- **Methods**:
  - `processInvoice()`: Create and capture invoice for succeeded payments
  - `createPendingInvoice()`: Create pending invoice for authorized payments
  - `processPartialInvoice()`: Handle partial captures with constraints
- **Implementation Notes**:
  - Use LockManager for concurrency control
  - Handle "already captured" errors gracefully
  - Implement transaction handling for atomicity
  - Add comprehensive logging
  - Ensure proper transaction ID management
  - Prevent multiple partial captures

## 5. Webhook Helper

### WebhooksHelperInterface

Create this interface in `Api/WebhooksHelperInterface.php`:

- **Purpose**: Define contract for webhook handling
- **Methods**:
  - `processWebhook(RequestInterface $request): void`
  - `verifyWebhookSignature(string $payload, array $headers): bool`
  - `dispatchEvent(array $eventData): void`
- **Implementation Notes**:
  - Document security requirements
  - Define error handling expectations

### WebhooksHelper

Create this class in `Service/WebhooksHelper.php`:

- **Purpose**: Handle webhook processing and verification
- **Implementation Notes**:
  - Implement signature verification using MONEI's algorithm
  - Add comprehensive logging for all webhook events
  - Use ProcessPaymentService for payment processing
  - Handle different event types
  - Implement proper error handling
  - Return appropriate HTTP responses

## 6. Controller Updates

### Callback Controller

Update `Controller/Payment/Callback.php`:

- **Changes**:
  - Use WebhooksHelper for signature verification
  - Use ProcessPaymentService for payment processing
  - Simplify error handling
  - Add comprehensive logging
  - Implement proper response handling
- **Implementation Notes**:
  - Keep backward compatibility
  - Ensure proper dependency injection
  - Add detailed comments explaining the flow

### Complete Controller

Update `Controller/Payment/Complete.php`:

- **Changes**:
  - Use ProcessPaymentService for payment processing
  - Implement waiting for locks if payment is being processed
  - Fall back to API if payment data is missing
  - Improve customer redirects based on payment status
- **Implementation Notes**:
  - Keep backward compatibility
  - Ensure proper dependency injection
  - Add detailed comments explaining the flow
  - Focus on customer experience

## 7. Admin Functionality

### CaptureService

Create this class in `Service/CaptureService.php`:

- **Purpose**: Handle admin-initiated captures for authorized payments
- **Methods**:
  - `capturePayment(string $paymentId, float $amount): bool`
  - `canCapture(string $paymentId): bool`
- **Implementation Notes**:
  - Call MONEI API to capture payment
  - Update invoice status after successful capture
  - Add comprehensive error handling
  - Implement proper logging

### Admin UI Updates

- **Grid Updates**:
  - Add column for payment status
  - Highlight authorized payments
  - Add capture action for authorized payments
- **Implementation Notes**:
  - Use existing Magento grid infrastructure
  - Add proper permissions
  - Implement AJAX for capture action

## 8. Testing Strategy

### Unit Tests

Create tests for all new services:

- **ProcessPaymentService Tests**:
  - Test different payment statuses
  - Test locking scenarios
  - Test error handling

- **InvoiceService Tests**:
  - Test invoice creation
  - Test pending invoice creation
  - Test duplicate prevention
  - Test error handling

- **LockManager Tests**:
  - Test lock acquisition and release
  - Test waiting functionality
  - Test timeout handling

- **WebhooksHelper Tests**:
  - Test signature verification
  - Test event dispatching
  - Test error handling

### Integration Tests

Create end-to-end tests:

- **Payment Flow Tests**:
  - Test full checkout-to-capture flow
  - Test authorized payment flow
  - Test failed payment flow

- **Concurrency Tests**:
  - Test Callback arriving before Complete
  - Test Complete arriving before Callback
  - Test simultaneous processing

- **Error Handling Tests**:
  - Test API errors
  - Test already captured payments
  - Test timeout scenarios 
