<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Api\PaymentDataProviderInterface;
use Monei\MoneiPayment\Api\PaymentProcessingResultInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Data\PaymentProcessingResult;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;

/**
 * Service for processing MONEI payments
 */
class ProcessPaymentService implements PaymentProcessorInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var LockManagerInterface
     */
    private LockManagerInterface $lockManager;

    /**
     * @var InvoiceService
     */
    private InvoiceService $invoiceService;

    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $apiClient;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var PaymentDataProviderInterface
     */
    private PaymentDataProviderInterface $apiPaymentDataProvider;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param LockManagerInterface $lockManager
     * @param InvoiceService $invoiceService
     * @param MoneiApiClient $apiClient
     * @param Logger $logger
     * @param PaymentDataProviderInterface $apiPaymentDataProvider
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        LockManagerInterface $lockManager,
        InvoiceService $invoiceService,
        MoneiApiClient $apiClient,
        Logger $logger,
        PaymentDataProviderInterface $apiPaymentDataProvider,
        MoneiPaymentModuleConfigInterface $moduleConfig
    ) {
        $this->orderRepository = $orderRepository;
        $this->lockManager = $lockManager;
        $this->invoiceService = $invoiceService;
        $this->apiClient = $apiClient;
        $this->logger = $logger;
        $this->apiPaymentDataProvider = $apiPaymentDataProvider;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * @inheritdoc
     */
    public function process(string $orderId, string $paymentId, array $paymentData): PaymentProcessingResultInterface
    {
        try {
            // Try to acquire a lock for this payment
            if (!$this->lockManager->lockPayment($orderId, $paymentId)) {
                $this->logger->warning(sprintf(
                    '[Payment processing locked] Order %s, payment %s',
                    $orderId,
                    $paymentId
                ));

                return PaymentProcessingResult::createError(
                    $paymentData['status'] ?? 'unknown',
                    $orderId,
                    $paymentId,
                    'Payment is currently being processed by another request'
                );
            }

            try {
                // Load the order
                $order = $this->orderRepository->get($orderId);

                // Create PaymentDTO from data
                $payment = PaymentDTO::fromArray($paymentData);

                // Process based on payment status
                if ($payment->isSucceeded()) {
                    return $this->processSuccessfulPayment($order, $payment);
                } elseif ($payment->isAuthorized()) {
                    return $this->processAuthorizedPayment($order, $payment);
                } elseif ($payment->isFailed() || $payment->isCanceled() || $payment->isExpired()) {
                    return $this->processFailedPayment($order, $payment);
                } else {
                    // Payment is in a pending or unknown state
                    $this->logger->info(sprintf(
                        '[Payment in pending state] Order %s, payment %s, status: %s',
                        $orderId,
                        $paymentId,
                        $payment->getStatus()
                    ));

                    return PaymentProcessingResult::createSuccess(
                        $payment->getStatus(),
                        $orderId,
                        $paymentId
                    );
                }
            } finally {
                // Always release the lock
                $this->lockManager->unlockPayment($orderId, $paymentId);
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Payment processing error] Order %s, payment %s: %s',
                $orderId,
                $paymentId,
                $e->getMessage()
            ));

            return PaymentProcessingResult::createError(
                $paymentData['status'] ?? 'error',
                $orderId,
                $paymentId,
                $e->getMessage()
            );
        }
    }

    /**
     * Process a successful payment
     *
     * @param OrderInterface $order
     * @param PaymentDTO $payment
     * @return PaymentProcessingResultInterface
     */
    private function processSuccessfulPayment(OrderInterface $order, PaymentDTO $payment): PaymentProcessingResultInterface
    {
        $orderId = $order->getEntityId();
        $incrementId = $order->getIncrementId();
        $paymentId = $payment->getId();

        try {
            // Check if order is already processed
            if ($order->getState() === \Magento\Sales\Model\Order::STATE_PROCESSING) {
                $this->logger->info(sprintf(
                    '[Order already processed] Order %s, payment %s',
                    $incrementId,
                    $paymentId
                ));

                return PaymentProcessingResult::createSuccess(
                    $payment->getStatus(),
                    $incrementId,
                    $paymentId
                );
            }

            // Generate invoice
            $invoice = $this->invoiceService->processInvoice($order, $paymentId);

            // Update order status
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $orderStatus = $this->moduleConfig->getConfirmedStatus($order->getStoreId());
            $order->setStatus($orderStatus);
            $this->orderRepository->save($order);

            $this->logger->info(sprintf(
                '[Payment successful] Order %s, payment %s',
                $incrementId,
                $paymentId
            ));

            return PaymentProcessingResult::createSuccess(
                $payment->getStatus(),
                $incrementId,
                $paymentId
            );
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error processing successful payment] Order %s, payment %s: %s',
                $incrementId,
                $paymentId,
                $e->getMessage()
            ));

            return PaymentProcessingResult::createError(
                $payment->getStatus(),
                $incrementId,
                $paymentId,
                $e->getMessage()
            );
        }
    }

    /**
     * Process an authorized payment
     *
     * @param OrderInterface $order
     * @param PaymentDTO $payment
     * @return PaymentProcessingResultInterface
     */
    private function processAuthorizedPayment(OrderInterface $order, PaymentDTO $payment): PaymentProcessingResultInterface
    {
        $incrementId = $order->getIncrementId();
        $paymentId = $payment->getId();

        try {
            // Check if order is already processed
            if ($order->getState() === \Magento\Sales\Model\Order::STATE_PROCESSING) {
                $this->logger->info(sprintf(
                    '[Order already processed] Order %s, payment %s',
                    $incrementId,
                    $paymentId
                ));

                return PaymentProcessingResult::createSuccess(
                    $payment->getStatus(),
                    $incrementId,
                    $paymentId
                );
            }

            // Create pending invoice
            $invoice = $this->invoiceService->createPendingInvoice($order, $paymentId);

            // Update order status to payment review
            $order->setState(\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW);
            $orderStatus = $this->moduleConfig->getPreAuthorizedStatus($order->getStoreId());
            $order->setStatus($orderStatus);
            $this->orderRepository->save($order);

            $this->logger->info(sprintf(
                '[Payment authorized] Order %s, payment %s',
                $incrementId,
                $paymentId
            ));

            return PaymentProcessingResult::createSuccess(
                $payment->getStatus(),
                $incrementId,
                $paymentId
            );
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error processing authorized payment] Order %s, payment %s: %s',
                $incrementId,
                $paymentId,
                $e->getMessage()
            ));

            return PaymentProcessingResult::createError(
                $payment->getStatus(),
                $incrementId,
                $paymentId,
                $e->getMessage()
            );
        }
    }

    /**
     * Process a failed payment
     *
     * @param OrderInterface $order
     * @param PaymentDTO $payment
     * @return PaymentProcessingResultInterface
     */
    private function processFailedPayment(OrderInterface $order, PaymentDTO $payment): PaymentProcessingResultInterface
    {
        $incrementId = $order->getIncrementId();
        $paymentId = $payment->getId();

        try {
            // Check if order is already canceled
            if ($order->getState() === \Magento\Sales\Model\Order::STATE_CANCELED) {
                $this->logger->info(sprintf(
                    '[Order already canceled] Order %s, payment %s',
                    $incrementId,
                    $paymentId
                ));

                return PaymentProcessingResult::createSuccess(
                    $payment->getStatus(),
                    $incrementId,
                    $paymentId
                );
            }

            // Cancel the order
            if ($order->canCancel()) {
                $order->cancel();
                $order->addCommentToStatusHistory(
                    __('MONEI Payment failed. Status: %1', $payment->getStatus())
                );
                $this->orderRepository->save($order);

                $this->logger->info(sprintf(
                    '[Payment failed, order canceled] Order %s, payment %s, status: %s',
                    $incrementId,
                    $paymentId,
                    $payment->getStatus()
                ));
            } else {
                $this->logger->warning(sprintf(
                    '[Payment failed, but order cannot be canceled] Order %s, payment %s, status: %s',
                    $incrementId,
                    $paymentId,
                    $payment->getStatus()
                ));
            }

            return PaymentProcessingResult::createSuccess(
                $payment->getStatus(),
                $incrementId,
                $paymentId
            );
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error processing failed payment] Order %s, payment %s: %s',
                $incrementId,
                $paymentId,
                $e->getMessage()
            ));

            return PaymentProcessingResult::createError(
                $payment->getStatus(),
                $incrementId,
                $paymentId,
                $e->getMessage()
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function isProcessing(string $orderId, string $paymentId): bool
    {
        return $this->lockManager->isPaymentLocked($orderId, $paymentId);
    }

    /**
     * @inheritdoc
     */
    public function waitForProcessing(string $orderId, string $paymentId, int $timeout = 15): bool
    {
        return $this->lockManager->waitForPaymentUnlock($orderId, $paymentId, $timeout);
    }

    /**
     * @inheritdoc
     */
    public function getPaymentStatus(string $paymentId): array
    {
        try {
            $payment = $this->apiPaymentDataProvider->getPaymentData($paymentId);
            return $payment->getRawData();
        } catch (LocalizedException $e) {
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
}
