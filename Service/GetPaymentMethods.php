<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use GuzzleHttp\ClientFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentMethodsInterface;
use Monei\MoneiPayment\Model\Config\Source\ModuleVersion;
use Monei\MoneiPayment\Registry\AccountId as RegistryAccountId;

/**
 * Monei get payment REST integration service class.
 */
class GetPaymentMethods extends AbstractService implements GetPaymentMethodsInterface
{
    public const METHOD = 'payment-methods';

    /** @var RegistryAccountId Registry for storing account ID */
    private RegistryAccountId $registryAccountId;

    /**
     * Constructor.
     *
     * @param RegistryAccountId $registryAccountId
     * @param ClientFactory $clientFactory Class from GuzzleHttp namespace
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param SerializerInterface $serializer
     * @param Logger $logger
     * @param ModuleVersion $moduleVersion
     */
    public function __construct(
        RegistryAccountId $registryAccountId,
        ClientFactory $clientFactory,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        SerializerInterface $serializer,
        Logger $logger,
        ModuleVersion $moduleVersion
    ) {
        parent::__construct(
            $clientFactory,
            $moduleConfig,
            $storeManager,
            $urlBuilder,
            $serializer,
            $logger,
            $moduleVersion
        );
        $this->registryAccountId = $registryAccountId;
    }

    /**
     * Execute payment methods request.
     *
     * @return array List of available payment methods
     */
    public function execute(string $accountId = null): array
    {
        $this->logger->debug('[Method] ' . __METHOD__);

        if ($accountId === null) {
            $storeId = null;
            $accountId = $this->moduleConfig->getAccountId($storeId);
        }

        $this->logger->debug('[Get payment methods request]');
        $this->logger->debug('[Account ID] ' . $accountId);

        try {
            $response = $this->createClient()->get(
                self::METHOD,
                [
                    'headers' => $this->getHeaders(),
                    'query' => [
                        'accountId' => $accountId
                    ]
                ]
            );
        } catch (\Exception $e) {
            $this->logger->critical('[Exception] ' . $e->getMessage());
            throw $e;
        }

        $this->logger->debug('[Get payment methods response]');
        $this->logger->debug(json_encode(json_decode((string) $response->getBody()), JSON_PRETTY_PRINT));


        return $this->serializer->unserialize($response->getBody());
    }
}
