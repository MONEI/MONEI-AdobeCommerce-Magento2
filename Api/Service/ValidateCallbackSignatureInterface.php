<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

/**
 * Interface for validating MONEI payment callback signatures
 */
interface ValidateCallbackSignatureInterface
{
    /**
     * Validate callback signature
     *
     * @param string $payload The request body
     * @param string $signature The signature header
     * @param string $secret Optional callback secret
     *
     * @return bool True if signature is valid, false otherwise
     */
    public function validate(string $payload, string $signature, string $secret = ''): bool;
}
