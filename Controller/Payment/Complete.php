<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Monei\Model\PaymentStatus;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Model\PaymentDataProvider\ApiPaymentDataProvider;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Logger;

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
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private $moduleConfig;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var MagentoRedirect
     */
    private $resultRedirectFactory;

    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var PaymentProcessor
     */
    private PaymentProcessor $paymentProcessor;

    /**
     * @var ApiPaymentDataProvider
     */
    private ApiPaymentDataProvider $apiPaymentDataProvider;

    /**
     * @var LockManagerInterface
     */
    private LockManagerInterface $lockManager;

    /**
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderSender $orderSender
     * @param OrderFactory $orderFactory
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param MagentoRedirect $resultRedirectFactory
     * @param PaymentProcessor $paymentProcessor
     * @param ApiPaymentDataProvider $apiPaymentDataProvider
     * @param LockManagerInterface $lockManager
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        OrderSender $orderSender,
        OrderFactory $orderFactory,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        MagentoRedirect $resultRedirectFactory,
        PaymentProcessor $paymentProcessor,
        ApiPaymentDataProvider $apiPaymentDataProvider,
        LockManagerInterface $lockManager,
        Logger $logger
    ) {
        $this->context = $context;
        $this->orderRepository = $orderRepository;
        $this->orderSender = $orderSender;
        $this->orderFactory = $orderFactory;
        $this->moduleConfig = $moduleConfig;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->paymentProcessor = $paymentProcessor;
        $this->apiPaymentDataProvider = $apiPaymentDataProvider;
        $this->lockManager = $lockManager;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $data = $this->context->getRequest()->getParams();
        $this->logger->debug('[Complete controller]');
        $this->logger->debug(sprintf('[Order ID] %s', $data['orderId'] ?? 'unknown'));
        $this->logger->debug(sprintf('[Payment status] %s', $data['status'] ?? 'unknown'));
        $this->logger->debug('[Payment data] ' . json_encode($data, JSON_PRETTY_PRINT));

        // Check if we have the required parameters
        if (!isset($data['orderId']) || !isset($data['id']) || !isset($data['status'])) {
            $this->logger->error('[Missing required parameters]');

            return $this->resultRedirectFactory->setPath('checkout/cart', ['_secure' => true]);
        }

        $orderId = $data['orderId'];
        $paymentId = $data['id'];

        // Load the order
        /** @var Order $order */
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (!$order->getId()) {
            $this->logger->error(sprintf('[Order not found] %s', $orderId));

            return $this->resultRedirectFactory->setPath('checkout/cart', ['_secure' => true]);
        }

        // Check if payment is already being processed by the webhook
        if ($this->lockManager->isPaymentLocked($orderId, $paymentId)) {
            $this->logger->info(sprintf(
                '[Payment already being processed] Order %s, payment %s - waiting for completion',
                $orderId,
                $paymentId
            ));

            // Wait for processing to complete (max 5 seconds)
            $maxWaitTime = 5;  // seconds
            $unlocked = $this->lockManager->waitForPaymentUnlock($orderId, $paymentId, $maxWaitTime);

            if (!$unlocked) {
                $this->logger->warning(sprintf(
                    '[Timeout waiting for payment processing] Order %s, payment %s',
                    $orderId,
                    $paymentId
                ));
                // Continue anyway - let's see the current order state
            } else {
                $this->logger->info(sprintf(
                    '[Payment processing completed by webhook] Order %s, payment %s',
                    $orderId,
                    $paymentId
                ));
                // Refresh order data
                $order = $this->orderFactory->create()->loadByIncrementId($orderId);
            }
        } else {
            // Process the payment
            try {
                // Create a PaymentDTO from the redirect data
                $paymentDTO = PaymentDTO::fromArray($data);

                // Process the payment
                $this->paymentProcessor->processPayment($order, $paymentDTO);

                // Email is now sent from the PaymentProcessor
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    '[Error processing payment] Order %s, payment %s: %s',
                    $orderId,
                    $paymentId,
                    $e->getMessage()
                ));
            }
        }

        // Determine redirect based on payment status
        if ($data['status'] === PaymentStatus::SUCCEEDED || $data['status'] === PaymentStatus::AUTHORIZED) {
            return $this->resultRedirectFactory->setPath('checkout/onepage/success', ['_secure' => true]);
        } else {
            return $this->resultRedirectFactory->setPath('checkout/cart', ['_secure' => true]);
        }
    }
}
