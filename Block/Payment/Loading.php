<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\Payment;

use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Element\Template;
use Magento\Framework\UrlInterface;

/**
 * Block class for the payment loading page
 */
class Loading extends Template
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @param Context $context
     * @param UrlInterface $urlBuilder
     * @param array $data
     */
    public function __construct(
        Context $context,
        UrlInterface $urlBuilder,
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $data);
    }

    /**
     * Get payment ID
     *
     * @return string
     */
    public function getPaymentId(): string
    {
        return $this->getRequest()->getParam('payment_id') ?? '';
    }

    /**
     * Get the complete URL for payment status handling
     *
     * @return string
     */
    public function getCompleteUrl(): string
    {
        return $this->urlBuilder->getUrl('monei/payment/complete');
    }

    /**
     * Get order ID
     *
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->getRequest()->getParam('order_id') ?? '';
    }
}
