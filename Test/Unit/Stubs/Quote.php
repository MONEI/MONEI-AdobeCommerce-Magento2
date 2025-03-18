<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Stubs;

use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote as MagentoQuote;

/**
 * Stub class for Quote to add methods needed for testing
 */
class Quote extends MagentoQuote
{
    /**
     * Get base grand total
     *
     * @return float
     */
    public function getBaseGrandTotal(): float
    {
        return 0.0;
    }

    /**
     * Get base currency code
     *
     * @return string
     */
    public function getBaseCurrencyCode(): string
    {
        return '';
    }

    /**
     * Get customer ID
     *
     * @return int
     */
    public function getCustomerId(): int
    {
        return 0;
    }

    /**
     * Get billing address
     *
     * @return Address|null
     */
    public function getBillingAddress(): ?Address
    {
        return null;
    }

    /**
     * Get shipping address
     *
     * @return Address|null
     */
    public function getShippingAddress(): ?Address
    {
        return null;
    }

    /**
     * Get ID
     *
     * @return int|string|null
     */
    public function getId()
    {
        return null;
    }

    /**
     * Reserve order ID
     *
     * @return $this
     */
    public function reserveOrderId()
    {
        return $this;
    }

    /**
     * Get reserved order ID
     *
     * @return string|null
     */
    public function getReservedOrderId(): ?string
    {
        return null;
    }

    /**
     * Get data
     *
     * @param string $key
     * @param string|int|null $index
     * @return mixed
     */
    public function getData($key = '', $index = null)
    {
        return null;
    }

    /**
     * Set data
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setData($key, $value = null)
    {
        return $this;
    }
}
