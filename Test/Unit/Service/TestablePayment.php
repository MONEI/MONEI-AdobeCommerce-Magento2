<?php

/**
 * MONEI Payment for Magento 2
 *
 * @category  Payment
 * @package   Monei\MoneiPayment
 * @author    MONEI <support@monei.com>
 * @copyright 2023 MONEI
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://monei.com/
 */

namespace Monei\MoneiPayment\Test\Unit\Service;

use Magento\Sales\Model\Order\Payment;

/**
 * Extended Payment class for testing that includes required methods
 *
 * @category  Tests
 * @package   Monei\MoneiPayment\Test\Unit\Service
 * @author    MONEI <support@monei.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://monei.com/
 */
class TestablePayment extends Payment
{
    /**
     * Set created invoice for testing payment-invoice association
     *
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     * @return $this
     */
    public function setCreatedInvoice($invoice)
    {
        return $this;
    }
}
