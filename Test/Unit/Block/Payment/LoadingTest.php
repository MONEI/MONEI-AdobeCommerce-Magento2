<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Block\Payment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\UrlInterface;
use Monei\MoneiPayment\Block\Payment\Loading;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LoadingTest extends TestCase
{
    /**
     * @var Loading
     */
    private $loadingBlock;

    /**
     * @var Context|MockObject
     */
    private $contextMock;

    /**
     * @var UrlInterface|MockObject
     */
    private $urlBuilderMock;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);
        $this->requestMock = $this->createMock(RequestInterface::class);

        // Setup context mock to return request mock
        $this
            ->contextMock
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($this->requestMock);

        $this->loadingBlock = new Loading(
            $this->contextMock,
            $this->urlBuilderMock
        );
    }

    /**
     * Test getPaymentId method
     *
     * @dataProvider paymentIdDataProvider
     */
    public function testGetPaymentId(?string $paramValue, string $expected): void
    {
        // We need to replace the request with our mock since Loading class uses getRequest()
        $this->setObjectProperty($this->loadingBlock, '_request', $this->requestMock);

        $this
            ->requestMock
            ->expects($this->once())
            ->method('getParam')
            ->with('payment_id')
            ->willReturn($paramValue);

        $this->assertEquals($expected, $this->loadingBlock->getPaymentId());
    }

    /**
     * Test getCompleteUrl method
     */
    public function testGetCompleteUrl(): void
    {
        $expectedUrl = 'https://example.com/monei/payment/complete';

        $this
            ->urlBuilderMock
            ->expects($this->once())
            ->method('getUrl')
            ->with('monei/payment/complete')
            ->willReturn($expectedUrl);

        $this->assertEquals($expectedUrl, $this->loadingBlock->getCompleteUrl());
    }

    /**
     * Test getOrderId method
     *
     * @dataProvider orderIdDataProvider
     */
    public function testGetOrderId(?string $paramValue, string $expected): void
    {
        // We need to replace the request with our mock since Loading class uses getRequest()
        $this->setObjectProperty($this->loadingBlock, '_request', $this->requestMock);

        $this
            ->requestMock
            ->expects($this->once())
            ->method('getParam')
            ->with('order_id')
            ->willReturn($paramValue);

        $this->assertEquals($expected, $this->loadingBlock->getOrderId());
    }

    /**
     * Data provider for testGetPaymentId
     */
    public function paymentIdDataProvider(): array
    {
        return [
            'with payment id' => ['payment123', 'payment123'],
            'without payment id' => [null, '']
        ];
    }

    /**
     * Data provider for testGetOrderId
     */
    public function orderIdDataProvider(): array
    {
        return [
            'with order id' => ['order123', 'order123'],
            'without order id' => [null, '']
        ];
    }

    /**
     * Helper method to set protected/private properties
     *
     * @param object $object
     * @param string $propertyName
     * @param mixed $value
     * @return void
     */
    private function setObjectProperty(object $object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
