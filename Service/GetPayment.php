<?php

/**
 * @author Monei Team
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
    public const METHOD = 'payments';

    public function execute(string $paymentId): array
    {
        $this->logger->debug(__METHOD__);

        $client = $this->createClient();

        $this->logger->debug('------------------ START GET PAYMENT REQUEST -----------------');
        $this->logger->debug('Payment id = '.$paymentId);
        $this->logger->debug('------------------- END GET PAYMENT REQUEST ------------------');
        $this->logger->debug('');

        $response = $client->get(
            self::METHOD.'/'.$paymentId,
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
