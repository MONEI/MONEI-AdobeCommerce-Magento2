<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

/**
 * Interface for validating webhook signatures
 */
interface ValidateWebhookSignatureInterface
{
    /**
     * Validate a webhook signature
     *
     * @param string $payload The webhook payload
     * @param string $signature The signature header value
     * @param string $secret The webhook secret
     * @return bool True if the signature is valid, false otherwise
     */
    public function validate(string $payload, string $signature, string $secret): bool;
}
