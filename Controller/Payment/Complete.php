<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Monei\MoneiPayment\Model\Payment\Monei;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Model\PendingOrderFactory;
use Monei\MoneiPayment\Model\ResourceModel\PendingOrder as PendingOrderResource;

/**
 * Monei payment complete controller
 */
class Complete implements ActionInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderInterfaceFactory
     */
    private $orderFactory;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private $moduleConfig;

    /**
     * @var GenerateInvoiceInterface
     */
    private $generateInvoiceService;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var MagentoRedirect
     */
    private $resultRedirectFactory;

    /**
     * @var PendingOrderFactory
     */
    private $pendingOrderFactory;

    /**
     * @var PendingOrderResource
     */
    private $pendingOrderResource;

    /**
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderInterfaceFactory $orderFactory
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param GenerateInvoiceInterface $generateInvoiceService
     * @param MagentoRedirect $resultRedirectFactory
     */
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        OrderInterfaceFactory $orderFactory,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        GenerateInvoiceInterface $generateInvoiceService,
        MagentoRedirect $resultRedirectFactory,
        PendingOrderFactory $pendingOrderFactory,
        PendingOrderResource $pendingOrderResource
    ) {
        $this->context = $context;
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->moduleConfig = $moduleConfig;
        $this->generateInvoiceService = $generateInvoiceService;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->pendingOrderFactory = $pendingOrderFactory;
        $this->pendingOrderResource = $pendingOrderResource;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $data = $this->context->getRequest()->getParams();
        switch ($data['status']) {
            case Monei::ORDER_STATUS_AUTHORIZED:
                /**
                 * @var $order OrderInterface
                 */
                $order = $this->orderFactory->create()->loadByIncrementId($data['orderId']);
                $payment = $order->getPayment();
                $payment->setLastTransId($data['id']);
                $order->setStatus($this->moduleConfig->getPreAuthorizedStatus())
                    ->setState(Order::STATE_PENDING_PAYMENT);
                $order->setData('monei_payment_id', $data['id']);
                $pendingOrder = $this->pendingOrderFactory->create()->setOrderIncrementId($data['orderId']);
                $this->pendingOrderResource->save($pendingOrder);
                $this->orderRepository->save($order);
                return $this->resultRedirectFactory->setPath('checkout/onepage/success', ['_secure' => true]);

            case Monei::ORDER_STATUS_SUCCEEDED:
                $this->generateInvoiceService->execute($data);
                /**
                 * @var $order OrderInterface
                 */
                $order = $this->orderFactory->create()->loadByIncrementId($data['orderId']);
                $payment = $order->getPayment();
                $payment->setLastTransId($data['id']);
                $order->setStatus($this->moduleConfig->getConfirmedStatus())->setState(Order::STATE_PROCESSING);
                $order->setData('monei_payment_id', $data['id']);
                $this->orderRepository->save($order);
                return $this->resultRedirectFactory->setPath('checkout/onepage/success', ['_secure' => true]);

            default:
                return $this->resultRedirectFactory->setPath('checkout/cart', ['_secure' => true]);
        }
    }
}
