<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\Model\PaymentStatus;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\PaymentDataProviderInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Api\Service\CallbackHelperInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Helper service for processing MONEI payment callbacks
 */
class CallbackHelper implements CallbackHelperInterface
{
    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * @var PaymentDataProviderInterface
     */
    private PaymentDataProviderInterface $callbackPaymentDataProvider;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var PaymentProcessorInterface
     */
    private PaymentProcessorInterface $paymentProcessor;

    /**
     * @var EventManagerInterface
     */
    private EventManagerInterface $eventManager;

    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $apiClient;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @param Logger $logger
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param PaymentDataProviderInterface $callbackPaymentDataProvider
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentProcessorInterface $paymentProcessor
     * @param EventManagerInterface $eventManager
     * @param MoneiApiClient $apiClient
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        Logger $logger,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        PaymentDataProviderInterface $callbackPaymentDataProvider,
        OrderRepositoryInterface $orderRepository,
        PaymentProcessorInterface $paymentProcessor,
        EventManagerInterface $eventManager,
        MoneiApiClient $apiClient,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->logger = $logger;
        $this->moduleConfig = $moduleConfig;
        $this->callbackPaymentDataProvider = $callbackPaymentDataProvider;
        $this->orderRepository = $orderRepository;
        $this->paymentProcessor = $paymentProcessor;
        $this->eventManager = $eventManager;
        $this->apiClient = $apiClient;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Process a payment callback request
     *
     * @param RequestInterface $request
     * @return void
     */
    public function processCallback(RequestInterface $request): void
    {
        try {
            // @phpstan-ignore-next-line
            $payload = (string)$request->getContent();

            // Extract payment data from callback using the provider
            $paymentDTO = $this->callbackPaymentDataProvider->getPaymentData($payload);
            $paymentData = $paymentDTO->getRawData();

            // Dispatch event with the callback data
            $this->dispatchEvent([
                'payment_data' => $paymentData,
                'payment_dto' => $paymentDTO
            ]);

            // Process the payment
            $this->processPaymentFromCallback($paymentDTO);

            $this->logger->debug('[Callback] Payment callback processed successfully');
        } catch (\Exception $e) {
            $this->logger->error('[Callback] Error processing callback: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }

    /**
     * Verify the signature of a callback using MONEI SDK
     *
     * @param string $payload The raw request body
     * @param string $signature The signature from the request header
     * @return bool True if signature is valid, false otherwise
     */
    public function verifyCallbackSignature(string $payload, string $signature): bool
    {
        try {
            $this->logger->debug('[Callback] Verifying signature', [
                'payload_length' => strlen($payload),
                'signature' => $signature
            ]);

            // Use the SDK to verify the signature
            $verificationResult = $this->apiClient->getMoneiSdk()->verifySignature($payload, $signature);

            // The API client returns a valid payment object, check if it's valid
            $result = !empty($verificationResult);

            $this->logger->debug('[Callback] Signature verification result', [
                'is_valid' => $result ? 'true' : 'false'
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[Callback] Error verifying signature: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return false;
        }
    }

    /**
     * Dispatch payment callback events
     *
     * @param array $eventData
     * @return void
     */
    public function dispatchEvent(array $eventData): void
    {
        try {
            $this->eventManager->dispatch('monei_payment_callback_received', $eventData);

            // Dispatch specific event based on payment status if available
            if (isset($eventData['payment_dto']) && $eventData['payment_dto'] instanceof PaymentDTO) {
                $status = $eventData['payment_dto']->getStatus();
                if ($status) {
                    $this->eventManager->dispatch(
                        'monei_payment_callback_' . strtolower($status),
                        $eventData
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('[Callback] Error dispatching event: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }

    /**
     * Process payment from callback data
     *
     * @param PaymentDTO $paymentDTO
     * @return void
     */
    private function processPaymentFromCallback(PaymentDTO $paymentDTO): void
    {
        try {
            $orderId = $paymentDTO->getOrderId();
            if (!$orderId) {
                $this->logger->warning('[Callback] Missing order ID in payment data');
                return;
            }

            $incrementId = null;
            $entityId = null;

            // Try to determine if orderId is an increment ID or entity ID
            if (is_numeric($orderId)) {
                try {
                    // Try to load as entity ID first
                    $order = $this->orderRepository->get($orderId);
                    if ($order && $order->getEntityId()) {
                        $entityId = $orderId;
                        $incrementId = $order->getIncrementId();
                    }
                } catch (\Exception $e) {
                    // Ignore and try as increment ID below
                }
            }

            if (!$entityId) {
                // Load order by increment ID (assuming orderId is increment ID)
                try {
                    $searchCriteria = $this->searchCriteriaBuilder
                        ->addFilter('increment_id', $orderId)
                        ->create();
                    $orderList = $this->orderRepository->getList($searchCriteria);
                    $orders = $orderList->getItems();

                    if (count($orders) > 0) {
                        $order = reset($orders);
                        $entityId = $order->getEntityId();
                        $incrementId = $orderId;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('[Callback] Error searching for order: ' . $e->getMessage(), [
                        'exception' => $e,
                        'order_id' => $orderId
                    ]);
                }
            }

            if (!$entityId) {
                $this->logger->warning('[Callback] Order not found for ID: ' . $orderId);
                return;
            }

            $status = $paymentDTO->getStatus();
            $this->logger->debug('[Callback] Processing payment with status: ' . $status, [
                'increment_id' => $incrementId,
                'entity_id' => $entityId,
                'payment_id' => $paymentDTO->getId()
            ]);

            // Process payment based on status
            switch ($status) {
                case PaymentStatus::SUCCEEDED:
                case PaymentStatus::FAILED:
                case PaymentStatus::CANCELED:
                case PaymentStatus::AUTHORIZED:
                    // Always use the order increment ID for payment processing
                    $this->paymentProcessor->process($incrementId, $paymentDTO->getId(), $paymentDTO->getRawData());
                    break;
                default:
                    $this->logger->warning('[Callback] Unhandled payment status: ' . $status);
            }
        } catch (\Exception $e) {
            $this->logger->error('[Callback] Error processing payment: ' . $e->getMessage(), [
                'exception' => $e,
                'payment_id' => $paymentDTO->getId(),
                'order_id' => $paymentDTO->getOrderId()
            ]);
        }
    }
}
