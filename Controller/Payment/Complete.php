<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Model\PendingOrderFactory;
use Monei\MoneiPayment\Model\ResourceModel\PendingOrder as PendingOrderResource;
use Monei\MoneiPayment\Service\Order\CreateVaultPayment;

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
     * @var OrderSender
     */
    protected $orderSender;
    private CreateVaultPayment $createVaultPayment;

    /**
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderSender $orderSender
     * @param OrderInterfaceFactory $orderFactory
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param GenerateInvoiceInterface $generateInvoiceService
     * @param MagentoRedirect $resultRedirectFactory
     * @param PendingOrderFactory $pendingOrderFactory
     * @param PendingOrderResource $pendingOrderResource
     * @param CreateVaultPayment $createVaultPayment
     */
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        OrderSender $orderSender,
        OrderInterfaceFactory $orderFactory,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        GenerateInvoiceInterface $generateInvoiceService,
        MagentoRedirect $resultRedirectFactory,
        PendingOrderFactory $pendingOrderFactory,
        PendingOrderResource $pendingOrderResource,
        CreateVaultPayment $createVaultPayment
    ) {
        $this->createVaultPayment = $createVaultPayment;
        $this->context = $context;
        $this->orderRepository = $orderRepository;
        $this->orderSender = $orderSender;
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
                if($payment){
                    $payment->setLastTransId($data['id']);
                    if ($order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION)) {
                        $vaultCreated = $this->createVaultPayment->execute(
                            $data['id'],
                            $payment
                        );
                        $order->setData(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION, $vaultCreated);
                    }
                }

                $order->setStatus($this->moduleConfig->getPreAuthorizedStatus())
                    ->setState(Order::STATE_PENDING_PAYMENT);
                $order->setData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $data['id']);
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
                $order->setStatus($this->moduleConfig->getConfirmedStatus())->setState(Order::STATE_NEW);
                $order->setData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $data['id']);
                $this->orderRepository->save($order);

                // send Order email
                if ($order->getCanSendNewEmailFlag() && !$order->getEmailSent()) {
                    try {
                        $this->orderSender->send($order);
                    } catch (\Exception $e) {
                        $this->logger->critical($e);
                    }
                }

                return $this->resultRedirectFactory->setPath('checkout/onepage/success', ['_secure' => true]);

            default:
                return $this->resultRedirectFactory->setPath('checkout/cart', ['_secure' => true]);
        }
    }
}
