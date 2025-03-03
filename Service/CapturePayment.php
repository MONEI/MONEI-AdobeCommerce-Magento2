<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Service\CapturePaymentInterface;
use Magento\Framework\Phrase;

/**
 * Monei capture payment REST integration service class.
 */
class CapturePayment extends AbstractService implements CapturePaymentInterface
{
    /**
     * API method name for capturing payments.
     */
    public const METHOD = 'capture';

    /**
     * List of required parameters for capture request.
     * @var array
     */
    private $requiredArguments = [
        'paymentId',
        'amount',
    ];

    /**
     * Execute a payment capture request to the Monei API.
     *
     * Captures an authorized payment by sending a request to the Monei payment API.
     * Requires a payment ID and amount to capture.
     *
     * @param array $data Data for the capture request containing paymentId and amount
     * @return array Response from the API with capture results or error details
     */
    public function execute(array $data): array
    {
        $this->logger->debug(__METHOD__);

        try {
            $this->validate($data);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        $requestBody = [
            'amount' => $data['amount'],
        ];

        $this->logger->debug('------------------ START CAPTURE PAYMENT REQUEST -----------------');
        $this->logger->debug($this->serializer->serialize($data));
        $this->logger->debug('------------------- END CAPTURE PAYMENT REQUEST ------------------');
        $this->logger->debug('');

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
            return ['error' => true, 'errorMessage' => $e->getMessage()];
        }

        $this->logger->debug('----------------- START CAPTURE PAYMENT RESPONSE -----------------');
        $this->logger->debug((string) $response->getBody());
        $this->logger->debug('------------------ END CAPTURE PAYMENT RESPONSE ------------------');
        $this->logger->debug('');

        return $this->serializer->unserialize($response->getBody());
    }

    /**
     * Validate request required parameters.
     *
     * Checks if all required parameters are present and have appropriate values.
     * Throws exceptions if validation fails.
     *
     * @param array $data Request data to validate
     * @throws LocalizedException If validation fails
     */
    private function validate(array $data): void
    {
        foreach ($this->requiredArguments as $argument) {
            if (!isset($data[$argument]) || null === $data[$argument]) {
                $this->throwRequiredArgumentException($argument);
            } elseif ('amount' === $argument && !is_numeric($data[$argument])) {
                throw new LocalizedException(
                    new Phrase('%1 should be numeric value', [$argument])
                );
            }
        }
    }
}
