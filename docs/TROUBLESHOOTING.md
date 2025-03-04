# MONEI Payment Processing Troubleshooting Guide

This document provides guidance for troubleshooting common issues that may arise during implementation and operation of the refactored MONEI payment processing system.

## Common Implementation Pitfalls

### 1. Lock Management Issues

#### Symptoms:
- Deadlocks
- Payments stuck in processing
- Timeouts during checkout

#### Potential Causes:
- Locks not being released in error scenarios
- Incorrect lock naming
- Missing try/finally blocks
- Inconsistent usage of order vs. payment locks

#### Solutions:
- Ensure all locks are released in finally blocks
- Use the unified LockManager consistently throughout the codebase
- Add comprehensive logging for lock acquisition and release
- Use unique lock names based on both order ID and payment ID
- Ensure backward compatibility with existing lock usage patterns
- Verify that lock timeouts are appropriate for each operation

### 2. Race Conditions

#### Symptoms:
- Duplicate invoices
- Inconsistent order status
- Payment processed multiple times

#### Potential Causes:
- Insufficient locking
- Lock timeout too short
- Missing transaction handling

#### Solutions:
- Ensure all critical operations are protected by locks
- Use database transactions for atomicity
- Implement idempotent processing
- Check existing state before making changes

### 3. API Error Handling

#### Symptoms:
- Unhelpful error messages
- Missing error details
- Unhandled exceptions

#### Potential Causes:
- Generic exception handling
- Missing error logging
- Insufficient error context

#### Solutions:
- Pass through MONEI API error messages
- Add context to error messages
- Implement comprehensive logging
- Handle specific error cases (e.g., already captured)

### 4. Webhook Signature Verification

#### Symptoms:
- Webhook verification failures
- Security warnings
- Rejected webhooks

#### Potential Causes:
- Incorrect signature algorithm
- Missing or incorrect webhook secret
- Header parsing issues

#### Solutions:
- Verify signature algorithm matches MONEI documentation
- Ensure webhook secret is correctly configured
- Add detailed logging for signature verification
- Test with MONEI's webhook testing tools

## Operational Troubleshooting

### 1. Payment Processing Issues

#### Diagnostic Steps:
1. Check payment status in MONEI dashboard
2. Verify webhook delivery in MONEI logs
3. Check Magento payment logs
4. Verify order status in Magento admin
5. Check for lock-related issues in logs

#### Common Fixes:
- Manually release stuck locks using Magento's lock management tools
- Verify webhook URL is accessible
- Check for API credential issues
- Ensure proper error handling

### 2. Invoice Creation Problems

#### Diagnostic Steps:
1. Check if payment was successful in MONEI
2. Verify if invoice creation was attempted (logs)
3. Check for "already captured" errors
4. Verify order can be invoiced (`canInvoice()`)

#### Common Fixes:
- Manually create invoice if payment was successful
- Check for partial captures
- Verify transaction IDs are correctly set
- Ensure proper error handling for already captured payments

### 3. Lock Contention

#### Diagnostic Steps:
1. Check lock acquisition times in logs
2. Monitor Magento's lock system for stuck locks
3. Check for concurrent requests
4. Verify lock timeout settings

#### Common Fixes:
- Adjust lock timeout settings
- Use Magento's lock management tools to clear stuck locks
- Add monitoring for lock acquisition times
- Optimize lock granularity

### 4. Performance Issues

#### Diagnostic Steps:
1. Monitor response times for payment operations
2. Check API call frequency
3. Monitor lock contention
4. Check database performance

#### Common Fixes:
- Optimize lock usage
- Implement caching for API responses
- Reduce unnecessary API calls
- Optimize database queries

## Debugging Tools

### 1. Logging

Enable detailed logging for troubleshooting:

```xml
<type name="Monei\MoneiPayment\Logger\Handler">
    <arguments>
        <argument name="level" xsi:type="const">Monolog\Logger::DEBUG</argument>
    </arguments>
</type>
```

### 2. Lock Monitoring

Create a CLI command to monitor locks:

```bash
bin/magento monei:locks:status
```

### 3. Payment Status Verification

Create a CLI command to verify payment status:

```bash
bin/magento monei:payment:status [payment_id]
```

### 4. Webhook Testing

Use MONEI's webhook testing tools or create a CLI command:

```bash
bin/magento monei:webhook:test
```

## Common Error Messages and Solutions

### "Payment has already been captured"

**Cause**: Attempting to capture a payment that has already been captured.

**Solution**: 
- Implement proper checking before capture
- Handle this error gracefully in the code
- Check if invoice already exists

### "Lock acquisition timeout"

**Cause**: Unable to acquire lock within the specified timeout.

**Solution**:
- Increase lock timeout
- Check for stuck locks using Magento's lock management tools
- Verify that the unified LockManager is being used consistently

### "Webhook signature verification failed"

**Cause**: Invalid or missing signature in webhook request.

**Solution**:
- Verify webhook secret
- Check signature algorithm
- Ensure all required headers are present

### "Invalid payment status transition"

**Cause**: Attempting to transition payment to an invalid state.

**Solution**:
- Verify current payment status before transition
- Implement proper state machine
- Check for concurrent modifications

## Recovery Procedures

### 1. Stuck Orders

For orders stuck in processing:

1. Check lock status using Magento's lock management tools
2. Verify payment status in MONEI
3. Manually release lock if necessary
4. Update order status based on MONEI payment status

### 2. Missing Invoices

For successful payments without invoices:

1. Verify payment status in MONEI
2. Check if order can be invoiced
3. Manually create invoice if necessary
4. Set transaction ID correctly

### 3. Duplicate Invoices

For orders with duplicate invoices:

1. Identify the correct invoice
2. Cancel or void incorrect invoices
3. Update order status
4. Check locking mechanism

### 4. Failed Webhooks

For webhooks that failed to process:

1. Check webhook logs
2. Verify signature
3. Manually process the webhook data
4. Update order status accordingly 
