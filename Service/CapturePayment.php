<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Service\CapturePaymentInterface;

/**
 * Monei capture payment REST integration service class.
 */
class CapturePayment extends AbstractService implements CapturePaymentInterface
{
    public const METHOD = 'capture';

    /**
     * @var array
     */
    private $requiredArguments = [
        'paymentId',
        'amount',
    ];

    /**
     * @inheritDoc
     */
    public function execute(array $data): array
    {
        $this->logger->debug(__METHOD__);
        try {
            $this->validate($data);
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        $requestBody = [
            'amount' => $data['amount']
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
                    'json'    => $requestBody,
                ]
            );
        } catch (Exception $e) {
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
     * @param array $data
     * @return void
     */
    private function validate(array $data): void
    {
        foreach ($this->requiredArguments as $argument) {
            if (!isset($data[$argument]) || null === $data[$argument]) {
                $this->throwRequiredArgumentException($argument);
            } elseif ($argument === 'amount' && !is_numeric($data[$argument])) {
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
