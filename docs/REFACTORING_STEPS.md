# MONEI Payment Processing Refactoring Implementation Steps

This document provides a step-by-step guide for implementing the refactoring plan outlined in `PAYMENT_PROCESSING_REFACTOR.md`.

## Phase 1: Core Infrastructure

### Step 1: Create Data Transfer Objects

1. Create `PaymentDTO` class to standardize payment data from different sources
   - Include all required fields from MONEI payment object
   - Add validation for required fields
   - Implement getters for all properties

2. Create `PaymentDataProviderInterface` and implementations:
   - `WebhookPaymentDataProvider`: Extract data from webhook request
   - `ApiPaymentDataProvider`: Fetch data from MONEI API

### Step 2: Implement Unified Locking Mechanism

1. Create `LockManagerInterface` in the Api directory
2. Implement `LockManager` class that consolidates the existing `ProcessingLock` and `OrderLockManager` classes:
   - Merge the functionality of both existing lock implementations
   - Maintain the same lock naming conventions and timeout settings
   - Ensure backward compatibility with existing code
   - Standardize the API for both order-level and payment-level locks
   - Implement waiting functionality for locks
   - Add transaction-style execution with automatic lock release
   - Ensure comprehensive logging for all lock operations

### Step 3: Create Payment Processing Service

1. Create `PaymentProcessorInterface` in the Api directory
2. Implement `ProcessPaymentService` class with:
   - Payment status handling logic
   - Integration with LockManager
   - Error handling with direct MONEI error messages
   - Support for both webhook and redirect flows

## Phase 2: Invoice Handling

### Step 4: Implement Invoice Service

1. Create `InvoiceService` class with:
   - `processInvoice` method for immediate capture
   - `createPendingInvoice` method for authorized payments
   - `processPartialInvoice` method with single partial capture constraint
   - Error handling for already captured payments
   - Integration with LockManager

### Step 5: Implement Webhook Helper

1. Create `WebhooksHelperInterface` in the Api directory
2. Implement `WebhooksHelper` class with:
   - Signature verification
   - Event dispatching
   - Standardized webhook processing

## Phase 3: Controller Updates

### Step 6: Update Callback Controller

1. Refactor `Callback` controller to:
   - Use WebhooksHelper for signature verification
   - Use ProcessPaymentService for payment processing
   - Implement proper error handling
   - Add logging for all steps

### Step 7: Update Complete Controller

1. Refactor `Complete` controller to:
   - Use ProcessPaymentService for payment processing
   - Implement waiting for locks if payment is being processed
   - Fall back to API if payment data is missing
   - Provide appropriate customer redirects

## Phase 4: Admin Functionality

### Step 8: Implement Admin Capture for Authorized Payments

1. Create `CaptureService` class for admin-initiated captures
2. Update admin grid to show pending authorized payments
3. Implement capture action in admin panel

## Phase 5: Testing and Deployment

### Step 9: Implement Unit Tests

1. Create tests for all new services:
   - ProcessPaymentService tests
   - InvoiceService tests
   - LockManager tests
   - WebhooksHelper tests

### Step 10: Implement Integration Tests

1. Create end-to-end payment flow tests
2. Create concurrency tests
3. Create error handling tests

### Step 11: Deployment

1. Deploy to staging environment
2. Conduct QA testing
3. Deploy to production during low-traffic period
4. Monitor logs and metrics

## Implementation Order

For optimal implementation, follow this order:

1. Data structures (DTOs and interfaces)
2. Core services (LockManager, ProcessPaymentService)
3. Supporting services (InvoiceService, WebhooksHelper)
4. Controller updates
5. Admin functionality
6. Tests

This approach allows for incremental testing and validation at each step. 
