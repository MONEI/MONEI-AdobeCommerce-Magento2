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
    public function execute(): array
    {
        $this->logger->debug(__METHOD__);

        $storeId = (int) $this->storeManager->getStore()->getId();
        $accountId = $this->registryAccountId->get() ?? $this->moduleConfig->getAccountId($storeId);

        if ($accountId) {
            $client = $this->createClient();

            $this->logger->debug('------------------ START GET PAYMENT METHODS REQUEST -----------------');
            $this->logger->debug('Account id = ' . $accountId);
            $this->logger->debug('------------------- END GET PAYMENT METHODS REQUEST ------------------');
            $this->logger->debug('');

            $response = $client->get(
                self::METHOD . '?accountId=' . $accountId . '&' . time(),
                [
                    'headers' => $this->getHeaders(),
                ]
            );

            $this->logger->debug('----------------- START GET PAYMENT METHODS RESPONSE -----------------');
            $this->logger->debug((string) $response->getBody());
            $this->logger->debug('------------------ END GET PAYMENT METHODS RESPONSE ------------------');
            $this->logger->debug('');

            return $this->serializer->unserialize($response->getBody());
        }

        return [];
    }
}
