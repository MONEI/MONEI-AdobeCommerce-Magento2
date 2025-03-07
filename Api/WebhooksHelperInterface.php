<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api;

use Magento\Framework\App\RequestInterface;

/**
 * Interface for webhook handling
 */
interface WebhooksHelperInterface
{
    /**
     * Process a webhook event
     *
     * @param RequestInterface $request
     * @return void
     */
    public function processWebhook(RequestInterface $request): void;

    /**
     * Verify webhook signature
     *
     * @param string $payload
     * @param array $headers
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, array $headers): bool;

    /**
     * Dispatch webhook event to appropriate handler
     *
     * @param array $eventData
     * @return void
     */
    public function dispatchEvent(array $eventData): void;
}
