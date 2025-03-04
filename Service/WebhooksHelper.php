<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\PaymentDataProviderInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Api\Service\ValidateWebhookSignatureInterface;
use Monei\MoneiPayment\Api\WebhooksHelperInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Data\PaymentDTO;

/**
 * Helper for webhook handling
 */
class WebhooksHelper implements WebhooksHelperInterface
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
    private PaymentDataProviderInterface $webhookPaymentDataProvider;

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
     * @var ValidateWebhookSignatureInterface
     */
    private ValidateWebhookSignatureInterface $validateWebhookSignature;

    /**
     * @param Logger $logger
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param PaymentDataProviderInterface $webhookPaymentDataProvider
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentProcessorInterface $paymentProcessor
     * @param EventManagerInterface $eventManager
     * @param ValidateWebhookSignatureInterface $validateWebhookSignature
     */
    public function __construct(
        Logger $logger,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        PaymentDataProviderInterface $webhookPaymentDataProvider,
        OrderRepositoryInterface $orderRepository,
        PaymentProcessorInterface $paymentProcessor,
        EventManagerInterface $eventManager,
        ValidateWebhookSignatureInterface $validateWebhookSignature
    ) {
        $this->logger = $logger;
        $this->moduleConfig = $moduleConfig;
        $this->webhookPaymentDataProvider = $webhookPaymentDataProvider;
        $this->orderRepository = $orderRepository;
        $this->paymentProcessor = $paymentProcessor;
        $this->eventManager = $eventManager;
        $this->validateWebhookSignature = $validateWebhookSignature;
    }

    /**
     * @inheritdoc
     */
    public function processWebhook(RequestInterface $request): void
    {
        try {
            $payload = $request->getContent();
            $signatureHeader = $request->getHeader('Monei-Signature');

            // Verify webhook signature if available
            if ($signatureHeader && !$this->verifyWebhookSignature($payload, ['Monei-Signature' => $signatureHeader])) {
                $this->logger->error('[Webhook] Invalid signature');
                return;
            }

            // Extract payment data from webhook
            $paymentDTO = $this->webhookPaymentDataProvider->extractFromWebhook($payload, $signatureHeader);
            $paymentData = $paymentDTO->getRawData();

            // Dispatch event with the webhook data
            $this->dispatchEvent($paymentData);

            // Process the payment
            $this->processPaymentFromWebhook($paymentDTO);
        } catch (\Exception $e) {
            $this->logger->error('[Webhook] Error processing webhook: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * @inheritdoc
     */
    public function verifyWebhookSignature(string $payload, array $headers): bool
    {
        try {
            $signatureHeader = $headers['Monei-Signature'] ?? '';
            if (empty($signatureHeader)) {
                $this->logger->warning('[Webhook] Missing signature header');
                return false;
            }

            $webhookSecret = $this->moduleConfig->getWebhookSecret();
            if (empty($webhookSecret)) {
                $this->logger->warning('[Webhook] Webhook secret not configured');
                return false;
            }

            return $this->validateWebhookSignature->validate($payload, $signatureHeader, $webhookSecret);
        } catch (\Exception $e) {
            $this->logger->error('[Webhook] Error verifying signature: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function dispatchEvent(array $eventData): void
    {
        try {
            $eventName = 'monei_payment_webhook_' . strtolower($eventData['status'] ?? 'unknown');
            $this->logger->info('[Webhook] Dispatching event: ' . $eventName);

            $this->eventManager->dispatch($eventName, ['webhook_data' => $eventData]);
            $this->eventManager->dispatch('monei_payment_webhook', ['webhook_data' => $eventData]);
        } catch (\Exception $e) {
            $this->logger->error('[Webhook] Error dispatching event: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Process payment from webhook data
     *
     * @param PaymentDTO $paymentDTO
     * @return void
     */
    private function processPaymentFromWebhook(PaymentDTO $paymentDTO): void
    {
        try {
            $orderId = $paymentDTO->getOrderId();
            if (empty($orderId)) {
                $this->logger->warning('[Webhook] Missing order ID in payment data');
                return;
            }

            // Load the order
            $order = $this->orderRepository->get($orderId);
            if (!$order || !$order->getEntityId()) {
                $this->logger->warning('[Webhook] Order not found: ' . $orderId);
                return;
            }

            // Process the payment
            $result = $this->paymentProcessor->process(
                $order->getIncrementId(),
                $paymentDTO->getId(),
                $paymentDTO->getRawData()
            );

            if ($result->isSuccessful()) {
                $this->logger->info(sprintf(
                    '[Webhook] Payment processed successfully: Order %s, Payment %s, Status: %s',
                    $order->getIncrementId(),
                    $paymentDTO->getId(),
                    $paymentDTO->getStatus()
                ));
            } else {
                $this->logger->warning(sprintf(
                    '[Webhook] Payment processing failed: Order %s, Payment %s, Error: %s',
                    $order->getIncrementId(),
                    $paymentDTO->getId(),
                    $result->getErrorMessage() ?? 'Unknown error'
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error(
                '[Webhook] Error processing payment: ' . $e->getMessage(),
                ['payment_id' => $paymentDTO->getId(), 'exception' => $e]
            );
        }
    }
}
