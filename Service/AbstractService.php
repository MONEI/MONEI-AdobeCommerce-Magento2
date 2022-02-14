<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Monei\MoneiPayment\Model\Config\Source\Mode;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Client;
use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Monei\MoneiPayment\Service\Logger;

/**
 * Monei WS service abstract class.
 */
abstract class AbstractService
{
    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    protected $moduleConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param ClientFactory                     $clientFactory
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param StoreManagerInterface             $storeManager
     * @param UrlInterface                      $urlBuilder
     * @param SerializerInterface               $serializer
     * @param Logger                   $logger
     */
    public function __construct(
        ClientFactory $clientFactory,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        SerializerInterface $serializer,
        Logger $logger
    ) {
        $this->clientFactory = $clientFactory;
        $this->moduleConfig = $moduleConfig;
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Get webservice API url(test or production)
     *
     * @return string
     */
    protected function getApiUrl(): string
    {
        $currentStoreId = $this->storeManager->getStore()->getId();
        if ($this->moduleConfig->getMode($currentStoreId) === Mode::MODE_TEST) {
            return $this->moduleConfig->getTestUrl($currentStoreId);
        }

        return $this->moduleConfig->getProductionUrl($currentStoreId);
    }

    /**
     * Get webservice API key(test or production)
     *
     * @return string
     */
    private function getApiKey(): string
    {
        $currentStoreId = $this->storeManager->getStore()->getId();
        if ($this->moduleConfig->getMode($currentStoreId) === Mode::MODE_TEST) {
            return $this->moduleConfig->getTestApiKey($currentStoreId);
        }

        return $this->moduleConfig->getProductionApiKey($currentStoreId);
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => $this->getApiKey(),
            'Content-Type'  => 'application/json',
        ];
    }

    /**
     * Creates client with base URI
     *
     * @return Client
     */
    public function createClient(): Client
    {
        $client = $this->clientFactory->create(['config' => [
            'base_uri' => $this->getApiUrl()
        ]]);

        return $client;
    }

    /**
     * Throws exception about missing required argument.
     *
     * @param string $parameter
     *
     * @throws LocalizedException
     */
    protected function throwRequiredArgumentException(string $parameter): void
    {
        throw new LocalizedException(__('Required parameter is missing %1', $parameter));
    }

    /**
     * Get array of URLs for WS request
     *
     * @return array
     */
    protected function getUrls(): array
    {
        return [
            'completeUrl' => $this->urlBuilder->getUrl('monei/payment/complete'),
            'callbackUrl' => $this->urlBuilder->getUrl('monei/payment/callback/'),
            'cancelUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
            'failUrl' => $this->urlBuilder->getUrl('monei/payment/fail'),
        ];
    }
}
