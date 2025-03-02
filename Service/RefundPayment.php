<?php

/**
 * @author Monei Team
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

    /**
     * @var array
     */
    private $requiredArguments = [
        'paymentId',
        'refundReason',
        'amount',
    ];

    /**
     * @var array
     */
    private $refundReasons = [
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
            'refundReason' => $data['refundReason'],
            'amount' => $data['amount'],
        ];

        $this->logger->debug('------------------ START REFUND PAYMENT REQUEST -----------------');
        $this->logger->debug($this->serializer->serialize($data));
        $this->logger->debug('------------------- END REFUND PAYMENT REQUEST ------------------');
        $this->logger->debug('');

        $storeId = $data['storeId'] ?? null;

        $client = $this->createClient($storeId);
        $response = $client->post(
            'payments/'.$data['paymentId'].'/'.self::METHOD,
            [
                'headers' => $this->getHeaders($storeId),
                'json' => $requestBody,
            ]
        );

        $this->logger->debug('----------------- START REFUND PAYMENT RESPONSE -----------------');
        $this->logger->debug((string) $response->getBody());
        $this->logger->debug('------------------ END REFUND PAYMENT RESPONSE ------------------');
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
