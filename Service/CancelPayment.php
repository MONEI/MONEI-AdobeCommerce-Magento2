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

    public function execute(array $data): array
    {
        $this->logger->debug(__METHOD__);

        try {
            $this->validate($data);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        $requestBody = [
            'cancellationReason' => $data['cancellationReason'],
        ];

        $this->logger->debug('------------------ START CANCEL PAYMENT REQUEST -----------------');
        $this->logger->debug($this->serializer->serialize($data));
        $this->logger->debug('------------------- END CANCEL PAYMENT REQUEST ------------------');
        $this->logger->debug('');

        $client = $this->createClient();

        try {
            $response = $client->post(
                'payments/'.$data['paymentId'].'/'.self::METHOD,
                [
                    'headers' => $this->getHeaders(),
                    'json' => $requestBody,
                ]
            );
        } catch (\Exception $e) {
            return ['error' => true, 'errorMessage' => $e->getMessage()];
        }

        $this->logger->debug('----------------- START CANCEL PAYMENT RESPONSE -----------------');
        $this->logger->debug((string) $response->getBody());
        $this->logger->debug('------------------ END CANCEL PAYMENT RESPONSE ------------------');
        $this->logger->debug('');

        return $this->serializer->unserialize($response->getBody());
    }

    /**
     * Validate request required parameters.
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
