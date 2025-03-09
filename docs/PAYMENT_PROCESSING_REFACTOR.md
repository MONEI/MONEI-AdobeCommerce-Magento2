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
   - `CallbackHelper`: For handling webhook processing, validation, and dispatching
   - `PaymentStatusHelper`: For consistent payment status handling

2. **Separation of Concerns**:
   - Move callback signature verification to a dedicated service
   - Create a dedicated callback dispatcher to process callbacks
   - Split payment processing logic from controller logic

### 4. Standardized Payment Data Flow

- Both data sources (Callback webhook request body and getPayment API response) provide the same MONEI payment object structure as defined in the MONEI API
- Create a Payment DTO (Data Transfer Object) to encapsulate the payment data from either source
- Implement a PaymentDataProvider interface with two implementations:
  - CallbackPaymentDataProvider: Extracts payment data from the callback request body
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
