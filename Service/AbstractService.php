<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\NoSuchEntityException;
use Monei\MoneiPayment\Model\Config\Source\Mode;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Client;
use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Monei\MoneiPayment\Model\Config\Source\ModuleVersion;
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
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var Logger
     */
    protected $logger;

    private ModuleVersion $moduleVersion;

    /**
     * @param ClientFactory                     $clientFactory
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param StoreManagerInterface             $storeManager
     * @param UrlInterface                      $urlBuilder
     * @param SerializerInterface               $serializer
     * @param Logger                            $logger
     * @param ModuleVersion                     $moduleVersion
     */
    public function __construct(
        ClientFactory $clientFactory,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        SerializerInterface $serializer,
        Logger $logger,
        ModuleVersion $moduleVersion
    ) {
        $this->clientFactory = $clientFactory;
        $this->moduleConfig = $moduleConfig;
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->moduleVersion = $moduleVersion;
    }

    /**
     * Get webservice API url(test or production)
     *
     * @param int|null $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getApiUrl(int $storeId = null): string
    {
        $currentStoreId = $storeId ?: $this->storeManager->getStore()->getId();

        return $this->moduleConfig->getUrl($currentStoreId);
    }

    /**
     * Get webservice API key(test or production)
     *
     * @param int|null $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    private function getApiKey(int $storeId = null): string
    {
        $currentStoreId = $storeId ?: $this->storeManager->getStore()->getId();

        return $this->moduleConfig->getApiKey($currentStoreId);
    }

    /**
     * Get webservice API user agent with module version
     * @return string
     */
    private function getUserAgent(): string
    {
        $moduleVersion = $this->moduleVersion->getModuleVersion();
        if ($moduleVersion) {
            return 'MONEI/Magento2/' . $moduleVersion;
        }

        return '';
    }

    /**
     * @param int|null $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    protected function getHeaders(int $storeId = null): array
    {
        return [
            'Authorization' => $this->getApiKey($storeId),
            'User-Agent'    => $this->getUserAgent(),
            'Content-Type'  => 'application/json',
        ];
    }

    /**
     * Creates client with base URI
     *
     * @param int|null $storeId
     * @return Client
     * @throws NoSuchEntityException
     */
    public function createClient(int $storeId = null): Client
    {
        return $this->clientFactory->create(['config' => [
            'base_uri' => $this->getApiUrl($storeId)
        ]]);
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
