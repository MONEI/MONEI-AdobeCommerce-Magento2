<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Service\CancelPaymentInterface;

/**
 * Monei cancel payment REST integration service class.
 */
class CancelPayment extends AbstractService implements CancelPaymentInterface
{
    public const METHOD = 'cancel';

    /** @var array */
    private $requiredArguments = [
        'paymentId',
        'cancellationReason',
    ];

    /** @var array */
    private $cancellationReasons = [
        'duplicated',
        'fraudulent',
        'requested_by_customer',
    ];

    /**
     * Execute payment cancellation request.
     *
     * @param array $data Payment data including paymentId and cancellationReason
     * @return array Response from the Monei API
     * @throws LocalizedException If validation fails
     */
    public function execute(array $data): array
    {
        $this->logger->debug('[Method] ' . __METHOD__);

        try {
            $this->validate($data);
        } catch (\Exception $e) {
            $this->logger->critical('[Exception] ' . $e->getMessage());
        }

        $requestBody = [
            'cancellationReason' => $data['cancellationReason']
        ];

        $this->logger->debug('[Cancel payment request]');
        $this->logger->debug(json_encode(json_decode($this->serializer->serialize($data)), JSON_PRETTY_PRINT));


        $client = $this->createClient();

        try {
            $response = $client->post(
                'payments/' . $data['paymentId'] . '/' . self::METHOD,
                [
                    'headers' => $this->getHeaders(),
                    'json' => $requestBody,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->critical('[Exception] ' . $e->getMessage());
            return ['error' => true, 'errorMessage' => $e->getMessage()];
        }

        $this->logger->debug('[Cancel payment response]');
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
            if ('cancellationReason' === $argument && !\in_array($data[$argument], $this->cancellationReasons, true)) {
                throw new LocalizedException(
                    __(
                        '%1 doesn\'t match any allowed reasons %2',
                        $argument,
                        $this->serializer->serialize($this->cancellationReasons)
                    )
                );
            }
        }
    }
}
