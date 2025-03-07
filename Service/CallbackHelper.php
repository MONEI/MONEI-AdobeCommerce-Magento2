<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\PaymentDataProviderInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Api\Service\CallbackHelperInterface;
use Monei\MoneiPayment\Api\Service\ValidateCallbackSignatureInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;

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
     * @var ValidateCallbackSignatureInterface
     */
    private ValidateCallbackSignatureInterface $validateCallbackSignature;

    /**
     * @param Logger $logger
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param PaymentDataProviderInterface $callbackPaymentDataProvider
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentProcessorInterface $paymentProcessor
     * @param EventManagerInterface $eventManager
     * @param ValidateCallbackSignatureInterface $validateCallbackSignature
     */
    public function __construct(
        Logger $logger,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        PaymentDataProviderInterface $callbackPaymentDataProvider,
        OrderRepositoryInterface $orderRepository,
        PaymentProcessorInterface $paymentProcessor,
        EventManagerInterface $eventManager,
        ValidateCallbackSignatureInterface $validateCallbackSignature
    ) {
        $this->logger = $logger;
        $this->moduleConfig = $moduleConfig;
        $this->callbackPaymentDataProvider = $callbackPaymentDataProvider;
        $this->orderRepository = $orderRepository;
        $this->paymentProcessor = $paymentProcessor;
        $this->eventManager = $eventManager;
        $this->validateCallbackSignature = $validateCallbackSignature;
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
            $payload = $request->getContent();
            $signatureHeader = $request->getHeader('MONEI-Signature');

            // Verify callback signature if available
            if ($signatureHeader && !$this->verifyCallbackSignature($payload, ['MONEI-Signature' => $signatureHeader])) {
                $this->logger->warning('[Callback] Invalid signature');
                return;
            }

            // Extract payment data from callback
            $paymentDTO = $this->callbackPaymentDataProvider->extractFromCallback($payload, $signatureHeader ?? '');
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
     * Verify the signature of a callback
     *
     * @param string $payload The raw request body
     * @param array $headers Request headers
     * @return bool True if signature is valid, false otherwise
     */
    public function verifyCallbackSignature(string $payload, array $headers): bool
    {
        try {
            $signatureHeader = $headers['MONEI-Signature'] ?? '';
            if (empty($signatureHeader)) {
                $this->logger->warning('[Callback] Missing MONEI-Signature header');
                return false;
            }

            // Get the webhook secret from configuration
            $webhookSecret = $this->moduleConfig->getWebhookSecret();

            // Validate the signature
            return $this->validateCallbackSignature->validate($payload, $signatureHeader, $webhookSecret);
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

            // Load the order
            $order = $this->orderRepository->get($orderId);
            if (!$order || !$order->getEntityId()) {
                $this->logger->warning('[Callback] Order not found: ' . $orderId);
                return;
            }

            $status = $paymentDTO->getStatus();
            $this->logger->debug('[Callback] Processing payment with status: ' . $status, [
                'order_id' => $order->getIncrementId(),
                'payment_id' => $paymentDTO->getId()
            ]);

            // Process payment based on status
            switch ($status) {
                case 'SUCCEEDED':
                    $this->paymentProcessor->processPayment($order, $paymentDTO);
                    break;
                case 'FAILED':
                    $this->paymentProcessor->processPayment($order, $paymentDTO);
                    break;
                case 'CANCELED':
                    $this->paymentProcessor->processPayment($order, $paymentDTO);
                    break;
                case 'AUTHORIZED':
                    $this->paymentProcessor->processPayment($order, $paymentDTO);
                    break;
                default:
                    $this->logger->warning('[Callback] Unhandled payment status: ' . $status);
            }
        } catch (\Exception $e) {
            $this->logger->error('[Callback] Error processing payment: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }
}
