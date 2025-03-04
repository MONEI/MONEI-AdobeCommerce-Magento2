# MONEI Payment Processing Refactoring Plan

## Current Issues

1. **Race Condition**: Both Callback and Complete controllers can process the same payment simultaneously, potentially leading to duplicate invoice creation and order processing.
2. **Inconsistent Logic**: The Callback and Complete controllers have different implementation logic despite serving similar purposes.
3. **Duplicate Invoice Generation**: Both controllers can attempt to generate invoices for the same order.
4. **No Handling for Already Captured Payments**: When a payment is already captured, `$invoice->register()->capture()` will cause an error.
5. **No Waiting Mechanism**: If one controller locks the process, the other should wait or properly handle the case.
6. **Limited Partial Capture Support**: MONEI can handle partial captures, but only allows a single partial capture per payment, not multiple partial captures.

## MONEI Payment Flow

According to [MONEI documentation](https://github.com/MONEI/docs/blob/master/docs/integrations/use-prebuilt-payment-page.mdx), the payment flow works as follows:

1. **Redirect to MONEI Payment Page**:
   - Customer is redirected to the MONEI payment page with order details
   - MONEI processes the payment with the selected payment method

2. **Double Notification**:
   - **Callback (Webhook)**: An asynchronous server-to-server notification that is sent to the `callbackUrl` regardless of the payment result
   - **Redirect (Complete)**: The customer is redirected back to the `completeUrl` with payment status in query parameters

3. **Payment Status Handling**:
   - `succeeded`: Payment authorized and captured
   - `authorized`: Payment authorized but not captured
   - `failed`, `canceled`, `expired`: Payment unsuccessful
   - `refunded`, `partially_refunded`: Post-payment statuses

4. **Important Considerations**:
   - The Callback notification is more reliable as it's a server-to-server communication
   - The Complete redirect depends on the customer's browser behavior
   - Both notifications can arrive in any order
   - The Callback includes a signature header for validation
   - The system must handle duplicate notifications gracefully

## Error Handling Approach

When encountering MONEI API errors, we'll implement a simplified approach:

1. **Direct Error Display**: Instead of handling specific error codes individually, we'll display the error message directly from the MONEI API response to the merchant.

2. **Error Logging**: All errors will be logged for troubleshooting purposes, including the complete API response.

3. **Exception Handling**: For operational errors (like "already captured" payments), we'll still implement specific exception handling to ensure smooth processing.

4. **User-Friendly Messages**: While displaying direct error messages, we'll ensure they're presented in a user-friendly context within the Magento admin interface.

This approach simplifies maintenance while providing merchants with direct visibility into payment processing issues.

## Concurrency Management Approach

To effectively manage concurrency between Callback and Complete controllers, we'll implement a robust locking strategy:

### 1. Locking Strategy

1. **Lock Granularity**: Create locks based on both `orderId` and `paymentId` to ensure specific payment processing is protected
2. **Lock Acquisition Timeout**: Set appropriate timeouts to prevent indefinite waiting
3. **Lock States**:
   - **Not Locked**: Proceed with normal processing
   - **Locked by Current Process**: Continue processing (reentrant lock)
   - **Locked by Another Process**: Wait or proceed based on controller type
   
### 2. Controller-Specific Behavior

1. **Callback Controller**:
   - Try to acquire lock with minimal timeout (2 seconds)
   - If lock acquisition fails, log and exit gracefully (webhook can be retried)
   - Priority: Reliable payment status recording

2. **Complete Controller**:
   - Try to acquire lock with reasonable timeout (5 seconds)
   - If locked, implement a short polling strategy to wait for completion (max 15 seconds)
   - After timeout, check payment status and proceed based on that
   - Priority: Customer experience (avoid showing error pages)

### 3. Error Handling and Recovery

1. **Lock Release Guarantee**:
   - Use `try/finally` blocks to ensure locks are always released
   - Implement a lock expiration mechanism as a fallback (30 minute maximum lock time)

2. **Transaction Handling**:
   - Use database transactions for critical operations
   - Implement rollback capability for failed operations

3. **Idempotency**:
   - Ensure all operations are idempotent (can be executed multiple times without side effects)
   - Check current state before applying changes

## Proposed Solution (Updated with Stripe Patterns)

### 1. Create a Unified Payment Processing Service

Create a new service class `ProcessPaymentService` that will:
- Handle the entire payment processing logic
- Be used by both Callback and Complete controllers
- Implement proper locking mechanism
- Handle all payment statuses consistently

This service will process payments differently based on their status:
- **AUTHORIZED**: Create a pending invoice that can be captured later by admin
- **SUCCEEDED**: Create and capture an invoice immediately
- **FAILED/CANCELED/EXPIRED**: Update order status and send appropriate notifications

### 2. Unified Locking Mechanism

Instead of having separate `OrderLockManager` and `ProcessingLock` classes, we'll create a single robust locking class:

- **LockManager**: This class will:
  - Support both simple order-based locking and more granular payment-specific locking
  - Allow waiting for locks to be released
  - Provide rich information about lock status
  - Implement proper logging for all locking operations
  - Offer both basic lock/unlock operations and higher-level abstractions
  - Support transaction-style operations with automatic lock release

### 3. Adopt Stripe's Helper-Based Architecture

Based on Stripe's implementation, we'll organize our code with these improvements:

1. **Specialized Helpers**: Create purpose-specific helper classes similar to Stripe's approach:
   - `WebhookHelper`: For handling webhook processing, validation, and dispatching
   - `SignatureHelper`: For validating MONEI webhook signatures
   - `PaymentStatusHelper`: For consistent payment status handling

2. **Event-Based Architecture**: Implement an event system for payment state changes:
   - Dispatch events for payment status changes
   - Allow other modules to react to payment events
   - Improve extensibility without modifying core code

3. **Separation of Concerns**:
   - Move webhook signature verification to a dedicated service
   - Create a dedicated webhook dispatcher to process webhooks
   - Split payment processing logic from controller logic

### 4. Standardized Payment Data Flow

- Both data sources (Callback webhook request body and getPayment API response) provide the same MONEI payment object structure as defined in the MONEI API
- Create a Payment DTO (Data Transfer Object) to encapsulate the payment data from either source
- Implement a PaymentDataProvider interface with two implementations:
  - WebhookPaymentDataProvider: Extracts payment data from the webhook request body
  - ApiPaymentDataProvider: Fetches payment data using the MONEI API
- Both providers will return data in the same standardized format, simplifying processing logic
- Add validation to ensure required fields are present regardless of source

### 5. Safe Invoice Generation

- Modify the invoice generation process to check if the payment is already captured
- Implement a "capture if not already captured" logic
- Add transaction status verification before attempting capture
- Wrap the capture operation in try-catch to handle "already captured" exceptions

### 5. Intelligent Invoice Handling (Based on Stripe's Implementation)

Following Stripe's approach to invoice handling:

1. **State-Aware Invoice Processing**:
   - Check `canInvoice()` to determine if a new invoice needs to be created
   - If an invoice cannot be created, check the existing invoice collection for invoices that:
     - Can be captured (`canCapture()`)
     - Are in an open state (`getState() == STATE_OPEN`)

2. **Transaction ID Management**:
   - Set transaction ID on the invoice only if it doesn't already have one
   - Update the payment's last transaction ID for consistency

3. **Already Captured Payment Handling**:
   - Check for payment capture status using MONEI API before attempting capture
   - Compare captured amounts with intended capture amount to detect partial captures
   - Handle different scenarios with appropriate user messages:
     - Full capture already completed: Log and continue
     - Partial capture already completed: Either attempt to capture remaining amount or abort with message
     - Failed capture: Provide detailed error message with reason

4. **Partial Capture Constraints**:
   - Track if a payment has already been partially captured
   - Since MONEI only supports a single partial capture per payment, prevent multiple partial captures
   - If a partial invoice exists and another partial capture is attempted, either:
     - Capture the remaining amount in full (if possible)
     - Show an error message explaining the limitation
     - Provide guidance on using the MONEI dashboard for manual adjustments if needed

5. **Complete Order Processing**:
   - Save the invoice, order, and send invoice email in a consistent manner
   - Ensure all associated entities are properly updated

6. **Transaction and Lock Coordination**:
   - Use the LockManager to ensure invoice operations aren't duplicated
   - Wrap invoice operations in transactions for atomicity
   - Release locks even if exceptions occur

7. **Handling Authorized Payments (Stripe-Like Approach)**:
   - For payments with "AUTHORIZED" status:
     - Create an invoice in Magento but set its state to `Invoice::STATE_OPEN`
     - Set `$invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE)` to prevent automatic capture
     - Store the MONEI payment ID with the invoice for later capture
   - When admin clicks "Capture" on the invoice:
     - Retrieve the MONEI payment ID linked to the invoice
     - Call MONEI API to capture the payment
     - Update the invoice to paid status after successful capture
   - This approach provides:
     - Better visibility: Admins can immediately see authorized payments with pending invoices
     - Clear control: Two-step process (authorize → capture) is clearly represented in the system
     - Audit trail: Clear record of when authorization happened versus capture
     - Familiar workflow: Aligns with Magento's standard invoice capture process

### 6. Idempotency Keys for API Calls

Following Stripe's pattern:
- Implement idempotency key generation for all API calls to MONEI
- Store idempotency keys with transactions to prevent duplicates
- Re-use idempotency keys for retry attempts

## Implementation Details

### 1. Interface Definitions

#### PaymentProcessorInterface

```php
<?php

namespace Monei\MoneiPayment\Api;

/**
 * Interface for processing MONEI payments with concurrency control
 */
interface PaymentProcessorInterface
{
    /**
     * Process payment with locking to prevent race conditions
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @param array $paymentData Payment data from MONEI
     * @return PaymentProcessingResultInterface
     */
    public function process(string $orderId, string $paymentId, array $paymentData): PaymentProcessingResultInterface;

    /**
     * Check if a payment is currently being processed
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @return bool
     */
    public function isProcessing(string $orderId, string $paymentId): bool;

    /**
     * Wait for payment processing to complete
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @param int $timeout Maximum time to wait in seconds
     * @return bool True if processing completed, false if timed out
     */
    public function waitForProcessing(string $orderId, string $paymentId, int $timeout = 15): bool;
    
    /**
     * Get the current payment status from MONEI API
     *
     * @param string $paymentId MONEI payment ID
     * @return array Payment data
     * @throws \Exception When API call fails, with original error message
     */
    public function getPaymentStatus(string $paymentId): array;
}
```

#### PaymentProcessingResultInterface

```php
<?php

namespace Monei\MoneiPayment\Api;

/**
 * Interface for payment processing result
 */
interface PaymentProcessingResultInterface
{
    /**
     * Get the payment status
     *
     * @return string
     */
    public function getStatus(): string;
    
    /**
     * Get the order ID
     *
     * @return string
     */
    public function getOrderId(): string;
    
    /**
     * Get the payment ID
     *
     * @return string
     */
    public function getPaymentId(): string;
    
    /**
     * Check if processing was successful
     *
     * @return bool
     */
    public function isSuccessful(): bool;
    
    /**
     * Get any error message
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string;
    
    /**
     * Get MONEI status code
     *
     * @return string|null
     */
    public function getStatusCode(): ?string;
    
    /**
     * Get the full error response from MONEI API
     * This allows merchants to see the complete error details
     *
     * @return array|null
     */
    public function getFullErrorResponse(): ?array;
    
    /**
     * Get a user-friendly error message suitable for display
     * 
     * @return string|null
     */
    public function getDisplayErrorMessage(): ?string;
}
```

#### LockManagerInterface

```php
<?php

namespace Monei\MoneiPayment\Api;

/**
 * Interface for the unified locking mechanism
 */
interface LockManagerInterface
{
    /**
     * Lock an order for processing
     *
     * @param string $incrementId Order increment ID
     * @param int $timeout Lock acquisition timeout in seconds
     * @return bool True if lock acquired, false otherwise
     */
    public function lockOrder(string $incrementId, int $timeout = 5): bool;
    
    /**
     * Unlock an order
     *
     * @param string $incrementId Order increment ID
     * @return bool True if lock released, false otherwise
     */
    public function unlockOrder(string $incrementId): bool;
    
    /**
     * Check if an order is locked
     *
     * @param string $incrementId Order increment ID
     * @return bool True if locked, false otherwise
     */
    public function isOrderLocked(string $incrementId): bool;
    
    /**
     * Lock a specific payment for processing
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @param int $timeout Lock acquisition timeout in seconds
     * @return bool True if lock acquired, false otherwise
     */
    public function lockPayment(string $orderId, string $paymentId, int $timeout = 5): bool;
    
    /**
     * Unlock a payment
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @return bool True if lock released, false otherwise
     */
    public function unlockPayment(string $orderId, string $paymentId): bool;
    
    /**
     * Check if a payment is locked
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @return bool True if locked, false otherwise
     */
    public function isPaymentLocked(string $orderId, string $paymentId): bool;
    
    /**
     * Wait for a payment to be unlocked
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @param int $timeout Maximum wait time in seconds
     * @param int $interval Check interval in milliseconds
     * @return bool True if unlocked before timeout, false otherwise
     */
    public function waitForPaymentUnlock(string $orderId, string $paymentId, int $timeout = 15, int $interval = 500): bool;
    
    /**
     * Execute a callback with a payment lock
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @param callable $callback Function to execute with the lock
     * @param int $timeout Lock acquisition timeout in seconds
     * @return mixed The result of the callback
     * @throws \Exception If lock cannot be acquired or callback throws exception
     */
    public function executeWithPaymentLock(string $orderId, string $paymentId, callable $callback, int $timeout = 5);
}
```

#### WebhooksHelperInterface (New, inspired by Stripe)

```php
<?php

namespace Monei\MoneiPayment\Api;

use Magento\Framework\App\RequestInterface;

/**
 * Interface for webhook handling
 */
interface WebhooksHelperInterface
{
    /**
     * Process a webhook event
     * 
     * @param RequestInterface $request
     * @return void
     */
    public function processWebhook(RequestInterface $request): void;
    
    /**
     * Verify webhook signature
     * 
     * @param string $payload
     * @param array $headers
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, array $headers): bool;
    
    /**
     * Dispatch webhook event to appropriate handler
     * 
     * @param array $eventData
     * @return void
     */
    public function dispatchEvent(array $eventData): void;
}
```

### 2. Stripe-Inspired Helper Classes

Following Stripe's comprehensive helper architecture, we'll add these additional helpers:

#### PaymentEventDispatcherInterface (New)

Will handle dispatching various payment-related events throughout the system, similar to Stripe's event-driven approach.

#### PaymentMethodHelper (New)

Will provide utilities for payment method handling, tokenization, and storage.

#### WebhookEventLogger (New)

Will handle specialized logging for webhook events, with appropriate filtering and masking of sensitive data.

### 3. Controller Updates

#### Callback Controller

The Callback controller will be significantly simplified by delegating all processing to specialized services:

1. **Signature Verification**: Using a dedicated SignatureVerificationService
2. **Webhook Processing**: Delegated to WebhooksHelper
3. **Response Handling**: Standardized responses for success/failure
4. **Authorized Payment Handling**: For "AUTHORIZED" status webhook events:
   - Create pending invoices using the same InvoiceService used by the Complete controller
   - Ensure consistency with the Complete controller's processing logic
   - Properly handle race conditions if both controllers receive events simultaneously

#### Complete Controller

The Complete controller will be updated to:

1. **Handle Customer Experience**: Focus on providing appropriate redirects
2. **Wait for Processing**: Use the LockManager to check if a payment is already being processed
3. **Fall Back to API**: If payment information is missing, fetch from API
4. **Process Authorized Payments**: For payments with "AUTHORIZED" status:
   - Create a pending invoice using the InvoiceService's createPendingInvoice method
   - Set appropriate order status for authorized but not captured payments
   - Store the payment ID for later capture

### 4. Invoice Service Implementation

Based on Stripe's approach, we will implement an `InvoiceService` class that handles all invoice-related operations:

```php
<?php

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService as MagentoInvoiceService;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Logger\Logger;

class InvoiceService
{
    /**
     * Process invoice creation with protection against duplicate operations
     *
     * @param Order $order
     * @param string|null $transactionId
     * @param bool $save
     * @return Invoice|null
     * @throws LocalizedException
     */
    public function processInvoice(
        Order $order, 
        ?string $transactionId = null, 
        bool $save = true
    ): ?Invoice {
        // Use a lock to prevent concurrent invoice operations
        return $this->lockManager->executeWithPaymentLock(
            $order->getIncrementId(),
            $order->getPayment()->getLastTransId() ?? 'order-payment',
            function() use ($order, $transactionId, $save) {
                try {
                    // If order already has an invoice, don't create a new one
                    if (!$order->canInvoice()) {
                        $this->logger->info(
                            "Order already has an invoice, skipping invoice creation",
                            ['order_id' => $order->getIncrementId()]
                        );
                        return null;
                    }
                    
                    // No need to check if payment is already processed
                    // Simply attempt to create and capture the invoice
                    // MONEI API will return an error if the payment has already been captured
                    
                    // Create a new invoice
                    $invoice = $this->magentoInvoiceService->prepareInvoice($order);
                    
                    if (!$invoice->getTotalQty()) {
                        $this->logger->warning(
                            "Cannot create invoice with zero items",
                            ['order_id' => $order->getIncrementId()]
                        );
                        return null;
                    }
                    
                    // Set transaction ID
                    if ($transactionId) {
                        $invoice->setTransactionId($transactionId);
                        $order->getPayment()->setLastTransId($transactionId);
                    }
                    
                    // Register and capture the invoice
                    $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                    $invoice->register();
                    
                    if ($save) {
                        $this->saveInvoice($invoice);
                    }
                    
                    return $invoice;
                    
                } catch (\Exception $e) {
                    // Handle already captured payments gracefully
                    if ($this->isAlreadyCapturedError($e)) {
                        $this->logger->info(
                            "Payment was already captured, no need to create an invoice",
                            ['order_id' => $order->getIncrementId()]
                        );
                        return null;
                    }
                    
                    // Log the error with full details
                    $this->logger->error(
                        "Error during invoice processing: " . $e->getMessage(),
                        ['order_id' => $order->getIncrementId(), 'exception' => $e]
                    );
                    
                    // Pass the error message through
                    throw new LocalizedException(
                        __('MONEI Payment Error: %1', $e->getMessage())
                    );
                }
            }
        );
    }
    
    /**
     * Save the invoice and related entities
     */
    private function saveInvoice(Invoice $invoice, Order $order): void
    {
        $transaction = $this->transactionFactory->create();
        $transaction->addObject($invoice);
        $transaction->addObject($order);
        $transaction->save();
        
        // Send invoice email if needed
        if ($this->moduleConfig->shouldSendInvoiceEmail()) {
            $this->invoiceSender->send($invoice);
        }
    }
    
    /**
     * Check if exception indicates payment was already captured
     */
    private function isAlreadyCapturedError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        return (
            strpos($message, 'already been captured') !== false ||
            strpos($message, 'has already been paid') !== false ||
            strpos($message, 'already a paid invoice') !== false ||
            strpos($message, 'duplicated operation') !== false ||
            strpos($message, 'transaction has already been captured') !== false
        );
    }

    /**
     * Create pending invoice for authorized payment
     */
    public function createPendingInvoice(
        Order $order,
        string $paymentId,
        bool $save = true
    ): ?Invoice {
        // Use a lock to prevent concurrent invoice operations
        return $this->lockManager->executeWithPaymentLock(
            $order->getIncrementId(),
            $paymentId,
            function() use ($order, $paymentId, $save) {
                // If order already has an invoice, don't create a new one
                if (!$order->canInvoice()) {
                    $this->logger->info(
                        "Order already has an invoice, skipping pending invoice creation",
                        ['order_id' => $order->getIncrementId()]
                    );
                    return null;
                }
                
                // Create a new invoice
                $invoice = $this->magentoInvoiceService->prepareInvoice($order);
                
                // Link the payment ID to the invoice for later capture
                $invoice->setTransactionId($paymentId);
                $order->getPayment()->setLastTransId($paymentId);
                
                // Set to pending - don't capture yet
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
                $invoice->register();
                
                if ($save) {
                    $this->saveInvoice($invoice, $order);
                }
                
                $this->logger->info(
                    "Created pending invoice for authorized payment",
                    ['order_id' => $order->getIncrementId(), 'payment_id' => $paymentId]
                );
                
                return $invoice;
            }
        );
    }
}
```

3. **Partial Capture Support**:
   - While MONEI supports a single partial capture per payment, in typical "Authorize and Capture" mode this is rarely needed
   - The implementation still maintains support for partial captures with a verification:

```php
/**
 * Process partial invoice with protection against multiple partial captures
 *
 * @param Order $order
 * @param array $qtys
 * @param string|null $transactionId
 * @param bool $save
 * @return Invoice|null
 * @throws LocalizedException
 */
public function processPartialInvoice(
    Order $order, 
    array $qtys,
    ?string $transactionId = null, 
    bool $save = true
): ?Invoice {
    return $this->lockManager->executeWithPaymentLock(
        $order->getIncrementId(),
        $order->getPayment()->getLastTransId() ?? 'order-payment',
        function() use ($order, $qtys, $transactionId, $save) {
            try {
                // Check if there's already a partial invoice
                if ($this->hasPartialCapture($order)) {
                    throw new LocalizedException(
                        __('MONEI only supports a single partial capture per payment. Please capture the full remaining amount.')
                    );
                }
                
                if (!$order->canInvoice()) {
                    $this->logger->info(
                        "Cannot create partial invoice - order already fully invoiced",
                        ['order_id' => $order->getIncrementId()]
                    );
                    return null;
                }
                
                // Create a partial invoice
                $invoice = $this->magentoInvoiceService->prepareInvoice($order, $qtys);
                
                if (!$invoice->getTotalQty()) {
                    throw new LocalizedException(
                        __('Cannot create an invoice without items.')
                    );
                }
                
                if ($transactionId) {
                    $invoice->setTransactionId($transactionId);
                    $order->getPayment()->setLastTransId($transactionId);
                }
                
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                $invoice->register();
                
                if ($save) {
                    $this->saveInvoice($invoice, $order);
                }
                
                return $invoice;
                
            } catch (\Exception $e) {
                $this->logger->error(
                    "Error during partial invoice processing: " . $e->getMessage(),
                    ['order_id' => $order->getIncrementId(), 'exception' => $e]
                );
                
                throw $e;
            }
        }
    );
}

/**
 * Check if order already has partial captures
 */
private function hasPartialCapture(Order $order): bool
{
    foreach ($order->getInvoiceCollection() as $invoice) {
        if ($invoice->getState() == Invoice::STATE_PAID && 
            $invoice->getBaseGrandTotal() < $order->getBaseGrandTotal()) {
            return true;
        }
    }
    return false;
}
```

4. **Concurrency Protection**:
   - The locking mechanism prevents both the Callback and Complete controllers from creating duplicate invoices
   - Each invoice operation is wrapped in a transaction for atomicity
   - Locks are always released, even when exceptions occur

## Expected Results

1. **No Race Conditions**: Eliminate race conditions between Callback and Complete controllers
2. **Consistent Processing**: Both controllers use the same unified processing service
3. **No Duplicate Invoices**: Invoice generation is protected by locks and duplicate checking
4. **Error Resilience**: Proper error handling for already captured payments and other errors
5. **Improved Customer Experience**: Appropriate redirects based on payment status
6. **Better Diagnostics**: Improved logging for troubleshooting
7. **Enhanced Security**: Robust signature verification for webhooks

## Deployment and Rollout Strategy

1. **Development Phase**:
   - Implement changes in a feature branch
   - Comprehensive unit and integration testing

2. **Testing Phase**:
   - QA testing in development environment
   - Stress testing with simulated concurrent requests

3. **Production Rollout**:
   - Deploy during low-traffic period
   - Monitor for any issues
   - Have rollback plan ready

4. **Post-Deployment Verification**:
   - Monitor payment processing logs
   - Verify no duplicate invoices or race conditions
   - Watch for any error spikes
   - Collect metrics on lock acquisition and release times

### 5. Testing Strategy

#### Unit Tests

1. **ProcessPaymentService Tests**:
   - Test payment processing with "SUCCEEDED" status from MONEI
   - Test payment processing with "AUTHORIZED" status from MONEI
   - Test locking scenarios between Callback and Complete controllers
   - Test proper payment data handling from both sources

2. **InvoiceService Tests**:
   - Test direct invoice creation on payment success:
     - Verify invoice is properly created and registered
     - Verify transaction ID is set correctly
     - Verify payment's last transaction ID is updated
   - Test pending invoice creation for authorized payments:
     - Verify invoice is created with STATE_OPEN status
     - Verify CAPTURE_OFFLINE is set correctly
     - Verify payment ID is stored properly for later capture
   - Test duplicate invoice prevention:
     - Verify no new invoice is created when one already exists
     - Verify proper logging when skipping duplicate invoice creation
   - Test error handling:
     - Verify graceful handling of "already captured" errors
     - Verify proper error logging and message display
   - Test concurrent processing:
     - Verify lock acquisition prevents duplicate invoice creation
     - Verify lock release occurs even when exceptions are thrown

3. **Partial Capture Tests**:
   - Test successful partial capture
   - Test prevention of multiple partial captures (MONEI limitation)
   - Test proper error messages for partial capture constraints

4. **LockManager Tests**:
   - Test payment-specific lock acquisition and release
   - Test order-based locking
   - Test execution with lock pattern
   - Test timeout handling
   - Test lock waiting functionality

5. **WebhooksHelper Tests**:
   - Test webhook signature validation
   - Test proper webhook event processing
   - Test error handling during webhook processing

6. **Error Handling Tests**:
   - Test direct display of MONEI API error messages
   - Test user-friendly formatting of technical errors
   - Verify comprehensive error logging
   - Test recovery from transient errors

#### Integration Tests

1. **End-to-End Payment Flow**:
   - Test full checkout-to-capture flow with MONEI redirect
   - Verify proper order and invoice creation
   - Test successful email sending

2. **Concurrency Handling**:
   - Test Callback arriving before Complete
   - Test Complete arriving before Callback
   - Test simultaneous processing of both notifications
   - Verify correct final state in all scenarios
   - Confirm no duplicate invoices are created

3. **Payment Status Handling**:
   - Test handling of "succeeded" status
   - Test handling of "failed" status
   - Test handling of "cancelled" status
   - Verify proper order state transitions

#### Specific Test Cases

1. **Single Invoice Creation**: Verify that only one invoice is created per order
2. **Already Processed Detection**: Test detection of already processed payments
3. **Concurrent Request Handling**: Test proper handling when both controllers process simultaneously
4. **Lock Wait Behavior**: Test waiting for locks to be released
5. **Webhook Signature Validation**: Test proper signature verification
6. **Error Message Display**: Verify merchant-friendly error messages
7. **Partial Capture Success**: Test successful partial capture
8. **Multiple Partial Capture Prevention**: Test prevention of multiple partial captures
9. **Admin Capture of Authorized Payments**: Test the full flow of:
   - Creating a pending invoice for an authorized payment
   - Admin initiating capture through Magento admin panel
   - Successful capture via MONEI API
   - Invoice and order status updating correctly
   - Proper error handling if capture fails at the MONEI level
