<?php

/**
 * Copyright © Monei. All rights reserved.
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\VerifyApplePayDomainInterface;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;

/**
 * Observer for system configuration changes to automatically verify Apple Pay domain
 */
class ConfigChangeObserver implements ObserverInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var MoneiGoogleApplePaymentModuleConfigInterface
     */
    private $googleAppleConfig;

    /**
     * @var VerifyApplePayDomainInterface
     */
    private $verifyApplePayDomain;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var MoneiApiClient
     */
    private $apiClient;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private $moneiConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param MoneiGoogleApplePaymentModuleConfigInterface $googleAppleConfig
     * @param VerifyApplePayDomainInterface $verifyApplePayDomain
     * @param StoreManagerInterface $storeManager
     * @param Logger $logger
     * @param MoneiApiClient $apiClient
     * @param MoneiPaymentModuleConfigInterface $moneiConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        MoneiGoogleApplePaymentModuleConfigInterface $googleAppleConfig,
        VerifyApplePayDomainInterface $verifyApplePayDomain,
        StoreManagerInterface $storeManager,
        Logger $logger,
        MoneiApiClient $apiClient,
        MoneiPaymentModuleConfigInterface $moneiConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->googleAppleConfig = $googleAppleConfig;
        $this->verifyApplePayDomain = $verifyApplePayDomain;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->apiClient = $apiClient;
        $this->moneiConfig = $moneiConfig;
    }

    /**
     * Observer for config changes
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $changedPaths = $observer->getEvent()->getChangedPaths();

        // Check if Monei payment configuration has been changed
        $moneiConfigChanged = false;
        foreach ($changedPaths as $path) {
            if (strpos($path, 'payment/monei_') === 0) {
                $moneiConfigChanged = true;
                break;
            }
        }

        if (!$moneiConfigChanged) {
            return;
        }

        // Check if Apple Pay is enabled
        if (!$this->googleAppleConfig->isEnabled()) {
            return;
        }

        try {
            // Get the store's base URL domain
            $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
            $domain = parse_url($baseUrl, PHP_URL_HOST);

            if (empty($domain)) {
                $this->logger->error('Failed to extract domain from store URL for Apple Pay verification');
                return;
            }

            // Get the fresh API key directly from config based on the current mode
            $isTestMode = (bool) $this->scopeConfig->getValue(
                MoneiPaymentModuleConfigInterface::MODE,
                ScopeInterface::SCOPE_STORE
            );

            $configPath = $isTestMode
                ? MoneiPaymentModuleConfigInterface::TEST_API_KEY
                : MoneiPaymentModuleConfigInterface::PRODUCTION_API_KEY;

            $freshApiKey = $this->scopeConfig->getValue(
                $configPath,
                ScopeInterface::SCOPE_STORE
            );

            // Force reinitialize the API client with the fresh API key
            $this->apiClient->reinitialize($freshApiKey);

            // Register the domain with Apple Pay
            $this->logger->info('Automatically verifying Apple Pay domain: ' . $domain);
            $result = $this->verifyApplePayDomain->execute($domain);
            $this->logger->info('Apple Pay domain verification result: ' . json_encode($result));
        } catch (LocalizedException $e) {
            $this->logger->error('Error during automatic Apple Pay domain verification: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during automatic Apple Pay domain verification: ' . $e->getMessage());
        }
    }
}
