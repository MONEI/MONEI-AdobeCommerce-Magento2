<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Monei\MoneiPayment\Api\Service\GetPaymentInterface;

/**
 * Monei get payment REST integration service class.
 */
class GetPayment extends AbstractService implements GetPaymentInterface
{
    /**
     * API endpoint for payment operations.
     */
    public const METHOD = 'payments';

    /**
     * Execute a payment retrieval request to the Monei API.
     *
     * Retrieves payment details by ID from the Monei payment API.
     *
     * @param string $paymentId The ID of the payment to retrieve
     * @return array Response from the API with payment details or error information
     */
    public function execute(string $paymentId): array
    {
        $this->logger->debug(__METHOD__);

        $client = $this->createClient();

        $this->logger->debug('------------------ START GET PAYMENT REQUEST -----------------');
        $this->logger->debug('Payment id = ' . $paymentId);
        $this->logger->debug('------------------- END GET PAYMENT REQUEST ------------------');
        $this->logger->debug('');

        $response = $client->get(
            self::METHOD . '/' . $paymentId,
            [
                'headers' => $this->getHeaders(),
            ]
        );

        $this->logger->debug('----------------- START GET PAYMENT RESPONSE -----------------');
        $this->logger->debug((string) $response->getBody());
        $this->logger->debug('------------------ END GET PAYMENT RESPONSE ------------------');
        $this->logger->debug('');

        return $this->serializer->unserialize($response->getBody());
    }
}
