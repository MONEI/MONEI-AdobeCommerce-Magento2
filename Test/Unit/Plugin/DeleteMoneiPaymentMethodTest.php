<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Plugin;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Monei\MoneiPayment\Api\Service\Customer\CustomerServiceInterface;
use Monei\MoneiPayment\Plugin\DeleteMoneiPaymentMethod;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\TestCase;

class DeleteMoneiPaymentMethodTest extends TestCase
{
    /**
     * Bug: deleting a card of another payment provider calls MONEI.
     */
    public function testNonMoneiTokenMakesNoServiceCall(): void
    {
        $customerService = $this->createMock(CustomerServiceInterface::class);
        $customerService->expects($this->never())->method('deletePaymentMethod');

        $token = $this->createMock(PaymentTokenInterface::class);
        $token->method('getPaymentMethodCode')->willReturn('braintree');
        $token->method('getCustomerId')->willReturn(5);
        $token->method('getGatewayToken')->willReturn('tok_1');

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $plugin = new DeleteMoneiPaymentMethod($customerService, $storeManager, $this->createMock(Logger::class));

        $subject = $this->createMock(PaymentTokenRepositoryInterface::class);
        $this->assertTrue($plugin->afterDelete($subject, true, $token));
    }

    /**
     * Bug: a MONEI card delete sends the public hash, or the wrong customer or store,
     * so MONEI answers 404 at debug level and the card stays live.
     */
    public function testMoneiTokenDeletesTheGatewayTokenAtMonei(): void
    {
        $customerService = $this->createMock(CustomerServiceInterface::class);
        $customerService->expects($this->once())->method('deletePaymentMethod')->with(5, 2, 'tok_1');

        $token = $this->createMock(PaymentTokenInterface::class);
        $token->method('getPaymentMethodCode')->willReturn('monei_card');
        $token->method('getCustomerId')->willReturn(5);
        $token->method('getGatewayToken')->willReturn('tok_1');
        $token->method('getPublicHash')->willReturn('hash_1');

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $plugin = new DeleteMoneiPaymentMethod($customerService, $storeManager, $this->createMock(Logger::class));

        $subject = $this->createMock(PaymentTokenRepositoryInterface::class);
        $this->assertTrue($plugin->afterDelete($subject, true, $token));
    }
}
