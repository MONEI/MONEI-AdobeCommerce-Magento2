<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\PaymentDataProvider;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\PaymentDataProviderInterface;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;

/**
 * Fetch payment data from MONEI API
 */
class ApiPaymentDataProvider implements PaymentDataProviderInterface
{
    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $apiClient;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * @var array
     */
    private array $paymentCache = [];

    /**
     * @param MoneiApiClient $apiClient
     * @param Logger $logger
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     */
    public function __construct(
        MoneiApiClient $apiClient,
        Logger $logger,
        MoneiPaymentModuleConfigInterface $moduleConfig
    ) {
        $this->apiClient = $apiClient;
        $this->logger = $logger;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentData(string $paymentId): PaymentDTO
    {
        // Check cache first to prevent duplicate API calls
        if (isset($this->paymentCache[$paymentId])) {
            return $this->paymentCache[$paymentId];
        }

        try {
            $response = $this->apiClient->getPayment($paymentId);

            if (!$this->validatePaymentData($response)) {
                throw new LocalizedException(__('Invalid payment data returned from API'));
            }

            $paymentDTO = PaymentDTO::fromArray($response);

            // Cache the result
            $this->paymentCache[$paymentId] = $paymentDTO;

            return $paymentDTO;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching payment data from API: ' . $e->getMessage(), [
                'payment_id' => $paymentId
            ]);

            throw new LocalizedException(__('Failed to fetch payment data: %1', $e->getMessage()));
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
                $this->logger->error('Missing required field in API response: ' . $field, [
                    'data' => $data
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * Clear the payment cache
     *
     * @param string|null $paymentId If provided, clear only this payment's cache
     * @return void
     */
    public function clearCache(?string $paymentId = null): void
    {
        if ($paymentId !== null) {
            unset($this->paymentCache[$paymentId]);
        } else {
            $this->paymentCache = [];
        }
    }
}
