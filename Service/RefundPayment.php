<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Service\RefundPaymentInterface;

/**
 * Monei refund payment REST integration service class.
 */
class RefundPayment extends AbstractService implements RefundPaymentInterface
{
    public const METHOD = 'refund';

    /** @var array */
    private $requiredArguments = [
        'paymentId',
        'refundReason',
        'amount',
    ];

    /** @var array */
    private $refundReasons = [
        'duplicated',
        'fraudulent',
        'requested_by_customer',
    ];

    /**
     * Execute payment refund request.
     *
     * @param array $data Payment data including paymentId, refundReason, and amount
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
            'refundReason' => $data['refundReason'],
            'amount' => $data['amount'],
        ];

        $this->logger->debug('[Refund payment request]');
        $this->logger->debug(json_encode(json_decode($this->serializer->serialize($data)), JSON_PRETTY_PRINT));


        $storeId = $data['storeId'] ?? null;

        $client = $this->createClient($storeId);
        try {
            $response = $client->post(
                'payments/' . $data['paymentId'] . '/' . self::METHOD,
                [
                    'headers' => $this->getHeaders($storeId),
                    'json' => $requestBody,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->critical('[Exception] ' . $e->getMessage());
            return ['error' => true, 'errorMessage' => $e->getMessage()];
        }

        $this->logger->debug('[Refund payment response]');
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
            } elseif ('refundReason' === $argument && !\in_array($data[$argument], $this->refundReasons, true)) {
                throw new LocalizedException(
                    __(
                        '%1 doesn\'t match any allowed reasons %2',
                        $argument,
                        $this->serializer->serialize($this->refundReasons)
                    )
                );
            } elseif ('amount' === $argument && !is_numeric($data[$argument])) {
                throw new LocalizedException(
                    __(
                        '%1 should be numeric value',
                        $argument
                    )
                );
            }
        }
    }
}
