<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

/**
 * Generate invoice for order service interface.
 */
interface GenerateInvoiceInterface
{
    /**
     * Service execute method.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface|string $order
     * @param array|null $paymentData
     */
    public function execute($order, $paymentData = null): void;
}
