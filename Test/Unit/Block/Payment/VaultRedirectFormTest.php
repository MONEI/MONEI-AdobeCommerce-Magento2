<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Block\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Model\Quote;
use Monei\MoneiPayment\Block\Payment\VaultRedirectForm;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VaultRedirectFormTest extends TestCase
{
    /**
     * @var VaultRedirectForm
     */
    private $vaultRedirectForm;

    /**
     * @var Context|MockObject
     */
    private $contextMock;

    /**
     * @var CheckoutSession|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var Quote|MockObject
     */
    private $quoteMock;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->checkoutSessionMock = $this->createMock(CheckoutSession::class);
        $this->quoteMock = $this->createMock(Quote::class);

        $this->vaultRedirectForm = new VaultRedirectForm(
            $this->contextMock,
            $this->checkoutSessionMock
        );
    }

    /**
     * Test getQuoteId with quote
     */
    public function testGetQuoteIdWithQuote(): void
    {
        $quoteId = '12345';

        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn($quoteId);

        $this
            ->checkoutSessionMock
            ->expects($this->exactly(2))
            ->method('getQuote')
            ->willReturn($this->quoteMock);

        $this->assertEquals($quoteId, $this->vaultRedirectForm->getQuoteId());
    }

    /**
     * Test getQuoteId without quote
     */
    public function testGetQuoteIdWithoutQuote(): void
    {
        $this
            ->checkoutSessionMock
            ->expects($this->once())
            ->method('getQuote')
            ->willReturn(null);

        $this->assertEquals('', $this->vaultRedirectForm->getQuoteId());
    }
}
