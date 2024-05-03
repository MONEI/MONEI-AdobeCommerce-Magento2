<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Monei\MoneiPayment\Api\Service\GetPaymentMethodsInterface;

/**
 * Monei get payment REST integration service class.
 */
class GetPaymentMethods extends AbstractService implements GetPaymentMethodsInterface
{
    public const METHOD = 'payment-methods';


    /**
     * @inheritDoc
     */
    public function execute(): array
    {
        $this->logger->debug(__METHOD__);

        $storeId = (int) $this->storeManager->getStore()->getId();
        $accountId = $this->moduleConfig->getAccountId($storeId);

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
}
