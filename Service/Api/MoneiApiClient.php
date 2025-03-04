<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Monei\ApiErrorException;
use Monei\Monei;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\Source\ModuleVersion;
use Monei\MoneiPayment\Service\Logger;

/**
 * Client for interacting with the MONEI API
 */
class MoneiApiClient
{
    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var ModuleVersion
     */
    private ModuleVersion $moduleVersion;

    /**
     * @var array
     */
    private array $sdkInstances = [];

    /**
     * @param StoreManagerInterface $storeManager
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param Logger $logger
     * @param ModuleVersion $moduleVersion
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        Logger $logger,
        ModuleVersion $moduleVersion
    ) {
        $this->storeManager = $storeManager;
        $this->moduleConfig = $moduleConfig;
        $this->logger = $logger;
        $this->moduleVersion = $moduleVersion;
    }

    /**
     * Get payment details from MONEI API
     *
     * @param string $paymentId
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    public function getPayment(string $paymentId, ?int $storeId = null): array
    {
        try {
            $monei = $this->getMoneiSdk($storeId);
            $payment = $monei->payments->retrieve($paymentId);

            return $payment->toArray();
        } catch (ApiErrorException $e) {
            $this->logger->critical('[API Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to get payment from MONEI API: %1', $e->getMessage()));
        } catch (\Exception $e) {
            $this->logger->critical('[Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to get payment from MONEI API: %1', $e->getMessage()));
        }
    }

    /**
     * Create a new payment with MONEI API
     *
     * @param array $paymentData
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    public function createPayment(array $paymentData, ?int $storeId = null): array
    {
        try {
            $monei = $this->getMoneiSdk($storeId);
            $payment = $monei->payments->create($paymentData);

            return $payment->toArray();
        } catch (ApiErrorException $e) {
            $this->logger->critical('[API Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to create payment with MONEI API: %1', $e->getMessage()));
        } catch (\Exception $e) {
            $this->logger->critical('[Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to create payment with MONEI API: %1', $e->getMessage()));
        }
    }

    /**
     * Capture a payment with MONEI API
     *
     * @param string $paymentId
     * @param float|null $amount
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    public function capturePayment(string $paymentId, ?float $amount = null, ?int $storeId = null): array
    {
        try {
            $monei = $this->getMoneiSdk($storeId);
            $captureData = [];

            if ($amount !== null) {
                $captureData['amount'] = (int)round($amount * 100);
            }

            $payment = $monei->payments->capture($paymentId, $captureData);

            return $payment->toArray();
        } catch (ApiErrorException $e) {
            $this->logger->critical('[API Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to capture payment with MONEI API: %1', $e->getMessage()));
        } catch (\Exception $e) {
            $this->logger->critical('[Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to capture payment with MONEI API: %1', $e->getMessage()));
        }
    }

    /**
     * Refund a payment with MONEI API
     *
     * @param string $paymentId
     * @param float|null $amount
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    public function refundPayment(string $paymentId, ?float $amount = null, ?int $storeId = null): array
    {
        try {
            $monei = $this->getMoneiSdk($storeId);
            $refundData = [];

            if ($amount !== null) {
                $refundData['amount'] = (int)round($amount * 100);
            }

            $payment = $monei->payments->refund($paymentId, $refundData);

            return $payment->toArray();
        } catch (ApiErrorException $e) {
            $this->logger->critical('[API Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to refund payment with MONEI API: %1', $e->getMessage()));
        } catch (\Exception $e) {
            $this->logger->critical('[Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to refund payment with MONEI API: %1', $e->getMessage()));
        }
    }

    /**
     * Cancel a payment with MONEI API
     *
     * @param string $paymentId
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    public function cancelPayment(string $paymentId, ?int $storeId = null): array
    {
        try {
            $monei = $this->getMoneiSdk($storeId);
            $payment = $monei->payments->cancel($paymentId);

            return $payment->toArray();
        } catch (ApiErrorException $e) {
            $this->logger->critical('[API Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to cancel payment with MONEI API: %1', $e->getMessage()));
        } catch (\Exception $e) {
            $this->logger->critical('[Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to cancel payment with MONEI API: %1', $e->getMessage()));
        }
    }

    /**
     * Get available payment methods from MONEI API
     *
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    public function getPaymentMethods(?int $storeId = null): array
    {
        try {
            $monei = $this->getMoneiSdk($storeId);
            $methods = $monei->paymentMethods->all();

            return $methods;
        } catch (ApiErrorException $e) {
            $this->logger->critical('[API Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to get payment methods from MONEI API: %1', $e->getMessage()));
        } catch (\Exception $e) {
            $this->logger->critical('[Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to get payment methods from MONEI API: %1', $e->getMessage()));
        }
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload
     * @param string $signatureHeader
     * @param int|null $storeId
     * @return bool
     * @throws LocalizedException
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader, ?int $storeId = null): bool
    {
        try {
            $monei = $this->getMoneiSdk($storeId);
            $event = $monei->webhooks->constructEvent($payload, $signatureHeader);

            return $event !== null;
        } catch (ApiErrorException $e) {
            $this->logger->critical('[API Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to verify webhook signature: %1', $e->getMessage()));
        } catch (\Exception $e) {
            $this->logger->critical('[Error] ' . $e->getMessage());
            throw new LocalizedException(__('Failed to verify webhook signature: %1', $e->getMessage()));
        }
    }

    /**
     * Get the MONEI SDK instance
     *
     * @param int|null $storeId
     * @return Monei
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getMoneiSdk(?int $storeId = null): Monei
    {
        $currentStoreId = $storeId ?: $this->storeManager->getStore()->getId();
        $cacheKey = (string)$currentStoreId;

        if (!isset($this->sdkInstances[$cacheKey])) {
            $apiKey = $this->getApiKey($currentStoreId);
            $apiUrl = $this->getApiUrl($currentStoreId);

            $monei = new Monei($apiKey, [
                'api_base' => $apiUrl,
                'app_info' => [
                    'name' => 'MONEI/Magento2',
                    'version' => $this->moduleVersion->getModuleVersion(),
                    'url' => 'https://github.com/MONEI/MONEI-AdobeCommerce-Magento2',
                ],
            ]);

            $this->sdkInstances[$cacheKey] = $monei;
        }

        return $this->sdkInstances[$cacheKey];
    }

    /**
     * Get the API URL based on the store configuration
     *
     * @param int $storeId
     * @return string
     */
    private function getApiUrl(int $storeId): string
    {
        $isSandbox = $this->moduleConfig->isSandboxMode($storeId);
        return $isSandbox ? 'https://api.sandbox.monei.com' : 'https://api.monei.com';
    }

    /**
     * Get the API key based on the store configuration
     *
     * @param int $storeId
     * @return string
     * @throws LocalizedException
     */
    private function getApiKey(int $storeId): string
    {
        $isSandbox = $this->moduleConfig->isSandboxMode($storeId);
        $apiKey = $isSandbox
            ? $this->moduleConfig->getSandboxApiKey($storeId)
            : $this->moduleConfig->getLiveApiKey($storeId);

        if (empty($apiKey)) {
            throw new LocalizedException(
                __('MONEI API key is not configured. Please set it in the module configuration.')
            );
        }

        return $apiKey;
    }
}
