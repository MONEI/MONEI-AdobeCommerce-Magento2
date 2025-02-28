# Monei Payment Concurrency Management

## Race Conditions in Payment Processing

When processing payments, especially in webhook and callback scenarios, race conditions can occur when multiple processes attempt to update the same order simultaneously. This can lead to:

1. **Data Inconsistency**: Different processes writing conflicting data to the same order
2. **Double Processing**: The same payment being processed twice
3. **Lost Updates**: One process overwriting changes made by another
4. **Inconsistent Order State**: Order being left in an inconsistent state

## Lock Management Solutions

To prevent these issues, we've implemented several lock management mechanisms:

### 1. ProcessingLock Service

The `Monei\MoneiPayment\Model\Service\ProcessingLock` service provides a way to acquire and release locks for specific order and payment ID combinations:

```php
<?php
// Example usage:
public function processPayment(array $paymentData): bool
{
    $orderId = $paymentData['orderId'];
    $paymentId = $paymentData['id'];
    
    // Use the executeWithLock pattern
    return $this->processingLock->executeWithLock($orderId, $paymentId, function() use ($paymentData) {
        // Your payment processing logic here
        // The lock is automatically released even if an exception occurs
        return $result;
    });
}
```

### 2. OrderProcessor Service

The `Monei\MoneiPayment\Model\Service\OrderProcessor` service provides a higher-level abstraction for processing orders with transaction and lock management:

```php
<?php
// Example usage:
public function updateOrderStatus(string $incrementId, string $status): bool
{
    return $this->orderProcessor->processOrderById($incrementId, function(OrderInterface $order, $transaction) use ($status) {
        // Update order status
        $order->setStatus($status);
        
        // The transaction and lock are handled automatically
        return true;
    });
}
```

### 3. OrderLockManager

The `Monei\MoneiPayment\Api\OrderLockManagerInterface` provides low-level lock management for orders:

```php
<?php
// Example manual usage:
public function doSomethingWithOrder(string $incrementId): void
{
    if ($this->orderLockManager->isLocked($incrementId)) {
        // Order is being processed by another request
        return;
    }
    
    try {
        $this->orderLockManager->lock($incrementId);
        
        // Process the order
        
    } finally {
        // Always release the lock
        $this->orderLockManager->unlock($incrementId);
    }
}
```

## Best Practices

1. **Always Use try/finally Blocks**: Ensure locks are always released, even if an exception occurs
2. **Check for Existing Locks**: Before acquiring a lock, check if it's already locked
3. **Use Transactions**: For database operations, use transactions to ensure atomicity
4. **Log Lock Events**: Log when locks are acquired and released for debugging
5. **Implement Idempotency**: Even with locks, implement idempotency checks based on transaction IDs
6. **Use Higher-Level Abstractions**: Prefer the `executeWithLock` and `processOrderById` methods over direct lock management
7. **Set Appropriate Timeouts**: Configure lock timeouts based on expected processing time

## Handling Webhook Callbacks

For webhook callbacks:

1. **Verify Signatures**: Always verify the callback signature before processing
2. **Check Idempotency Keys**: Use payment IDs or transaction IDs as idempotency keys
3. **Store Processed Transactions**: Keep a record of processed transactions to prevent double-processing
4. **Use the ProcessingLock**: Always acquire a lock before processing a callback

## Database-Level Considerations

1. **Transaction Isolation Level**: Use appropriate isolation level (SERIALIZABLE for critical operations)
2. **Distributed Locks**: If running on multiple servers, ensure the lock manager is properly configured
3. **Lock Timeout**: Configure appropriate lock timeouts to prevent indefinite waits
4. **Lock Monitoring**: Implement monitoring for lock acquisition failures and long-held locks 
