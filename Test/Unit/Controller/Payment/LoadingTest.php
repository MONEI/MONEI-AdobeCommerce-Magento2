<?php

/**
 * Test for Loading controller
 *
 * @category  Monei
 * @package   Monei_MoneiPayment
 * @author    Monei <developers@monei.com>
 * @copyright 2022 Monei (https://monei.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link      https://docs.monei.com/api
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Controller\Payment;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;
use Monei\MoneiPayment\Controller\Payment\Loading;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for Loading controller
 *
 * @license  https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link     https://docs.monei.com/api
 */
class LoadingTest extends TestCase
{
    /**
     * Loading controller instance
     *
     * @var Loading
     */
    private $_controller;

    /**
     * ResultFactory mock
     *
     * @var ResultFactory|MockObject
     */
    private $_resultFactoryMock;

    /**
     * Request mock
     *
     * @var HttpRequest|MockObject
     */
    private $_requestMock;

    /**
     * Logger mock
     *
     * @var Logger|MockObject
     */
    private $_loggerMock;

    /**
     * Page mock
     *
     * @var Page|MockObject
     */
    private $_pageMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_resultFactoryMock = $this->createMock(ResultFactory::class);
        $this->_requestMock = $this->createMock(HttpRequest::class);
        $this->_loggerMock = $this->createMock(Logger::class);
        $this->_pageMock = $this->createMock(Page::class);

        $this->_resultFactoryMock->method('create')->willReturn($this->_pageMock);

        $this->_controller = new Loading(
            $this->_resultFactoryMock,
            $this->_requestMock,
            $this->_loggerMock
        );
    }

    /**
     * Test execute method
     *
     * @return void
     */
    public function testExecute(): void
    {
        // Set up parameter map for request
        $paramMap = [
            ['payment_id', null, 'pay_test_123'],
            ['order_id', null, null]
        ];

        $this
            ->_requestMock
            ->method('getParam')
            ->willReturnMap($paramMap);

        // Expect log to be called with payment ID
        $this
            ->_loggerMock
            ->expects($this->once())
            ->method('info')
            ->with(
                '[Loading] Displaying loading page',
                ['payment_id' => 'pay_test_123']
            );

        // Execute controller
        $result = $this->_controller->execute();

        // Assert result is the page mock
        $this->assertSame($this->_pageMock, $result);
    }
}
