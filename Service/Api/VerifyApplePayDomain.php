<?php

/**
 * @category  Monei
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\ApplePayDomainRegister200Response;
use Monei\Model\RegisterApplePayDomainRequest;
use Monei\MoneiPayment\Api\Service\VerifyApplePayDomainInterface;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiClient;

/**
 * Service class for registering domain with Apple Pay using the official MONEI PHP SDK.
 */
class VerifyApplePayDomain extends AbstractApiService implements VerifyApplePayDomainInterface
{
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
     * Register a domain with Apple Pay via the Monei API
     *
     * @param string $domain Domain to register with Apple Pay
     * @param int|null $storeId The store ID to use for configurations
     * @return ApplePayDomainRegister200Response
     * @throws LocalizedException If registration fails
     */
    public function execute(string $domain, ?int $storeId = null): ApplePayDomainRegister200Response
    {
        // Validate the request parameters
        if (empty($domain)) {
            throw new LocalizedException(__('Domain is required for Apple Pay verification'));
        }

        // Create register request with SDK model
        $request = new RegisterApplePayDomainRequest(['domain_name' => $domain]);

        // Use standardized SDK call pattern with the executeMoneiSdkCall method
        return $this->executeMoneiSdkCall(
            'registerApplePayDomain',
            function (MoneiClient $moneiSdk) use ($request) {
                return $moneiSdk->applePayDomain->register($request);
            },
            ['domain' => $domain],
            $storeId
        );
    }
}
