<?php

namespace Monei\MoneiPayment\Test\Unit\Service;

use Magento\Sales\Model\Order\Invoice;

/**
 * Extended invoice class for testing that includes required methods
 */
class TestableInvoice extends Invoice
{
    /**
     * @param string $mode
     * @return $this
     */
    public function setRequestedCaptureCase($mode)
    {
        return $this;
    }
}
