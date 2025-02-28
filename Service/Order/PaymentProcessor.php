<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Api\Service\SetOrderStatusAndStateInterface;
use Monei\MoneiPayment\Model\PendingOrderFactory;
use Monei\MoneiPayment\Model\ResourceModel\PendingOrder as PendingOrderResource;
use Monei\MoneiPayment\Model\Service\ProcessingLock;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Service\Order\CreateVaultPayment;

/**
 * Handles payment processing with concurrency control
 */
class PaymentProcessor
{
    /**
     * @var ProcessingLock
     */
    private $processingLock;
    
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
     * @var SetOrderStatusAndStateInterface
     */
    private $setOrderStatusAndStateService;
    
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
    private $orderSender;
    
    /**
     * @var CreateVaultPayment
     */
    private $createVaultPayment;
    
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param ProcessingLock $processingLock
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderInterfaceFactory $orderFactory
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param GenerateInvoiceInterface $generateInvoiceService
     * @param SetOrderStatusAndStateInterface $setOrderStatusAndStateService
     * @param PendingOrderFactory $pendingOrderFactory
     * @param PendingOrderResource $pendingOrderResource
     * @param OrderSender $orderSender
     * @param CreateVaultPayment $createVaultPayment
     * @param Logger $logger
     */
    public function __construct(
        ProcessingLock $processingLock,
        OrderRepositoryInterface $orderRepository,
        OrderInterfaceFactory $orderFactory,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        GenerateInvoiceInterface $generateInvoiceService,
        SetOrderStatusAndStateInterface $setOrderStatusAndStateService,
        PendingOrderFactory $pendingOrderFactory,
        PendingOrderResource $pendingOrderResource,
        OrderSender $orderSender,
        CreateVaultPayment $createVaultPayment,
        Logger $logger
    ) {
        $this->processingLock = $processingLock;
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->moduleConfig = $moduleConfig;
        $this->generateInvoiceService = $generateInvoiceService;
        $this->setOrderStatusAndStateService = $setOrderStatusAndStateService;
        $this->pendingOrderFactory = $pendingOrderFactory;
        $this->pendingOrderResource = $pendingOrderResource;
        $this->orderSender = $orderSender;
        $this->createVaultPayment = $createVaultPayment;
        $this->logger = $logger;
    }

    /**
     * Process payment data with concurrency control
     *
     * @param array $paymentData
     * @param string $source Controller source ('complete' or 'callback')
     * @return bool
     * @throws LocalizedException
     */
    public function processPayment(array $paymentData, string $source): bool
    {
        if (!isset($paymentData['orderId'], $paymentData['id'], $paymentData['status'])) {
            $this->logger->error('Missing required payment data', $paymentData);
            return false;
        }

        $orderId = $paymentData['orderId'];
        $paymentId = $paymentData['id'];
        $status = $paymentData['status'];
        
        $this->logger->info(sprintf(
            'Processing payment for order %s, payment %s, status %s from %s',
            $orderId,
            $paymentId,
            $status,
            $source
        ));

        // Try to acquire a lock for this order/payment combination
        if (!$this->processingLock->acquireLock($orderId, $paymentId)) {
            $this->logger->info(sprintf(
                'Payment for order %s is already being processed by another request',
                $orderId
            ));
            return false;
        }

        try {
            /** @var Order $order */
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);
            
            if (!$order->getId()) {
                $this->logger->error(sprintf('Order %s not found', $orderId));
                return false;
            }
            
            // Check for idempotency - if this payment has already been processed, don't process it again
            $existingPaymentId = $order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID);
            if ($existingPaymentId === $paymentId) {
                $this->logger->info(sprintf(
                    'Payment %s for order %s has already been processed',
                    $paymentId,
                    $orderId
                ));
                
                // If the existing payment has the same status, we don't need to process it again
                if ($this->hasOrderCorrectStatus($order, $status)) {
                    return true;
                }
            }

            // Process payment based on status
            switch ($status) {
                case Monei::ORDER_STATUS_AUTHORIZED:
                    return $this->processAuthorizedPayment($order, $paymentData);
                
                case Monei::ORDER_STATUS_SUCCEEDED:
                    return $this->processSucceededPayment($order, $paymentData);
                
                default:
                    $this->logger->info(sprintf(
                        'Unhandled payment status %s for order %s',
                        $status,
                        $orderId
                    ));
                    return false;
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error processing payment for order %s: %s',
                $orderId,
                $e->getMessage()
            ));
            return false;
        } finally {
            // Always release the lock, even if an exception occurred
            $this->processingLock->releaseLock($orderId, $paymentId);
        }
    }

    /**
     * Process a payment with AUTHORIZED status
     *
     * @param OrderInterface $order
     * @param array $paymentData
     * @return bool
     */
    private function processAuthorizedPayment(OrderInterface $order, array $paymentData): bool
    {
        // Check if order already has the correct status
        if ($this->hasOrderCorrectStatus($order, Monei::ORDER_STATUS_AUTHORIZED)) {
            return true;
        }
        
        $payment = $order->getPayment();
        if (!$payment) {
            $this->logger->error(sprintf('No payment found for order %s', $order->getIncrementId()));
            return false;
        }
        
        $payment->setLastTransId($paymentData['id']);
        
        if ($order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION)) {
            $vaultCreated = $this->createVaultPayment->execute(
                $paymentData['id'],
                $payment
            );
            $order->setData(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION, $vaultCreated);
        }

        $order->setStatus($this->moduleConfig->getPreAuthorizedStatus())
            ->setState(Order::STATE_PENDING_PAYMENT);
        $order->setData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $paymentData['id']);
        
        try {
            $pendingOrder = $this->pendingOrderFactory->create()->setOrderIncrementId($paymentData['orderId']);
            $this->pendingOrderResource->save($pendingOrder);
            $this->orderRepository->save($order);
            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error saving authorized payment for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Process a payment with SUCCEEDED status
     *
     * @param OrderInterface $order
     * @param array $paymentData
     * @return bool
     */
    private function processSucceededPayment(OrderInterface $order, array $paymentData): bool
    {
        // Check if order already has the correct status
        if ($this->hasOrderCorrectStatus($order, Monei::ORDER_STATUS_SUCCEEDED)) {
            return true;
        }
        
        try {
            // Generate invoice if it doesn't exist
            $this->generateInvoiceService->execute($paymentData);
            
            $order->setStatus($this->moduleConfig->getConfirmedStatus())
                ->setState(Order::STATE_NEW);
            $order->setData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $paymentData['id']);
            $this->orderRepository->save($order);

            // Send order email if not sent yet
            if ($order->getCanSendNewEmailFlag() && !$order->getEmailSent()) {
                try {
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->logger->error(sprintf(
                        'Error sending order email for %s: %s',
                        $order->getIncrementId(),
                        $e->getMessage()
                    ));
                }
            }
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error processing succeeded payment for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Check if the order already has the correct status for the given payment status
     *
     * @param OrderInterface $order
     * @param string $paymentStatus
     * @return bool
     */
    private function hasOrderCorrectStatus(OrderInterface $order, string $paymentStatus): bool
    {
        switch ($paymentStatus) {
            case Monei::ORDER_STATUS_AUTHORIZED:
                return $order->getState() === Order::STATE_PENDING_PAYMENT;
                
            case Monei::ORDER_STATUS_SUCCEEDED:
                return $order->getStatus() === $this->moduleConfig->getConfirmedStatus() &&
                       $order->getState() === Order::STATE_NEW;
                
            default:
                return false;
        }
    }
}
