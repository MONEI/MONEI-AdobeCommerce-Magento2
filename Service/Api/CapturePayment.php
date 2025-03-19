<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\CapturePaymentRequest;
use Monei\Model\Payment;
use Monei\MoneiPayment\Api\Service\CapturePaymentInterface;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiClient;

/**
 * Monei capture payment service class using the official MONEI PHP SDK.
 */
class CapturePayment extends AbstractApiService implements CapturePaymentInterface
{
    /**
     * List of required parameters for capture request.
     *
     * @var array
     */
    private $requiredArguments = [
        'paymentId',
        'amount',
    ];

    /**
     * @param Logger $logger
     * @param ApiExceptionHandler $exceptionHandler
     * @param MoneiApiClient $apiClient
     */
    public function __construct(
        Logger $logger,
        ApiExceptionHandler $exceptionHandler,
        MoneiApiClient $apiClient
    ) {
        parent::__construct($logger, $exceptionHandler, $apiClient);
    }

    /**
     * Execute a payment capture request to the Monei API.
     *
     * Captures an authorized payment using the official MONEI SDK.
     * Requires a payment ID and amount to capture.
     *
     * @param array $data Data for the capture request containing paymentId and amount
     *
     * @return Payment MONEI SDK Payment object
     * @throws LocalizedException If the payment cannot be captured
     */
    public function execute(array $data): Payment
    {
        // Validate the request parameters
        $this->validateParams($data, $this->requiredArguments, [
            'amount' => function ($value) {
                return is_numeric($value);
            }
        ]);

        // Create capture request with SDK model
        $captureRequest = new CapturePaymentRequest();

        // Set amount in cents
        if (isset($data['amount'])) {
            $captureRequest->setAmount((int) ($data['amount'] * 100));
        }

        // Use standardized SDK call pattern with the executeMoneiSdkCall method
        return $this->executeMoneiSdkCall(
            'capturePayment',
            function (MoneiClient $moneiSdk) use ($data, $captureRequest) {
                return $moneiSdk->payments->capture($data['paymentId'], $captureRequest);
            },
            $data
        );
    }
}
