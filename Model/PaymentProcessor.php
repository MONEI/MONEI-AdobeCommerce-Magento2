<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\Data\PaymentErrorCodeInterface;
use Monei\MoneiPayment\Api\Data\PaymentInfoInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Api\PaymentProcessingResultInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Model\Data\PaymentProcessingResult;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Service\Order\CreateVaultPayment;
use Monei\MoneiPayment\Service\InvoiceService;
use Monei\MoneiPayment\Service\Logger;
use Exception;

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
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * @var OrderSender
     */
    private OrderSender $orderSender;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var GetPaymentInterface
     */
    private GetPaymentInterface $getPaymentInterface;

    /**
     * @var OrderFactory
     */
    private OrderFactory $orderFactory;

    /**
     * @var CreateVaultPayment
     */
    private CreateVaultPayment $createVaultPayment;

    /**
     * @var PaymentDTOFactory
     */
    private PaymentDTOFactory $paymentDtoFactory;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceService $invoiceService
     * @param LockManagerInterface $lockManager
     * @param Logger $logger
     * @param MoneiApiClient $moneiApiClient
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param OrderSender $orderSender
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param GetPaymentInterface $getPaymentInterface
     * @param OrderFactory $orderFactory
     * @param CreateVaultPayment $createVaultPayment
     * @param PaymentDTOFactory $paymentDtoFactory
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        InvoiceService $invoiceService,
        LockManagerInterface $lockManager,
        Logger $logger,
        MoneiApiClient $moneiApiClient,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        OrderSender $orderSender,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        GetPaymentInterface $getPaymentInterface,
        OrderFactory $orderFactory,
        CreateVaultPayment $createVaultPayment,
        PaymentDTOFactory $paymentDtoFactory
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->lockManager = $lockManager;
        $this->logger = $logger;
        $this->moneiApiClient = $moneiApiClient;
        $this->moduleConfig = $moduleConfig;
        $this->orderSender = $orderSender;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->getPaymentInterface = $getPaymentInterface;
        $this->orderFactory = $orderFactory;
        $this->createVaultPayment = $createVaultPayment;
        $this->paymentDtoFactory = $paymentDtoFactory;
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
                    PaymentErrorCodeInterface::ERROR_NOT_FOUND,
                    null
                );
            }

            // Create PaymentDTO from payment data
            $payment = $this->paymentDtoFactory->createFromArray($paymentData);

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
                    PaymentErrorCodeInterface::ERROR_PROCESSING_FAILED,
                    null
                );
            }
        } catch (Exception $e) {
            $this->logger->error(sprintf(
                '[Payment] Processing failed for order %s, payment %s: %s',
                $orderId,
                $paymentId,
                $e->getMessage()
            ), ['exception' => $e]);

            return PaymentProcessingResult::createError(
                $orderId,
                $paymentId,
                $e->getMessage(),
                PaymentErrorCodeInterface::ERROR_EXCEPTION,
                null
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function isProcessing(string $orderId, string $paymentId): bool
    {
        return $this->lockManager->isOrderLocked($orderId) || $this->lockManager->isPaymentLocked($orderId, $paymentId);
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
                    '[Payment] Timeout waiting for processing for order %s, payment %s',
                    $orderId,
                    $paymentId
                ));

                return false;
            }

            // Wait a bit before checking again
            usleep(200000);  // 200ms
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getPayment(string $paymentId): array
    {
        try {
            $payment = $this->getPaymentInterface->execute($paymentId);

            return (array) $payment;
        } catch (Exception $e) {
            $this->logger->error(sprintf(
                '[Payment] Error getting payment status for payment %s: %s',
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
                    '[Payment] Lock acquisition failed for order %s, payment %s',
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
                '[Payment] Processing failed for order %s, payment %s: %s',
                $incrementId,
                $paymentId,
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
     * @return bool
     */
    public function processPaymentById(
        OrderInterface $order,
        string $paymentId
    ): bool {
        try {
            // Get payment data using our own getPayment method
            $paymentData = $this->getPayment($paymentId);

            if (isset($paymentData['status']) && $paymentData['status'] === 'ERROR') {
                throw new LocalizedException(__('Failed to fetch payment data: %1', $paymentData['error'] ?? 'Unknown error'));
            }

            $payment = $this->paymentDtoFactory->createFromArray($paymentData);

            // Process the payment
            return $this->processPayment($order, $payment);
        } catch (LocalizedException $e) {
            $this->logger->error(sprintf(
                '[Payment] Processing failed for order %s, payment %s: %s',
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
            '[Payment] Processing order %s, payment %s, status: %s',
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

        // Check if payment is pending
        if ($payment->isPending()) {
            return $this->handlePendingPayment($order, $payment);
        }

        // Check if payment failed
        if ($payment->isFailed() || $payment->isCanceled() || $payment->isExpired()) {
            return $this->handleFailedPayment($order, $payment);
        }

        // Payment is in a pending or unknown state
        $this->logger->info(sprintf(
            '[Payment] Pending state for order %s, payment %s, status: %s',
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
            if ($order->getState() === Order::STATE_PROCESSING) {
                $this->logger->info(sprintf(
                    '[Payment] Order already processed for order %s, payment %s',
                    $incrementId,
                    $paymentId
                ));

                return true;
            }

            // Update payment information first
            $this->updatePaymentInformation($order, $payment);

            $this->logger->info(sprintf(
                '[Payment] Payment information updated for order %s, payment %s',
                $incrementId,
                $paymentId
            ));

            // Before creating a new invoice, check for and mark existing history entries
            $this->markExistingCaptureHistoryAsNotified($order);

            // Generate invoice
            $invoice = $this->invoiceService->processInvoice($order, $paymentId);

            // Update order status
            $order->setState(Order::STATE_PROCESSING);
            $orderStatus = $this->moduleConfig->getConfirmedStatus($order->getStoreId());
            $order->setStatus($orderStatus);

            // Set the flag to allow sending the email now
            $order->setCanSendNewEmailFlag(true);

            // Send order email if it hasn't been sent yet
            if (!$order->getEmailSent()) {
                try {
                    if ($this->moduleConfig->shouldSendOrderEmail($order->getStoreId())) {
                        $this->logger->debug('[Payment] Sending order email');
                        $this->orderSender->send($order);
                    }
                } catch (Exception $e) {
                    $this->logger->critical('[Payment] Email sending error: ' . $e->getMessage());
                }
            }

            // After saving the order, check for newly added history entries related to capture
            $this->orderRepository->save($order);
            $this->markExistingCaptureHistoryAsNotified($order);

            $this->logger->info(sprintf(
                '[Payment] Payment successful for order %s, payment %s',
                $incrementId,
                $paymentId
            ));

            return true;
        } catch (Exception $e) {
            $this->logger->error(sprintf(
                '[Payment] Error processing successful payment for order %s, payment %s: %s',
                $incrementId,
                $paymentId,
                $e->getMessage()
            ));

            return false;
        }
    }

    /**
     * Mark existing "Captured amount" history entries as notified
     *
     * @param OrderInterface $order
     * @return void
     */
    private function markExistingCaptureHistoryAsNotified(OrderInterface $order): void
    {
        try {
            // Get all status history entries
            $historyEntries = $order->getStatusHistories();
            if (!$historyEntries) {
                return;
            }

            $updated = false;

            // Find and update invoice and capture related entries
            foreach ($historyEntries as $history) {
                if (!$history->getIsCustomerNotified() && $history->getComment()) {
                    $comment = $history->getComment();
                    // Check for various capture-related texts
                    if (stripos($comment, 'Captured amount') !== false ||
                            stripos($comment, 'Invoice') !== false ||
                            stripos($comment, 'Capture') !== false) {
                        $history->setIsCustomerNotified(true);
                        $this->logger->debug(sprintf(
                            '[Payment] Order history entry marked as notified for order %s, Comment: %s',
                            $order->getIncrementId(),
                            $comment
                        ));
                        $updated = true;
                    }
                }
            }

            // Only save if we actually updated an entry
            if ($updated) {
                $this->orderRepository->save($order);
            }
        } catch (Exception $e) {
            $this->logger->error(
                sprintf(
                    '[Payment] Error marking history as notified: %s',
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
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
            if ($order->getState() === Order::STATE_PROCESSING) {
                $this->logger->info(sprintf(
                    '[Payment] Order already processed for order %s, payment %s',
                    $incrementId,
                    $paymentId
                ));

                return true;
            }

            // Update payment information
            $this->updatePaymentInformation($order, $payment);

            // Before updating order status, check for and mark existing history entries
            $this->markExistingCaptureHistoryAsNotified($order);

            // No longer creating pending invoice for authorized payment
            // The merchant will create the invoice manually, which will be automatically captured

            // Update order status
            $order->setState(Order::STATE_PROCESSING);
            $orderStatus = $this->moduleConfig->getPreAuthorizedStatus($order->getStoreId());
            $order->setStatus($orderStatus);

            // Set the flag to allow sending the email now
            $order->setCanSendNewEmailFlag(true);

            // Send order email if it hasn't been sent yet
            if (!$order->getEmailSent()) {
                try {
                    if ($this->moduleConfig->shouldSendOrderEmail($order->getStoreId())) {
                        $this->logger->debug('[Payment] Sending order email for authorized payment');
                        $this->orderSender->send($order);
                    }
                } catch (Exception $e) {
                    $this->logger->critical('[Payment] Email sending error for authorized payment: ' . $e->getMessage());
                }
            }

            // After saving the order, check for newly added history entries
            $this->orderRepository->save($order);
            $this->markExistingCaptureHistoryAsNotified($order);

            $this->logger->info(sprintf(
                '[Payment] Payment authorized for order %s, payment %s',
                $incrementId,
                $paymentId
            ));

            return true;
        } catch (Exception $e) {
            $this->logger->error(sprintf(
                '[Payment] Error processing authorized payment for order %s, payment %s: %s',
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
        $errorCode = $payment->getStatusCode();
        $errorMessage = $payment->getStatusMessage();

        try {
            // Check if order is already canceled
            if ($order->getState() === Order::STATE_CANCELED) {
                $this->logger->info(sprintf(
                    '[Payment] Order already canceled for order %s, payment %s',
                    $incrementId,
                    $paymentId
                ));

                return true;
            }

            // Update payment information
            $this->updatePaymentInformation($order, $payment);

            // Build cancellation comment with error details if available
            $comment = __('Payment error');
            if ($errorCode && $errorMessage) {
                $comment = __('Payment error with code %1: %2', $errorCode, $errorMessage);
            } elseif ($payment->isCanceled()) {
                $comment = __('Payment was canceled by the customer or the payment processor');
            } elseif ($payment->isExpired()) {
                $comment = __('Payment expired');
            }

            // Cancel the order
            $state = $order->getState();
            $allowedStatesToCancel = [
                Order::STATE_NEW,
                Order::STATE_PROCESSING,
            ];

            if (in_array($state, $allowedStatesToCancel)) {
                $order->setState(Order::STATE_CANCELED);
                $order->setStatus(Order::STATE_CANCELED);
                $order->addCommentToStatusHistory($comment);
                $this->orderRepository->save($order);

                $this->logger->info(sprintf(
                    '[Payment] Order canceled for order %s, payment %s, error_code: %s',
                    $incrementId,
                    $paymentId,
                    $errorCode ?? 'n/a'
                ));
            } else {
                $this->logger->warning(sprintf(
                    '[Payment] Order could not be canceled for order %s, payment %s, state: %s, error_code: %s',
                    $incrementId,
                    $paymentId,
                    $order->getState(),
                    $errorCode ?? 'n/a'
                ));
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error(sprintf(
                '[Payment] Error handling failed payment for order %s, payment %s, error_code: %s, exception: %s',
                $incrementId,
                $paymentId,
                $errorCode ?? 'n/a',
                $e->getMessage()
            ));

            return false;
        }
    }

    /**
     * Handle a pending payment
     *
     * @param OrderInterface $order
     * @param PaymentDTO $payment
     * @return bool
     */
    private function handlePendingPayment(OrderInterface $order, PaymentDTO $payment): bool
    {
        $incrementId = $order->getIncrementId();
        $paymentId = $payment->getId();

        try {
            // Check if order is already processed
            if ($order->getState() === Order::STATE_PROCESSING) {
                $this->logger->info(sprintf(
                    '[Payment] Order already processed for order %s, payment %s',
                    $incrementId,
                    $paymentId
                ));

                return true;
            }

            // Update payment information
            $this->updatePaymentInformation($order, $payment);

            // Before updating order status, check for and mark existing history entries
            $this->markExistingCaptureHistoryAsNotified($order);

            // Update order status similar to authorized payment
            $order->setState(Order::STATE_PROCESSING);
            $orderStatus = $this->moduleConfig->getPreAuthorizedStatus($order->getStoreId());
            $order->setStatus($orderStatus);

            // Set the flag to allow sending the email now
            $order->setCanSendNewEmailFlag(true);

            // Send order email if it hasn't been sent yet
            if (!$order->getEmailSent()) {
                try {
                    if ($this->moduleConfig->shouldSendOrderEmail($order->getStoreId())) {
                        $this->logger->debug('[Payment] Sending order email for pending payment');
                        $this->orderSender->send($order);
                    }
                } catch (Exception $e) {
                    $this->logger->critical('[Payment] Email sending error for pending payment: ' . $e->getMessage());
                }
            }

            // After saving the order, check for newly added history entries
            $this->orderRepository->save($order);
            $this->markExistingCaptureHistoryAsNotified($order);

            $this->logger->info(sprintf(
                '[Payment] Payment pending for order %s, payment %s',
                $incrementId,
                $paymentId
            ));

            return true;
        } catch (Exception $e) {
            $this->logger->error(sprintf(
                '[Payment] Error processing pending payment for order %s, payment %s: %s',
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
            $orderPayment->setAdditionalInformation(PaymentInfoInterface::PAYMENT_ID, $payment->getId());
            $orderPayment->setAdditionalInformation(PaymentInfoInterface::PAYMENT_STATUS, $payment->getStatus());
            $orderPayment->setAdditionalInformation(PaymentInfoInterface::PAYMENT_AMOUNT, $payment->getAmount());
            $orderPayment->setAdditionalInformation(PaymentInfoInterface::PAYMENT_CURRENCY, $payment->getCurrency());
            $orderPayment->setAdditionalInformation(PaymentInfoInterface::PAYMENT_UPDATED_AT, $payment->getUpdatedAt());

            // Store status code information if available
            if ($payment->getStatusCode()) {
                $orderPayment->setAdditionalInformation(PaymentInfoInterface::PAYMENT_ERROR_CODE, $payment->getStatusCode());
                $statusMessage = $payment->getStatusMessage();
                if ($statusMessage) {
                    $orderPayment->setAdditionalInformation(PaymentInfoInterface::PAYMENT_ERROR_MESSAGE, $statusMessage);
                }
            }

            if ($payment->isAuthorized()) {
                $orderPayment->setAdditionalInformation(PaymentInfoInterface::PAYMENT_IS_AUTHORIZED, true);
            }

            if ($payment->isSucceeded()) {
                $orderPayment->setAdditionalInformation(PaymentInfoInterface::PAYMENT_IS_CAPTURED, true);
            }

            // Check if we need to tokenize the payment
            if ($order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION)) {
                $vaultCreated = $this->createVaultPayment->execute(
                    $payment->getId(),
                    $orderPayment
                );
                $order->setData(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION, $vaultCreated);
                $this->logger->debug('[Payment] Payment token creation ' . ($vaultCreated ? 'successful' : 'failed'));
            }

            // Also set payment ID directly on the order to maintain compatibility with Block/Info/Monei.php
            $order->setData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $payment->getId());
        }
    }

    /**
     * Get order by increment ID or entity ID
     *
     * @param string $orderId
     * @return OrderInterface|null
     */
    private function getOrderByIncrementId(string $orderId): ?OrderInterface
    {
        try {
            $this->logger->debug(sprintf(
                '[Order lookup] Searching for order with ID: %s',
                $orderId
            ));

            // First try to load by increment ID using OrderFactory (similar to main branch)
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);

            if ($order && $order->getId()) {
                $this->logger->debug(sprintf(
                    '[Order found by increment ID] %s (Entity ID: %s)',
                    $orderId,
                    $order->getEntityId()
                ));

                return $order;
            }

            // If still not found, try without leading zeros
            $numericOrderId = ltrim($orderId, '0');
            if ($numericOrderId !== $orderId) {
                $this->logger->debug(sprintf(
                    '[Trying without leading zeros] %s -> %s',
                    $orderId,
                    $numericOrderId
                ));

                $order = $this->orderFactory->create()->loadByIncrementId($numericOrderId);

                if ($order && $order->getId()) {
                    $this->logger->debug(sprintf(
                        '[Order found by numeric increment ID] %s (Entity ID: %s)',
                        $numericOrderId,
                        $order->getEntityId()
                    ));

                    return $order;
                }
            }

            $this->logger->error(sprintf(
                '[Order not found] No order found for ID: %s',
                $orderId
            ));

            return null;
        } catch (Exception $e) {
            $this->logger->error(sprintf(
                '[Error loading order] ID %s: %s',
                $orderId,
                $e->getMessage()
            ), ['exception' => $e]);

            return null;
        }
    }

    /**
     * Get payment data for a specific payment ID
     *
     * @param string $paymentId
     * @return PaymentDTO
     * @throws LocalizedException
     */
    public function getPaymentData(string $paymentId): PaymentDTO
    {
        $paymentData = $this->getPayment($paymentId);

        if (isset($paymentData['status']) && $paymentData['status'] === 'ERROR') {
            throw new LocalizedException(__('Failed to fetch payment data: %1', $paymentData['error'] ?? 'Unknown error'));
        }

        return $this->paymentDtoFactory->createFromArray($paymentData);
    }

    /**
     * Validate payment data
     *
     * @param array $data
     * @return bool
     */
    public function validatePaymentData(array $data): bool
    {
        // Validate that required fields are present
        if (empty($data['id']) || !isset($data['status'])) {
            $this->logger->debug('[Payment data validation failed] Missing required fields', [
                'received_fields' => array_keys($data)
            ]);

            return false;
        }

        return true;
    }
}
