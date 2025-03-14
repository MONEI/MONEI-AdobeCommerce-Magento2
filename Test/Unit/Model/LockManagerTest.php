<?php

namespace Monei\MoneiPayment\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface as MagentoLockManagerInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Model\LockManager;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LockManagerTest extends TestCase
{
    /**
     * @var LockManager
     */
    private LockManager $lockManager;

    /**
     * @var MagentoLockManagerInterface|MockObject
     */
    private MagentoLockManagerInterface $magentoLockManagerMock;

    /**
     * @var Logger|MockObject
     */
    private Logger $loggerMock;

    protected function setUp(): void
    {
        $this->magentoLockManagerMock = $this->createMock(MagentoLockManagerInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);
        
        $this->lockManager = new LockManager(
            $this->magentoLockManagerMock,
            $this->loggerMock
        );
    }
    
    public function testLockOrder(): void
    {
        // Configure magento lock manager to return success
        $this->magentoLockManagerMock->expects($this->once())
            ->method('lock')
            ->with('MONEI_ORDER_LOCK_12345', LockManagerInterface::DEFAULT_LOCK_TIMEOUT)
            ->willReturn(true);
            
        // Call the method
        $result = $this->lockManager->lockOrder('12345');
        
        // Verify result
        $this->assertTrue($result);
    }
    
    public function testLockOrderFails(): void
    {
        // Configure magento lock manager to return failure
        $this->magentoLockManagerMock->expects($this->once())
            ->method('lock')
            ->with('MONEI_ORDER_LOCK_12345', LockManagerInterface::DEFAULT_LOCK_TIMEOUT)
            ->willReturn(false);
            
        // Call the method
        $result = $this->lockManager->lockOrder('12345');
        
        // Verify result
        $this->assertFalse($result);
    }
    
    public function testUnlockOrder(): void
    {
        // First check if it's locked
        $this->magentoLockManagerMock->expects($this->atLeastOnce())
            ->method('isLocked')
            ->with('MONEI_ORDER_LOCK_12345')
            ->willReturn(true, false);
            
        // Configure magento lock manager to unlock successfully
        $this->magentoLockManagerMock->expects($this->once())
            ->method('unlock')
            ->with('MONEI_ORDER_LOCK_12345')
            ->willReturn(true);
            
        // Call the method
        $result = $this->lockManager->unlockOrder('12345');
        
        // Verify result
        $this->assertTrue($result);
    }
    
    public function testUnlockOrderWithRetries(): void
    {
        // First check if it's locked - returns true multiple times then false
        $this->magentoLockManagerMock->expects($this->atLeastOnce())
            ->method('isLocked')
            ->with('MONEI_ORDER_LOCK_12345')
            ->willReturn(true, true, false);
            
        // Configure magento lock manager to unlock successfully after 2 tries
        $this->magentoLockManagerMock->expects($this->exactly(2))
            ->method('unlock')
            ->with('MONEI_ORDER_LOCK_12345')
            ->willReturnOnConsecutiveCalls(false, true);
            
        // Call the method
        $result = $this->lockManager->unlockOrder('12345');
        
        // Verify result
        $this->assertTrue($result);
    }
    
    public function testIsOrderLocked(): void
    {
        // Configure magento lock manager
        $this->magentoLockManagerMock->expects($this->once())
            ->method('isLocked')
            ->with('MONEI_ORDER_LOCK_12345')
            ->willReturn(true);
            
        // Call the method
        $result = $this->lockManager->isOrderLocked('12345');
        
        // Verify result
        $this->assertTrue($result);
    }
    
    public function testLockPayment(): void
    {
        // Configure magento lock manager to return success
        $this->magentoLockManagerMock->expects($this->once())
            ->method('lock')
            ->with('MONEI_PAYMENT_LOCK_12345_pay_67890', LockManagerInterface::DEFAULT_LOCK_TIMEOUT)
            ->willReturn(true);
            
        // Call the method
        $result = $this->lockManager->lockPayment('12345', 'pay_67890');
        
        // Verify result
        $this->assertTrue($result);
    }
    
    public function testExecuteWithOrderLock(): void
    {
        // Configure magento lock manager to lock successfully
        $this->magentoLockManagerMock->expects($this->once())
            ->method('lock')
            ->with('MONEI_ORDER_LOCK_12345', LockManagerInterface::DEFAULT_LOCK_TIMEOUT)
            ->willReturn(true);
            
        // Mock isLocked to return true then false after unlock
        $this->magentoLockManagerMock->expects($this->atLeastOnce())
            ->method('isLocked')
            ->with('MONEI_ORDER_LOCK_12345')
            ->willReturn(true, false);
            
        // Configure magento lock manager to unlock successfully
        $this->magentoLockManagerMock->expects($this->once())
            ->method('unlock')
            ->with('MONEI_ORDER_LOCK_12345')
            ->willReturn(true);
        
        $callbackCalled = false;
        $expectedResult = 'callback result';
        
        // Execute with lock
        $result = $this->lockManager->executeWithOrderLock('12345', function() use (&$callbackCalled, $expectedResult) {
            $callbackCalled = true;
            return $expectedResult;
        });
        
        // Verify callback was called and returned the expected result
        $this->assertTrue($callbackCalled);
        $this->assertEquals($expectedResult, $result);
    }
    
    public function testExecuteWithOrderLockThrowsExceptionWhenLockFails(): void
    {
        // Configure magento lock manager to fail locking
        $this->magentoLockManagerMock->expects($this->once())
            ->method('lock')
            ->with('MONEI_ORDER_LOCK_12345', LockManagerInterface::DEFAULT_LOCK_TIMEOUT)
            ->willReturn(false);
            
        // Ensure unlock is never called
        $this->magentoLockManagerMock->expects($this->never())
            ->method('unlock');
        
        $callbackCalled = false;
        
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to acquire order lock for order 12345');
        
        // Execute with lock
        $this->lockManager->executeWithOrderLock('12345', function() use (&$callbackCalled) {
            $callbackCalled = true;
        });
        
        // Verify callback was not called
        $this->assertFalse($callbackCalled);
    }
    
    public function testExecuteWithOrderLockUnlocksWhenCallbackThrows(): void
    {
        // Configure magento lock manager to lock successfully
        $this->magentoLockManagerMock->expects($this->once())
            ->method('lock')
            ->with('MONEI_ORDER_LOCK_12345', LockManagerInterface::DEFAULT_LOCK_TIMEOUT)
            ->willReturn(true);
            
        // Mock isLocked to return true then false after unlock
        $this->magentoLockManagerMock->expects($this->atLeastOnce())
            ->method('isLocked')
            ->with('MONEI_ORDER_LOCK_12345')
            ->willReturn(true, false);
            
        // Configure magento lock manager to unlock successfully
        $this->magentoLockManagerMock->expects($this->once())
            ->method('unlock')
            ->with('MONEI_ORDER_LOCK_12345')
            ->willReturn(true);
        
        $callbackCalled = false;
        $exception = new \Exception('Callback throws exception');
        
        try {
            // Execute with lock
            $this->lockManager->executeWithOrderLock('12345', function() use (&$callbackCalled, $exception) {
                $callbackCalled = true;
                throw $exception;
            });
            
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // Verify the exception thrown was our original one
            $this->assertSame($exception, $e);
        }
        
        // Verify callback was called
        $this->assertTrue($callbackCalled);
    }
}