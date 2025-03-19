<?php

/**
 * Test case for ProcessingLock.
 *
 * @category  Monei
 * @package   Monei\MoneiPayment
 * @author    Monei <info@monei.com>
 * @copyright 2023 Monei
 * @license   https://opensource.org/license/mit/ MIT License
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Service;

use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Model\Service\ProcessingLock;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for ProcessingLock.
 */
class ProcessingLockTest extends TestCase
{
    /**
     * @var ProcessingLock
     */
    private $processingLock;

    /**
     * @var LockManagerInterface|MockObject
     */
    private $lockManagerMock;

    /**
     * @var Logger|MockObject
     */
    private $loggerMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->lockManagerMock = $this->createMock(LockManagerInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);
        $this->processingLock = new ProcessingLock($this->lockManagerMock, $this->loggerMock);
    }

    /**
     * Test executeWithLock method
     *
     * @return void
     */
    public function testExecuteWithLock(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_12345';
        $callbackResult = 'success';

        $callback = function () use ($callbackResult) {
            return $callbackResult;
        };

        $this
            ->lockManagerMock
            ->expects($this->once())
            ->method('executeWithPaymentLock')
            ->with($orderId, $paymentId, $this->callback(function ($param) use ($callback) {
                return $param instanceof \Closure;
            }))
            ->willReturn($callbackResult);

        $result = $this->processingLock->executeWithLock($orderId, $paymentId, $callback);
        $this->assertEquals($callbackResult, $result);
    }

    /**
     * Test executeWithOrderLock method
     *
     * @return void
     */
    public function testExecuteWithOrderLock(): void
    {
        $incrementId = '000000001';
        $callbackResult = 'success';

        $callback = function () use ($callbackResult) {
            return $callbackResult;
        };

        $this
            ->lockManagerMock
            ->expects($this->once())
            ->method('executeWithOrderLock')
            ->with($incrementId, $this->callback(function ($param) use ($callback) {
                return $param instanceof \Closure;
            }))
            ->willReturn($callbackResult);

        $result = $this->processingLock->executeWithOrderLock($incrementId, $callback);
        $this->assertEquals($callbackResult, $result);
    }

    /**
     * Test acquireLock method
     *
     * @return void
     */
    public function testAcquireLock(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_12345';
        $timeout = 300;

        $this
            ->lockManagerMock
            ->expects($this->once())
            ->method('lockPayment')
            ->with($orderId, $paymentId, $timeout)
            ->willReturn(true);

        $result = $this->processingLock->acquireLock($orderId, $paymentId, $timeout);
        $this->assertTrue($result);
    }

    /**
     * Test acquireOrderLock method
     *
     * @return void
     */
    public function testAcquireOrderLock(): void
    {
        $incrementId = '000000001';
        $timeout = 300;

        $this
            ->lockManagerMock
            ->expects($this->once())
            ->method('lockOrder')
            ->with($incrementId, $timeout)
            ->willReturn(true);

        $result = $this->processingLock->acquireOrderLock($incrementId, $timeout);
        $this->assertTrue($result);
    }

    /**
     * Test releaseLock method
     *
     * @return void
     */
    public function testReleaseLock(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_12345';

        $this
            ->lockManagerMock
            ->expects($this->once())
            ->method('unlockPayment')
            ->with($orderId, $paymentId)
            ->willReturn(true);

        $result = $this->processingLock->releaseLock($orderId, $paymentId);
        $this->assertTrue($result);
    }

    /**
     * Test releaseOrderLock method
     *
     * @return void
     */
    public function testReleaseOrderLock(): void
    {
        $incrementId = '000000001';

        $this
            ->lockManagerMock
            ->expects($this->once())
            ->method('unlockOrder')
            ->with($incrementId)
            ->willReturn(true);

        $result = $this->processingLock->releaseOrderLock($incrementId);
        $this->assertTrue($result);
    }

    /**
     * Test isLocked method
     *
     * @return void
     */
    public function testIsLocked(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_12345';

        $this
            ->lockManagerMock
            ->expects($this->once())
            ->method('isPaymentLocked')
            ->with($orderId, $paymentId)
            ->willReturn(true);

        $result = $this->processingLock->isLocked($orderId, $paymentId);
        $this->assertTrue($result);
    }

    /**
     * Test isOrderLocked method
     *
     * @return void
     */
    public function testIsOrderLocked(): void
    {
        $incrementId = '000000001';

        $this
            ->lockManagerMock
            ->expects($this->once())
            ->method('isOrderLocked')
            ->with($incrementId)
            ->willReturn(true);

        $result = $this->processingLock->isOrderLocked($incrementId);
        $this->assertTrue($result);
    }

    /**
     * Test waitForUnlock method
     *
     * @return void
     */
    public function testWaitForUnlock(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_12345';
        $timeout = 30;
        $interval = 100;

        $this
            ->lockManagerMock
            ->expects($this->once())
            ->method('waitForPaymentUnlock')
            ->with($orderId, $paymentId, $timeout, $interval)
            ->willReturn(true);

        $result = $this->processingLock->waitForUnlock($orderId, $paymentId, $timeout, $interval);
        $this->assertTrue($result);
    }

    /**
     * Test waitForUnlock method with default parameters
     *
     * @return void
     */
    public function testWaitForUnlockWithDefaults(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_12345';

        $this
            ->lockManagerMock
            ->expects($this->once())
            ->method('waitForPaymentUnlock')
            ->with(
                $orderId,
                $paymentId,
                LockManagerInterface::DEFAULT_MAX_WAIT_TIME,
                LockManagerInterface::DEFAULT_WAIT_INTERVAL
            )
            ->willReturn(true);

        $result = $this->processingLock->waitForUnlock($orderId, $paymentId);
        $this->assertTrue($result);
    }
}
