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

    /**
     * @var array
     */
    private $requiredArguments = [
        'paymentId',
        'cancellationReason',
    ];

    /**
     * @var array
     */
    private $cancellationReasons = [
        'duplicated',
        'fraudulent',
        'requested_by_customer',
    ];

    /**
     * Execute payment cancellation request.
     *
     * @param array $data Payment data including paymentId and cancellationReason
     *
     * @throws LocalizedException If validation fails
     *
     * @return array Response from the Monei API
     */
    public function execute(array $data): array
    {
        $this->logger->debug('[Method] ' . __METHOD__);

        try {
            $this->validate($data);
        } catch (\Exception $e) {
            $this->logger->critical('[Exception] ' . $e->getMessage());

            return ['error' => true, 'errorMessage' => $e->getMessage()];
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

            $responseBody = (string) $response->getBody();
            $this->logger->debug('[Cancel payment response]');
            $this->logger->debug(json_encode(json_decode($responseBody), JSON_PRETTY_PRINT));

            return $this->serializer->unserialize($responseBody);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->logger->critical('[RequestException] ' . $e->getMessage());
            if ($e->hasResponse()) {
                $errorResponse = (string) $e->getResponse()->getBody();
                $this->logger->critical('[Error Response] ' . $errorResponse);

                try {
                    $errorData = $this->serializer->unserialize($errorResponse);

                    return ['error' => true, 'errorMessage' => $errorData['message'] ?? $e->getMessage(), 'errorData' => $errorData];
                } catch (\Exception $deserializeException) {
                    $this->logger->critical('[Deserialize Exception] ' . $deserializeException->getMessage());
                }
            }

            return ['error' => true, 'errorMessage' => $e->getMessage()];
        } catch (\Exception $e) {
            $this->logger->critical('[Exception] ' . $e->getMessage());

            return ['error' => true, 'errorMessage' => $e->getMessage()];
        }
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
                        "Cancellation reason '%1' is not valid. Allowed reasons: %2",
                        $data[$argument],
                        implode(', ', $this->cancellationReasons)
                    )
                );
            }
        }
    }
}
