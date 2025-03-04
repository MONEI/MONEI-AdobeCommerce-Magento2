<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\PaymentDataProvider;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\SerializerInterface;
use Monei\MoneiPayment\Api\PaymentDataProviderInterface;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;

/**
 * Extract payment data from webhook request
 */
class WebhookPaymentDataProvider implements PaymentDataProviderInterface
{
    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $apiClient;

    /**
     * @param SerializerInterface $serializer
     * @param Logger $logger
     * @param MoneiApiClient $apiClient
     */
    public function __construct(
        SerializerInterface $serializer,
        Logger $logger,
        MoneiApiClient $apiClient
    ) {
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->apiClient = $apiClient;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentData(string $paymentId): PaymentDTO
    {
        throw new LocalizedException(__('Direct payment ID lookup not supported in webhook provider. Use webhook data instead.'));
    }

    /**
     * Extract payment data from webhook request body
     *
     * @param string $requestBody
     * @param string|null $signatureHeader
     * @return PaymentDTO
     * @throws LocalizedException
     */
    public function extractFromWebhook(string $requestBody, ?string $signatureHeader = null): PaymentDTO
    {
        try {
            // Verify webhook signature if provided
            if ($signatureHeader !== null) {
                $this->verifyWebhookSignature($requestBody, $signatureHeader);
            }

            $data = $this->serializer->unserialize($requestBody);

            if (!$this->validatePaymentData($data)) {
                throw new LocalizedException(__('Invalid webhook data format'));
            }

            return PaymentDTO::fromArray($data);
        } catch (\Exception $e) {
            $this->logger->error('Error extracting payment data from webhook: ' . $e->getMessage(), [
                'request_body' => $requestBody
            ]);
            throw new LocalizedException(__('Failed to parse webhook data: %1', $e->getMessage()));
        }
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload
     * @param string $signatureHeader
     * @return void
     * @throws LocalizedException
     */
    private function verifyWebhookSignature(string $payload, string $signatureHeader): void
    {
        try {
            $this->logger->debug('Verifying webhook signature');

            if (!$this->apiClient->verifyWebhookSignature($payload, $signatureHeader)) {
                throw new LocalizedException(__('Invalid webhook signature'));
            }

            $this->logger->debug('Webhook signature verified successfully');
        } catch (LocalizedException $e) {
            $this->logger->error('Webhook signature verification failed: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Error during webhook signature verification: ' . $e->getMessage());
            throw new LocalizedException(__('Failed to verify webhook signature: %1', $e->getMessage()));
        }
    }

    /**
     * @inheritdoc
     */
    public function validatePaymentData(array $data): bool
    {
        $requiredFields = ['id', 'status', 'amount', 'currency', 'orderId'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->logger->error('Missing required field in webhook data: ' . $field, [
                    'data' => $data
                ]);
                return false;
            }
        }

        return true;
    }
}
