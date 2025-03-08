<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

use Magento\Framework\App\RequestInterface;

/**
 * Interface for callback helper service
 */
interface CallbackHelperInterface
{
    /**
     * Process a payment callback request
     *
     * @param RequestInterface $request
     * @return void
     */
    public function processCallback(RequestInterface $request): void;

    /**
     * Verify the signature of a callback
     *
     * @param string $payload The raw request body
     * @param string $signature The signature from the request header
     * @return bool True if signature is valid, false otherwise
     */
    public function verifyCallbackSignature(string $payload, string $signature): bool;

    /**
     * Dispatch payment callback events
     *
     * @param array $eventData
     * @return void
     */
    public function dispatchEvent(array $eventData): void;
}
