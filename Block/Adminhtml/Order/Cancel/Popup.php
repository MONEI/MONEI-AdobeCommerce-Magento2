<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\Adminhtml\Order\Cancel;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Template;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Model\Config\Source\CancelReason;

/**
 * Block for Monei cancel order popup.
 */
class Popup extends Template
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CancelReason
     */
    private $source;

    /**
     * @var OrderInterface|null
     */
    private $currentOrder;

    /**
     * Constructor.
     *
     * @param OrderRepositoryInterface $orderRepository Order repository
     * @param CancelReason $source Cancel reason source model
     * @param Context $context Block context
     * @param array $data Additional data
     * @param \Magento\Framework\Json\Helper\Data|null $serializer JSON serializer
     * @param DirectoryHelper|null $directoryHelper Directory helper
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CancelReason $source,
        Context $context,
        array $data = [],
        ?\Magento\Framework\Json\Helper\Data $serializer = null,
        ?DirectoryHelper $directoryHelper = null
    ) {
        $this->orderRepository = $orderRepository;
        $this->source = $source;
        parent::__construct($context, $data, $serializer, $directoryHelper);
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
     * @return OrderInterface
     */
    public function getOrder(): OrderInterface
    {
        if ($this->currentOrder === null) {
            $orderId = $this->getRequest()->getParam('order_id');
            if ($orderId) {
                $this->currentOrder = $this->orderRepository->get($orderId);
            }
        }
        return $this->currentOrder;
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
