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

    /**
     * Execute a payment creation request to the Monei API.
     *
     * Creates a new payment by sending a request to the Monei payment API.
     * The payment can be configured for authorization or capture based on the module configuration.
     *
     * @param array $data Payment data including amount, currency, orderId, customer and address details
     * @return array Response from the API with payment creation results or error details
     */
    public function execute(array $data): array
    {
        $this->logger->debug('[Method] ' . __METHOD__);

        try {
            $this->validate($data);
        } catch (\Exception $e) {
            $this->logger->critical('[Exception] ' . $e->getMessage());
        }
        $data = array_merge($data, $this->getUrls());
        if (TypeOfPayment::TYPE_PRE_AUTHORIZED === $this->moduleConfig->getTypeOfPayment()) {
            $data['transactionType'] = 'AUTH';
        }

        $this->logger->debug('[Create payment request]');
        $this->logger->debug(json_encode(json_decode($this->serializer->serialize($data)), JSON_PRETTY_PRINT));


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
            $this->logger->critical('[Exception] ' . $e->getMessage());

            return ['error' => true, 'orderId' => $data['orderId']];
        }

        $this->logger->debug('[Create payment response]');
        $this->logger->debug(json_encode(json_decode((string) $response->getBody()), JSON_PRETTY_PRINT));


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
