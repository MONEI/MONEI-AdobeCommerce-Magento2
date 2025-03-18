<?php

/**
 * Copyright © Monei. All rights reserved.
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\ApplePayDomainRegister200Response;

/**
 * Interface for service to register a domain with Apple Pay
 */
interface VerifyApplePayDomainInterface
{
    /**
     * Register a domain with Apple Pay via the Monei API
     *
     * @param string $domain Domain to register with Apple Pay
     * @param int|null $storeId The store ID to use for configurations
     * @return ApplePayDomainRegister200Response
     * @throws LocalizedException If registration fails
     */
    public function execute(string $domain, ?int $storeId = null): ApplePayDomainRegister200Response;
}
