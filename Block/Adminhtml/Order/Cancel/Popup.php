<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\Adminhtml\Order\Cancel;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Monei\MoneiPayment\Model\Config\Source\CancelReason;

/**
 * Block for Monei cancel order popup
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
     * @param Registry             $registry
     * @param CancelReason         $source
     * @param Context              $context
     * @param array                $data
     * @param JsonHelper|null      $jsonHelper
     * @param DirectoryHelper|null $directoryHelper
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
     * Check if order was placed using Monei payment method
     *
     * @return bool
     */
    public function isOrderPlacedWithMonei(): bool
    {
        return $this->getOrder()->getData('monei_payment_id') !== null;
    }

    /**
     * Retrieve order model object
     *
     * @return OrderInterface
     */
    public function getOrder(): OrderInterface
    {
        return $this->registry->registry('sales_order');
    }

    /**
     * Retrieve cancel order url
     *
     * @return string
     */
    public function getCancelUrl(): string
    {
        return $this->_urlBuilder->getUrl('monei/order/cancel');
    }

    /**
     * Retrieve cancel reasons for order
     *
     * @return array
     */
    public function getCancelReasons(): array
    {
        return $this->source->toOptionArray();
    }
}
