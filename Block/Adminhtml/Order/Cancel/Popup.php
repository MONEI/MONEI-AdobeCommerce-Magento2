<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\Adminhtml\Order\Cancel;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Template;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Model\Config\Source\CancelReason;

/**
 * Block for Monei cancel order popup.
 */
class Popup extends Template
{
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var CancelReason
     */
    private $source;

    /**
     * Constructor.
     *
     * @param Registry $registry Core registry
     * @param CancelReason $source Cancel reason source model
     * @param Context $context Block context
     * @param array $data Additional data
     * @param JsonHelper|null $jsonHelper JSON helper
     * @param DirectoryHelper|null $directoryHelper Directory helper
     */
    public function __construct(
        Registry $registry,
        CancelReason $source,
        Context $context,
        array $data = [],
        ?JsonHelper $jsonHelper = null,
        ?DirectoryHelper $directoryHelper = null
    ) {
        parent::__construct($context, $data, $jsonHelper, $directoryHelper);
        $this->registry = $registry;
        $this->source = $source;
    }

    /**
     * Check if order was placed using Monei payment method.
     */
    public function isOrderPlacedWithMonei(): bool
    {
        return null !== $this->getOrder()->getData('monei_payment_id');
    }

    /**
     * Retrieve order model object.
     *
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->registry->registry('sales_order');
    }

    /**
     * Retrieve cancel order url.
     */
    public function getCancelUrl(): string
    {
        return $this->_urlBuilder->getUrl('monei/order/cancel');
    }

    /**
     * Retrieve cancel reasons for order.
     */
    public function getCancelReasons(): array
    {
        return $this->source->toOptionArray();
    }
}
