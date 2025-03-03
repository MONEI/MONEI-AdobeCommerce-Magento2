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
    /** API endpoint for payment operations. */
    public const METHOD = 'payments';

    /**
     * Execute a payment retrieval request to the Monei API.
     *
     * Retrieves payment details by ID from the Monei payment API.
     *
     * @param string $paymentId The ID of the payment to retrieve
     *
     * @return array Response from the API with payment details or error information
     */
    public function execute(string $paymentId): array
    {
        $this->logger->debug('[Method] ' . __METHOD__);

        $this->logger->debug('[Get payment request]');
        $this->logger->debug('[Payment ID] ' . $paymentId);

        try {
            $response = $this->createClient()->get('payments/' . $paymentId, [
                'headers' => $this->getHeaders()
            ]);
        } catch (\Exception $e) {
            $this->logger->critical('[Exception] ' . $e->getMessage());

            throw $e;
        }

        $this->logger->debug('[Get payment response]');
        $this->logger->debug(json_encode(json_decode((string) $response->getBody()), JSON_PRETTY_PRINT));

        return $this->serializer->unserialize($response->getBody());
    }
}
