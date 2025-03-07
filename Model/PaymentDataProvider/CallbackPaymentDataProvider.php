<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\PaymentDataProvider;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Monei\MoneiPayment\Api\PaymentDataProviderInterface;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Service\Logger;

/**
 * Data provider for payment data from callbacks
 */
class CallbackPaymentDataProvider implements PaymentDataProviderInterface
{
    /**
     * @var Json
     */
    private Json $jsonSerializer;

    /**
     * @var PaymentDTOFactory
     */
    private PaymentDTOFactory $paymentDTOFactory;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param Json $jsonSerializer
     * @param PaymentDTOFactory $paymentDTOFactory
     * @param Logger $logger
     */
    public function __construct(
        Json $jsonSerializer,
        PaymentDTOFactory $paymentDTOFactory,
        Logger $logger
    ) {
        $this->jsonSerializer = $jsonSerializer;
        $this->paymentDTOFactory = $paymentDTOFactory;
        $this->logger = $logger;
    }

    /**
     * Extract payment data from callback payload
     *
     * @param string $payload
     * @param string $signature
     * @return PaymentDTO
     * @throws LocalizedException
     */
    public function extractFromCallback(string $payload, string $signature): PaymentDTO
    {
        try {
            $this->logger->debug('[Callback] Extracting payment data from callback payload');

            if (empty($payload)) {
                throw new LocalizedException(__('Empty callback payload'));
            }

            // Decode the JSON payload
            try {
                $data = $this->jsonSerializer->unserialize($payload);
            } catch (\Exception $e) {
                $this->logger->error('[Callback] Invalid JSON payload: ' . $e->getMessage(), [
                    'exception' => $e,
                    'payload' => $payload
                ]);

                throw new LocalizedException(__('Invalid JSON payload: %1', $e->getMessage()));
            }

            // Check if this is a valid payment callback
            if (empty($data)) {
                throw new LocalizedException(__('Empty data in callback payload'));
            }

            if (!isset($data['data']) || !is_array($data['data'])) {
                $this->logger->error('[Callback] Missing data field in payload', [
                    'payload' => $payload
                ]);

                throw new LocalizedException(__('Missing data field in callback payload'));
            }

            if (!isset($data['data']['id'])) {
                $this->logger->error('[Callback] Missing payment ID in payload', [
                    'payload' => $payload
                ]);

                throw new LocalizedException(__('Missing payment ID in callback payload'));
            }

            // Extract the payment data from the payload
            $paymentData = $data['data'];

            // Create and return the payment DTO
            $paymentDTO = $this->paymentDTOFactory->create();
            $paymentDTO->setId($paymentData['id']);
            $paymentDTO->setOrderId($paymentData['orderId'] ?? null);
            $paymentDTO->setAmount($paymentData['amount'] ?? 0);
            $paymentDTO->setCurrency($paymentData['currency'] ?? '');
            $paymentDTO->setStatus($paymentData['status'] ?? '');
            $paymentDTO->setRawData($paymentData);

            $this->logger->debug('[Callback] Payment data extracted successfully', [
                'payment_id' => $paymentDTO->getId(),
                'order_id' => $paymentDTO->getOrderId(),
                'status' => $paymentDTO->getStatus()
            ]);

            return $paymentDTO;
        } catch (LocalizedException $e) {
            // Re-throw LocalizedException as is
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('[Callback] Error extracting payment data: ' . $e->getMessage(), [
                'exception' => $e,
                'payload' => $payload
            ]);

            throw new LocalizedException(__('Error extracting payment data: %1', $e->getMessage()));
        }
    }

    /**
     * @inheritDoc
     */
    public function getPaymentData(string $paymentId): PaymentDTO
    {
        throw new LocalizedException(__('Method not supported for callback payment data provider'));
    }

    /**
     * @inheritDoc
     */
    public function validatePaymentData(array $data): bool
    {
        if (empty($data) || !isset($data['id'])) {
            return false;
        }

        return true;
    }
}
