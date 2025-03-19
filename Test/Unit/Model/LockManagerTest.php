<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface as MagentoLockManagerInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Model\LockManager;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Monei\MoneiPayment\Model\LockManager
 */
class LockManagerTest extends TestCase
{
    /**
     * @var MagentoLockManagerInterface|MockObject
     */
    private $magentoLockManagerMock;

    /**
     * @var Logger|MockObject
     */
    private $loggerMock;

    /**
     * @var LockManager
     */
    private $lockManager;

    protected function setUp(): void
    {
        $this->magentoLockManagerMock = $this->createMock(MagentoLockManagerInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);

        $this->lockManager = new LockManager(
            $this->magentoLockManagerMock,
            $this->loggerMock
        );
    }

    /**
     * Test lockOrder when lock is acquired successfully
     */
    public function testLockOrderSuccess(): void
    {
        $incrementId = '000000001';
        $lockName = 'MONEI_ORDER_LOCK_' . $incrementId;
        $timeout = 60;

        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('lock')
            ->with($lockName, $timeout)
            ->willReturn(true);

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('info')
            ->with(
                '[LockManager] Order lock acquired',
                [
                    'order_id' => $incrementId,
                    'timeout' => $timeout
                ]
            );

        $result = $this->lockManager->lockOrder($incrementId, $timeout);
        $this->assertTrue($result);
    }

    /**
     * Test lockOrder when lock acquisition fails
     */
    public function testLockOrderFailure(): void
    {
        $incrementId = '000000001';
        $lockName = 'MONEI_ORDER_LOCK_' . $incrementId;
        $timeout = 60;

        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('lock')
            ->with($lockName, $timeout)
            ->willReturn(false);

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('warning')
            ->with(
                '[LockManager] Failed to acquire order lock',
                [
                    'order_id' => $incrementId,
                    'timeout' => $timeout
                ]
            );

        $result = $this->lockManager->lockOrder($incrementId, $timeout);
        $this->assertFalse($result);
    }

    /**
     * Test unlockOrder when unlock is successful
     */
    public function testUnlockOrderSuccess(): void
    {
        $incrementId = '000000001';
        $lockName = 'MONEI_ORDER_LOCK_' . $incrementId;

        // First check is lock exists
        $this
            ->magentoLockManagerMock
            ->expects($this->atLeastOnce())
            ->method('isLocked')
            ->with($lockName)
            ->willReturn(true, false);

        // Then unlock
        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('unlock')
            ->with($lockName)
            ->willReturn(true);

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('info')
            ->with(
                '[LockManager] Order lock released',
                ['order_id' => $incrementId]
            );

        $result = $this->lockManager->unlockOrder($incrementId);
        $this->assertTrue($result);
    }

    /**
     * Test unlockOrder when unlock fails
     */
    public function testUnlockOrderFailure(): void
    {
        $incrementId = '000000001';
        $lockName = 'MONEI_ORDER_LOCK_' . $incrementId;

        // Lock exists but unlock fails
        $this
            ->magentoLockManagerMock
            ->expects($this->atLeastOnce())
            ->method('isLocked')
            ->with($lockName)
            ->willReturn(true);

        $this
            ->magentoLockManagerMock
            ->expects($this->exactly(3))
            ->method('unlock')
            ->with($lockName)
            ->willReturn(false);

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('warning')
            ->with(
                '[LockManager] Failed to release order lock',
                ['order_id' => $incrementId]
            );

        $result = $this->lockManager->unlockOrder($incrementId);
        $this->assertFalse($result);
    }

    /**
     * Test isOrderLocked
     */
    public function testIsOrderLocked(): void
    {
        $incrementId = '000000001';
        $lockName = 'MONEI_ORDER_LOCK_' . $incrementId;

        // Test when is locked
        $this->magentoLockManagerMock = $this->createMock(MagentoLockManagerInterface::class);
        $this->lockManager = new LockManager($this->magentoLockManagerMock, $this->loggerMock);

        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('isLocked')
            ->with($lockName)
            ->willReturn(true);

        $result = $this->lockManager->isOrderLocked($incrementId);
        $this->assertTrue($result);

        // Test when not locked in a separate test
        $this->magentoLockManagerMock = $this->createMock(MagentoLockManagerInterface::class);
        $this->lockManager = new LockManager($this->magentoLockManagerMock, $this->loggerMock);

        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('isLocked')
            ->with($lockName)
            ->willReturn(false);

        $result = $this->lockManager->isOrderLocked($incrementId);
        $this->assertFalse($result);
    }

