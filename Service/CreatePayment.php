<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Monei\MoneiPayment\Model\Config\Source\TypeOfPayment;

/**
 * Monei create payment REST integration service class.
 */
class CreatePayment extends AbstractService implements CreatePaymentInterface
{
    public const METHOD = 'payments';

    /** @var array */
    private $requiredArguments = [
        'amount',
        'currency',
        'orderId',
        'customer',
        'billingDetails',
        'shippingDetails',
    ];

    public function execute(array $data): array
    {
        $this->logger->debug(__METHOD__);

        try {
            $this->validate($data);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
        $data = array_merge($data, $this->getUrls());
        if (TypeOfPayment::TYPE_PRE_AUTHORIZED === $this->moduleConfig->getTypeOfPayment()) {
            $data['transactionType'] = 'AUTH';
        }

        $this->logger->debug('------------------ START CREATE PAYMENT REQUEST -----------------');
        $this->logger->debug($this->serializer->serialize($data));
        $this->logger->debug('------------------- END CREATE PAYMENT REQUEST ------------------');
        $this->logger->debug('');

        $client = $this->createClient();

        try {
            $response = $client->post(
                self::METHOD,
                [
                    'headers' => $this->getHeaders(),
                    'json' => $data,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());

            return ['error' => true, 'orderId' => $data['orderId']];
        }

        $this->logger->debug('----------------- START CREATE PAYMENT RESPONSE -----------------');
        $this->logger->debug((string) $response->getBody());
        $this->logger->debug('------------------ END CREATE PAYMENT RESPONSE ------------------');
        $this->logger->debug('');

        return $this->serializer->unserialize($response->getBody());
    }

    /**
     * Validate request required parameters.
     *
     * @param array $data
     */
    private function validate(array $data): void
    {
        foreach ($this->requiredArguments as $argument) {
            if (!isset($data[$argument]) || null === $data[$argument]) {
                $this->throwRequiredArgumentException($argument);
            }
        }
    }
}
