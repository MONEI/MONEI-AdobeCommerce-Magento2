<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentMethodsInterface;
use Monei\MoneiPayment\Registry\AccountId as RegistryAccountId;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use OpenAPI\Client\ApiException;

/**
 * Monei get payment methods service class using the official MONEI PHP SDK.
 */
class GetPaymentMethods extends AbstractApiService implements GetPaymentMethodsInterface
{
    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private $moduleConfig;

    /**
     * @var RegistryAccountId
     */
    private $registryAccountId;

    /**
     * @var MoneiApiClient
     */
    private $moneiApiClient;

    /**
     * @param Logger $logger
     * @param MoneiApiClient $moneiApiClient
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param RegistryAccountId $registryAccountId
     */
    public function __construct(
        Logger $logger,
        MoneiApiClient $moneiApiClient,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        RegistryAccountId $registryAccountId
    ) {
        parent::__construct($logger);
        $this->moneiApiClient = $moneiApiClient;
        $this->moduleConfig = $moduleConfig;
        $this->registryAccountId = $registryAccountId;
    }

    /**
     * Get available payment methods using the official MONEI SDK
     *
     * @param string|null $accountId Optional account ID to filter payment methods
     *
     * @return array List of available payment methods
     */
    public function execute(string $accountId = null): array
    {
        return $this->executeApiCall(__METHOD__, function () use ($accountId) {
            try {
                // Determine account ID if not provided
                if ($accountId === null) {
                    $storeId = null;
                    $accountId = $this->moduleConfig->getAccountId($storeId);
                }

                // Store the account ID in registry for later use
                if (!empty($accountId)) {
                    $this->registryAccountId->set($accountId);

                    // When using Account ID, set the accountId parameter in SDK
                    // This requires also setting the User-Agent
                    $moneiSdk = $this->moneiApiClient->getMoneiSdk();
                    $moneiSdk->setAccountId($accountId);
                } else {
                    $moneiSdk = $this->moneiApiClient->getMoneiSdk();
                }

                // Get the payment methods - using listAll() method as specified in the MONEI SDK
                $methods = $moneiSdk->paymentMethods->listAll();

                // Convert response to array
                return $this->moneiApiClient->convertResponseToArray($methods);
            } catch (ApiException $e) {
                $this->logger->critical('[API Error] ' . $e->getMessage());
                throw new LocalizedException(__('Failed to get payment methods from MONEI API: %1', $e->getMessage()));
            } catch (\Exception $e) {
                $this->logger->critical('[Error] ' . $e->getMessage());
                throw new LocalizedException(__('Failed to get payment methods from MONEI API: %1', $e->getMessage()));
            }
        }, ['accountId' => $accountId]);
    }
}