    /**
     * Test lockPayment when lock is acquired successfully
     */
    public function testLockPaymentSuccess(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $lockName = 'MONEI_PAYMENT_LOCK_' . $orderId . '_' . $paymentId;
        $timeout = 60;

        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('lock')
            ->with($lockName, $timeout)
            ->willReturn(true);

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('info')
            ->with(
                '[LockManager] Payment lock acquired',
                [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'timeout' => $timeout
                ]
            );

        $result = $this->lockManager->lockPayment($orderId, $paymentId, $timeout);
        $this->assertTrue($result);
    }

    /**
     * Test lockPayment when lock acquisition fails
     */
    public function testLockPaymentFailure(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $lockName = 'MONEI_PAYMENT_LOCK_' . $orderId . '_' . $paymentId;
        $timeout = 60;

        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('lock')
            ->with($lockName, $timeout)
            ->willReturn(false);

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('warning')
            ->with(
                '[LockManager] Failed to acquire payment lock',
                [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'timeout' => $timeout
                ]
            );

        $result = $this->lockManager->lockPayment($orderId, $paymentId, $timeout);
        $this->assertFalse($result);
    }

    /**
     * Test unlockPayment when unlock is successful
     */
    public function testUnlockPaymentSuccess(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $lockName = 'MONEI_PAYMENT_LOCK_' . $orderId . '_' . $paymentId;

        // First check is lock exists
        $this
            ->magentoLockManagerMock
            ->expects($this->atLeastOnce())
            ->method('isLocked')
            ->with($lockName)
            ->willReturn(true, false);

        // Then unlock
        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('unlock')
            ->with($lockName)
            ->willReturn(true);

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('info')
            ->with(
                '[LockManager] Payment lock released',
                [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId
                ]
            );

        $result = $this->lockManager->unlockPayment($orderId, $paymentId);
        $this->assertTrue($result);
    }

    /**
     * Test unlockPayment when unlock fails
     */
    public function testUnlockPaymentFailure(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $lockName = 'MONEI_PAYMENT_LOCK_' . $orderId . '_' . $paymentId;

        // Lock exists but unlock fails
        $this
            ->magentoLockManagerMock
            ->expects($this->atLeastOnce())
            ->method('isLocked')
            ->with($lockName)
            ->willReturn(true);

        $this
            ->magentoLockManagerMock
            ->expects($this->exactly(3))
            ->method('unlock')
            ->with($lockName)
            ->willReturn(false);

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('warning')
            ->with(
                '[LockManager] Failed to release payment lock',
                [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId
                ]
            );

        $result = $this->lockManager->unlockPayment($orderId, $paymentId);
        $this->assertFalse($result);
    }

    /**
     * Test isPaymentLocked
     */
    public function testIsPaymentLocked(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $lockName = 'MONEI_PAYMENT_LOCK_' . $orderId . '_' . $paymentId;

        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('isLocked')
            ->with($lockName)
            ->willReturn(true);

        $result = $this->lockManager->isPaymentLocked($orderId, $paymentId);
        $this->assertTrue($result);
    }

    /**
     * Test waitForPaymentUnlock with successful unlock
     */
    public function testWaitForPaymentUnlockSuccess(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $lockName = 'MONEI_PAYMENT_LOCK_' . $orderId . '_' . $paymentId;
        $timeout = 5;
        $interval = 100;

        // First info log
        $this
            ->loggerMock
            ->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                [
                    '[LockManager] Waiting for payment lock release',
                    [
                        'order_id' => $orderId,
                        'payment_id' => $paymentId,
                        'timeout' => $timeout,
                        'interval' => $interval
                    ]
                ],
                [
                    '[LockManager] Payment lock released',
                    $this->callback(function ($params) use ($orderId, $paymentId) {
                        return isset($params['order_id']) &&
                            $params['order_id'] === $orderId &&
                            isset($params['payment_id']) &&
                            $params['payment_id'] === $paymentId &&
                            isset($params['waited']);
                    })
                ]
            );

        // Return false on the second call to simulate unlock
        $this
            ->magentoLockManagerMock
            ->expects($this->exactly(2))
            ->method('isLocked')
            ->with($lockName)
            ->willReturnOnConsecutiveCalls(true, false);

