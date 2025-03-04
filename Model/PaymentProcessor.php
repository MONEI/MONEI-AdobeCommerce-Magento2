<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Api\PaymentDataProviderInterface;
use Monei\MoneiPayment\Api\PaymentProcessingResultInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Data\PaymentProcessingResult;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Service\InvoiceService;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;

/**
 * Processes payments and updates order status
 */
class PaymentProcessor implements PaymentProcessorInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var InvoiceService
     */
    private InvoiceService $invoiceService;

    /**
     * @var LockManagerInterface
     */
    private LockManagerInterface $lockManager;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $moneiApiClient;

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
     * @param InvoiceService $invoiceService
     * @param LockManagerInterface $lockManager
     * @param Logger $logger
     * @param MoneiApiClient $moneiApiClient
     * @param PaymentDataProviderInterface $apiPaymentDataProvider
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        InvoiceService $invoiceService,
        LockManagerInterface $lockManager,
        Logger $logger,
        MoneiApiClient $moneiApiClient,
        PaymentDataProviderInterface $apiPaymentDataProvider,
        MoneiPaymentModuleConfigInterface $moduleConfig
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->lockManager = $lockManager;
        $this->logger = $logger;
        $this->moneiApiClient = $moneiApiClient;
        $this->apiPaymentDataProvider = $apiPaymentDataProvider;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * @inheritdoc
     */
    public function process(string $orderId, string $paymentId, array $paymentData): PaymentProcessingResultInterface
    {
        try {
            // Load the order
            $order = $this->getOrderByIncrementId($orderId);
            if (!$order) {
                return PaymentProcessingResult::createError(
                    $orderId,
                    $paymentId,
                    'Order not found',
                    'not_found',
                    ['order_id' => $orderId]
                );
            }

            // Create PaymentDTO from payment data
            $payment = PaymentDTO::fromArray($paymentData);

            // Process the payment with locking
            $result = $this->processPayment($order, $payment);

            if ($result) {
                return PaymentProcessingResult::createSuccess(
                    $orderId,
                    $paymentId,
                    $payment->getStatus()
                );
            } else {
                return PaymentProcessingResult::createError(
                    $orderId,
                    $paymentId,
                    'Payment processing failed',
                    'processing_failed',
                    ['payment_status' => $payment->getStatus()]
                );
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Payment processing failed] Order %s, payment %s: %s',
                $orderId,
                $paymentId,
                $e->getMessage()
            ), ['exception' => $e]);

            return PaymentProcessingResult::createError(
                $orderId,
                $paymentId,
                $e->getMessage(),
                'exception',
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function isProcessing(string $orderId, string $paymentId): bool
    {
        return $this->lockManager->isOrderLocked($orderId) || $this->lockManager->isPaymentLocked($paymentId);
    }

    /**
     * @inheritdoc
     */
    public function waitForProcessing(string $orderId, string $paymentId, int $timeout = 15): bool
    {
        $startTime = time();
        while ($this->isProcessing($orderId, $paymentId)) {
            // Check if timeout has been reached
            if (time() - $startTime > $timeout) {
                $this->logger->warning(sprintf(
                    '[Timeout waiting for processing] Order %s, payment %s',
                    $orderId,
                    $paymentId
                ));
                return false;
            }

            // Wait a bit before checking again
            usleep(200000); // 200ms
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentStatus(string $paymentId): array
    {
        try {
            return $this->moneiApiClient->getPayment($paymentId);
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error getting payment status] Payment %s: %s',
                $paymentId,
                $e->getMessage()
            ), ['exception' => $e]);

            return ['status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }

    /**
     * Process a payment with locking to prevent race conditions
     *
     * @param OrderInterface $order
     * @param PaymentDTO $payment
     * @return bool
     */
    public function processPayment(OrderInterface $order, PaymentDTO $payment): bool
    {
        $orderId = $order->getEntityId();
        $incrementId = $order->getIncrementId();
        $paymentId = $payment->getId();

        try {
            // Acquire lock for the order
            $lockAcquired = $this->lockManager->lockOrder($incrementId);
            if (!$lockAcquired) {
                $this->logger->warning(sprintf(
                    '[Lock acquisition failed] Order %s, payment %s',
                    $incrementId,
                    $paymentId
                ));
                return false;
            }

            try {
                return $this->doProcessPayment($order, $payment, $incrementId);
            } finally {
                // Always release the lock
                $this->lockManager->unlockOrder($incrementId);
            }
        } catch (LocalizedException $e) {
            $this->logger->error(sprintf(
                '[Payment processing failed] Order %s, payment %s: %s',
                $incrementId,
                $paymentId,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Process a payment from webhook data
     *
     * @param OrderInterface $order
     * @param array $webhookData
     * @param PaymentDataProviderInterface $paymentDataProvider
     * @return bool
     */
    public function processPaymentFromWebhook(
        OrderInterface $order,
        array $webhookData,
        PaymentDataProviderInterface $paymentDataProvider
    ): bool {
        try {
            // Validate webhook data
            if (!$paymentDataProvider->validatePaymentData($webhookData)) {
                $this->logger->error(sprintf(
                    '[Invalid webhook data] Order %s',
                    $order->getIncrementId()
                ));
                return false;
            }

            // Create PaymentDTO from webhook data
            $payment = PaymentDTO::fromArray($webhookData);

            // Process the payment
            return $this->processPayment($order, $payment);
        } catch (LocalizedException $e) {
            $this->logger->error(sprintf(
                '[Webhook processing failed] Order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Process a payment by ID
     *
     * @param OrderInterface $order
     * @param string $paymentId
     * @param PaymentDataProviderInterface $paymentDataProvider
     * @return bool
     */
    public function processPaymentById(
        OrderInterface $order,
        string $paymentId,
        PaymentDataProviderInterface $paymentDataProvider
    ): bool {
        try {
            // Get payment data from provider
            $payment = $paymentDataProvider->getPaymentData($paymentId);

            // Process the payment
            return $this->processPayment($order, $payment);
        } catch (LocalizedException $e) {
            $this->logger->error(sprintf(
                '[Payment processing failed] Order %s, payment %s: %s',
                $order->getIncrementId(),
                $paymentId,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Internal method to process a payment
     *
     * @param OrderInterface $order
     * @param PaymentDTO $payment
     * @param string $incrementId
     * @return bool
     */
    private function doProcessPayment(OrderInterface $order, PaymentDTO $payment, string $incrementId): bool
    {
        $paymentId = $payment->getId();
        $this->logger->info(sprintf(
            '[Processing payment] Order %s, payment %s, status: %s',
            $incrementId,
            $paymentId,
            $payment->getStatus()
        ));

        // Check if payment is successful
        if ($payment->isSucceeded()) {
            return $this->handleSuccessfulPayment($order, $payment);
        }

        // Check if payment is authorized
        if ($payment->isAuthorized()) {
            return $this->handleAuthorizedPayment($order, $payment);
        }

        // Check if payment failed
        if ($payment->isFailed() || $payment->isCanceled() || $payment->isExpired()) {
            return $this->handleFailedPayment($order, $payment);
        }

        // Payment is in a pending or unknown state
        $this->logger->info(sprintf(
            '[Payment in pending state] Order %s, payment %s, status: %s',
            $incrementId,
            $paymentId,
            $payment->getStatus()
        ));

        return true;
    }

    /**
     * Handle a successful payment
     *
     * @param OrderInterface $order
     * @param PaymentDTO $payment
     * @return bool
     */
    private function handleSuccessfulPayment(OrderInterface $order, PaymentDTO $payment): bool
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
                return true;
            }

            // Update payment information
            $this->updatePaymentInformation($order, $payment);

            // Generate invoice
            $this->invoiceService->processInvoice($order, $paymentId);

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

            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error processing successful payment] Order %s, payment %s: %s',
                $incrementId,
                $paymentId,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Handle an authorized payment
     *
     * @param OrderInterface $order
     * @param PaymentDTO $payment
     * @return bool
     */
    private function handleAuthorizedPayment(OrderInterface $order, PaymentDTO $payment): bool
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
                return true;
            }

            // Update payment information
            $this->updatePaymentInformation($order, $payment);

            // Create pending invoice for authorized payment
            $this->invoiceService->createPendingInvoice($order, $paymentId);

            // Update order status
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $orderStatus = $this->moduleConfig->getPreAuthorizedStatus($order->getStoreId());
            $order->setStatus($orderStatus);
            $this->orderRepository->save($order);

            $this->logger->info(sprintf(
                '[Payment authorized] Order %s, payment %s',
                $incrementId,
                $paymentId
            ));

            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error processing authorized payment] Order %s, payment %s: %s',
                $incrementId,
                $paymentId,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Handle a failed payment
     *
     * @param OrderInterface $order
     * @param PaymentDTO $payment
     * @return bool
     */
    private function handleFailedPayment(OrderInterface $order, PaymentDTO $payment): bool
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
                return true;
            }

            // Update payment information
            $this->updatePaymentInformation($order, $payment);

            // Cancel the order
            if ($order->canCancel()) {
                $order->cancel();
                $this->orderRepository->save($order);

                $this->logger->info(sprintf(
                    '[Payment failed, order canceled] Order %s, payment %s, status: %s',
                    $incrementId,
                    $paymentId,
                    $payment->getStatus()
                ));
            } else {
                $this->logger->warning(sprintf(
                    '[Payment failed, but order cannot be canceled] Order %s, payment %s',
                    $incrementId,
                    $paymentId
                ));
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error processing failed payment] Order %s, payment %s: %s',
                $incrementId,
                $paymentId,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Update payment information in the order
     *
     * @param OrderInterface $order
     * @param PaymentDTO $payment
     * @return void
     */
    private function updatePaymentInformation(OrderInterface $order, PaymentDTO $payment): void
    {
        $orderPayment = $order->getPayment();
        if ($orderPayment) {
            $orderPayment->setAdditionalInformation('monei_payment_id', $payment->getId());
            $orderPayment->setAdditionalInformation('monei_payment_status', $payment->getStatus());
            $orderPayment->setAdditionalInformation('monei_payment_amount', $payment->getAmount());
            $orderPayment->setAdditionalInformation('monei_payment_currency', $payment->getCurrency());
            $orderPayment->setAdditionalInformation('monei_payment_updated_at', $payment->getUpdatedAt());

            if ($payment->isAuthorized()) {
                $orderPayment->setAdditionalInformation('monei_is_authorized', true);
            }

            if ($payment->isSucceeded()) {
                $orderPayment->setAdditionalInformation('monei_is_captured', true);
            }
        }
    }

    /**
     * Get order by increment ID
     *
     * @param string $incrementId
     * @return OrderInterface|null
     */
    private function getOrderByIncrementId(string $incrementId): ?OrderInterface
    {
        try {
            $searchCriteria = $this->orderRepository->create()->addFilter('increment_id', $incrementId);
            $orderList = $this->orderRepository->getList($searchCriteria);
            $orders = $orderList->getItems();

            return reset($orders) ?: null;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error loading order] Increment ID %s: %s',
                $incrementId,
                $e->getMessage()
            ));

            return null;
        }
    }
}
