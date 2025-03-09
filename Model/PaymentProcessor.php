<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Api\PaymentDataProviderInterface;
use Monei\MoneiPayment\Api\PaymentProcessingResultInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Data\PaymentProcessingResult;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Service\InvoiceService;
use Monei\MoneiPayment\Service\Logger;

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
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceService $invoiceService
     * @param LockManagerInterface $lockManager
     * @param Logger $logger
     * @param MoneiApiClient $moneiApiClient
     * @param PaymentDataProviderInterface $apiPaymentDataProvider
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param OrderSender $orderSender
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param GetPaymentInterface $getPaymentInterface
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        InvoiceService $invoiceService,
        LockManagerInterface $lockManager,
        Logger $logger,
        MoneiApiClient $moneiApiClient,
        PaymentDataProviderInterface $apiPaymentDataProvider,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        OrderSender $orderSender,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        GetPaymentInterface $getPaymentInterface
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->lockManager = $lockManager;
        $this->logger = $logger;
        $this->moneiApiClient = $moneiApiClient;
        $this->apiPaymentDataProvider = $apiPaymentDataProvider;
        $this->moduleConfig = $moduleConfig;
        $this->orderSender = $orderSender;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->getPaymentInterface = $getPaymentInterface;
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
                    null
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
                    null
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
                    '[Timeout waiting for processing] Order %s, payment %s',
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
    public function getPaymentStatus(string $paymentId): array
    {
        try {
            $payment = $this->getPaymentInterface->execute($paymentId);

            return (array)$payment;
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

            // Send order email if it hasn't been sent yet
            if (!$order->getEmailSent()) {
                try {
                    $this->logger->debug('[Sending order email]');
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->logger->critical('[Email sending error] ' . $e->getMessage());
                }
            }

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
            if ($order->getState() === Order::STATE_CANCELED) {
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
            $state = $order->getState();
            $allowedStatesToCancel = [
                Order::STATE_NEW,
                Order::STATE_PROCESSING,
            ];

            if (in_array($state, $allowedStatesToCancel)) {
                $order->setState(Order::STATE_CANCELED);
                $order->setStatus(Order::STATE_CANCELED);
                $this->orderRepository->save($order);

                $this->logger->info(sprintf(
                    '[Order canceled] Order %s, payment %s',
                    $incrementId,
                    $paymentId
                ));
            } else {
                $this->logger->warning(sprintf(
                    '[Order could not be canceled] Order %s, payment %s, state: %s',
                    $incrementId,
                    $paymentId,
                    $order->getState()
                ));
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error handling failed payment] Order %s, payment %s: %s',
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
     * Get order by increment ID or entity ID
     *
     * @param string $orderId
     * @return OrderInterface|null
     */
    private function getOrderByIncrementId(string $orderId): ?OrderInterface
    {
        try {
            // First - clear the search criteria builder to avoid any lingering filters
            $this->searchCriteriaBuilder->create();

            // Log the order ID we're looking for
            $this->logger->debug(sprintf(
                '[Order lookup] Searching for order with ID: %s',
                $orderId
            ));

            // DIAGNOSTIC: Get a sample of recent orders to see what's in the database
            $recentOrdersCriteria = $this->searchCriteriaBuilder
                ->setPageSize(10)
                ->setCurrentPage(1)
                ->addSortOrder('entity_id', 'DESC')
                ->create();

            $recentOrders = $this->orderRepository->getList($recentOrdersCriteria)->getItems();
            $recentOrderInfo = [];
            foreach ($recentOrders as $order) {
                $recentOrderInfo[] = [
                    'entity_id' => $order->getEntityId(),
                    'increment_id' => $order->getIncrementId(),
                    'store_id' => $order->getStoreId()
                ];
            }

            $this->logger->debug(
                '[DIAGNOSTIC] Recent orders in system',
                ['recent_orders' => $recentOrderInfo]
            );

            // Reset the search criteria builder after diagnostic
            $this->searchCriteriaBuilder->create();

            // First try with direct search criteria based on increment_id
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $orderId, 'eq')
                ->create();

            $orderList = $this->orderRepository->getList($searchCriteria);
            $orders = $orderList->getItems();

            if (count($orders) > 0) {
                $order = reset($orders);
                $this->logger->debug(sprintf(
                    '[Order found by increment ID] %s (Entity ID: %s)',
                    $orderId,
                    $order->getEntityId()
                ));

                return $order;
            }

            // Try searching across all store views
            $this->logger->debug('[Order not found in first attempt] Trying to search across all stores');

            // Reset the search criteria and try looking for any similar ID across all stores
            $this->searchCriteriaBuilder->create();
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $orderId, 'eq')
                ->create();

            $orderList = $this->orderRepository->getList($searchCriteria);
            $orders = $orderList->getItems();

            if (count($orders) > 0) {
                $order = reset($orders);
                $this->logger->debug(sprintf(
                    '[Order found across stores] %s (Entity ID: %s, Store ID: %s)',
                    $orderId,
                    $order->getEntityId(),
                    $order->getStoreId()
                ));

                return $order;
            }

            // If not found, try with a direct load by ID in case it's an entity ID
            try {
                $order = $this->orderRepository->get($orderId);
                if ($order && $order->getEntityId()) {
                    $this->logger->debug(sprintf(
                        '[Order found directly] Entity ID %s',
                        $orderId
                    ));

                    return $order;
                }
            } catch (\Exception $e) {
                // If loading by entity ID failed, continue with other search attempts
                $this->logger->debug(sprintf(
                    '[Not an entity ID] %s: %s',
                    $orderId,
                    $e->getMessage()
                ));
            }

            // Try for potential leading zeros trimming by loading orders with similar pattern
            // Sometimes Magento might treat numeric strings differently than expected
            $numericOrderId = ltrim($orderId, '0');
            if ($numericOrderId !== $orderId) {
                $this->logger->debug(sprintf(
                    '[Trying without leading zeros] %s -> %s',
                    $orderId,
                    $numericOrderId
                ));

                $searchCriteria = $this->searchCriteriaBuilder
                    ->addFilter('increment_id', $numericOrderId, 'eq')
                    ->create();

                $orderList = $this->orderRepository->getList($searchCriteria);
                $orders = $orderList->getItems();

                if (count($orders) > 0) {
                    $order = reset($orders);
                    $this->logger->debug(sprintf(
                        '[Order found by numeric increment ID] %s (Entity ID: %s)',
                        $numericOrderId,
                        $order->getEntityId()
                    ));

                    return $order;
                }
            }

            // Try padded order ID if the numeric version might have lost leading zeros
            // If MONEI is adding leading zeros that Magento doesn't store
            $paddedIds = [];
            // Try with various padding lengths (up to 9 leading zeros)
            for ($i = 1; $i <= 9; $i++) {
                $paddedId = str_pad($numericOrderId, $i, '0', STR_PAD_LEFT);
                if ($paddedId !== $orderId && $paddedId !== $numericOrderId) {
                    $paddedIds[] = $paddedId;
                }
            }

            if (!empty($paddedIds)) {
                $this->logger->debug(sprintf(
                    '[Trying with different zero padding] Original: %s, Trying: %s',
                    $orderId,
                    implode(', ', $paddedIds)
                ));

                // Try each variant
                foreach ($paddedIds as $paddedId) {
                    $this->searchCriteriaBuilder->create();
                    $searchCriteria = $this->searchCriteriaBuilder
                        ->addFilter('increment_id', $paddedId, 'eq')
                        ->create();

                    $orderList = $this->orderRepository->getList($searchCriteria);
                    $orders = $orderList->getItems();

                    if (count($orders) > 0) {
                        $order = reset($orders);
                        $this->logger->debug(sprintf(
                            '[Order found with different padding] Sent: %s, Found: %s (Entity ID: %s)',
                            $orderId,
                            $paddedId,
                            $order->getEntityId()
                        ));

                        return $order;
                    }
                }
            }

            // Log more detailed information about the missing order
            $this->logger->warning(sprintf(
                '[Order not found] No order found for ID: %s. Check if the order exists in the database and can be accessed.',
                $orderId
            ));

            // Try to get all orders with similar ID pattern to help diagnose
            $partialSearchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', substr($orderId, 0, 5) . '%', 'like')
                ->setPageSize(5)
                ->create();

            $similarOrders = $this->orderRepository->getList($partialSearchCriteria)->getItems();
            if (count($similarOrders) > 0) {
                $similarOrderIds = array_map(function ($order) {
                    return $order->getIncrementId();
                }, $similarOrders);

                $this->logger->debug(sprintf(
                    '[Similar orders found] Found %d orders with similar prefix: %s',
                    count($similarOrderIds),
                    implode(', ', $similarOrderIds)
                ));
            } else {
                $this->logger->debug('[No similar orders found] No orders with similar ID pattern exist');
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error loading order] ID %s: %s',
                $orderId,
                $e->getMessage()
            ), ['exception' => $e]);

            return null;
        }
    }
}