        $result = $this->lockManager->waitForPaymentUnlock($orderId, $paymentId, $timeout, $interval);
        $this->assertTrue($result);
    }

    /**
     * Test waitForPaymentUnlock with timeout
     */
    public function testWaitForPaymentUnlockTimeout(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $lockName = 'MONEI_PAYMENT_LOCK_' . $orderId . '_' . $paymentId;
        $timeout = 1;  // Short timeout for test
        $interval = 100;

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('info')
            ->with(
                '[LockManager] Waiting for payment lock release',
                [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'timeout' => $timeout,
                    'interval' => $interval
                ]
            );

        // Always return true to simulate lock never released
        $this
            ->magentoLockManagerMock
            ->expects($this->atLeastOnce())
            ->method('isLocked')
            ->with($lockName)
            ->willReturn(true);

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('warning')
            ->with(
                '[LockManager] Timeout waiting for payment lock release',
                [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'timeout' => $timeout
                ]
            );

        $result = $this->lockManager->waitForPaymentUnlock($orderId, $paymentId, $timeout, $interval);
        $this->assertFalse($result);
    }

    /**
     * Test executeWithPaymentLock with successful execution
     */
    public function testExecuteWithPaymentLockSuccess(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $lockName = 'MONEI_PAYMENT_LOCK_' . $orderId . '_' . $paymentId;
        $timeout = 60;
        $callbackResult = 'success';
        $callback = function () use ($callbackResult) {
            return $callbackResult;
        };

        // Lock is acquired
        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('lock')
            ->with($lockName, $timeout)
            ->willReturn(true);

        // Unlock is called after callback
        $this
            ->magentoLockManagerMock
            ->expects($this->atLeastOnce())
            ->method('isLocked')
            ->with($lockName)
            ->willReturn(true, false);

        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('unlock')
            ->with($lockName)
            ->willReturn(true);

        $result = $this->lockManager->executeWithPaymentLock($orderId, $paymentId, $callback, $timeout);
        $this->assertEquals($callbackResult, $result);
    }

    /**
     * Test executeWithPaymentLock with failed lock acquisition
     */
    public function testExecuteWithPaymentLockFailure(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $lockName = 'MONEI_PAYMENT_LOCK_' . $orderId . '_' . $paymentId;
        $timeout = 60;
        $callback = function () {
            $this->fail('Callback should not be executed');
        };

        // Lock fails
        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('lock')
            ->with($lockName, $timeout)
            ->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to acquire payment lock for order ' . $orderId . ', payment ' . $paymentId);

        $this->lockManager->executeWithPaymentLock($orderId, $paymentId, $callback, $timeout);
    }

    /**
     * Test executeWithOrderLock with successful execution
     */
    public function testExecuteWithOrderLockSuccess(): void
    {
        $incrementId = '000000001';
        $lockName = 'MONEI_ORDER_LOCK_' . $incrementId;
        $timeout = 60;
        $callbackResult = 'success';
        $callback = function () use ($callbackResult) {
            return $callbackResult;
        };

        // Lock is acquired
        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('lock')
            ->with($lockName, $timeout)
            ->willReturn(true);

        // Unlock is called after callback
        $this
            ->magentoLockManagerMock
            ->expects($this->atLeastOnce())
            ->method('isLocked')
            ->with($lockName)
            ->willReturn(true, false);

        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('unlock')
            ->with($lockName)
            ->willReturn(true);

        $result = $this->lockManager->executeWithOrderLock($incrementId, $callback, $timeout);
        $this->assertEquals($callbackResult, $result);
    }

    /**
     * Test executeWithOrderLock with failed lock acquisition
     */
    public function testExecuteWithOrderLockFailure(): void
    {
        $incrementId = '000000001';
        $lockName = 'MONEI_ORDER_LOCK_' . $incrementId;
        $timeout = 60;
        $callback = function () {
            $this->fail('Callback should not be executed');
        };

        // Lock fails
        $this
            ->magentoLockManagerMock
            ->expects($this->once())
            ->method('lock')
            ->with($lockName, $timeout)
            ->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to acquire order lock for order ' . $incrementId);

        $this->lockManager->executeWithOrderLock($incrementId, $callback, $timeout);
    }
}
