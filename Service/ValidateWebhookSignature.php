<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Monei\MoneiPayment\Api\Service\ValidateWebhookSignatureInterface;

/**
 * Service for validating webhook signatures
 */
class ValidateWebhookSignature implements ValidateWebhookSignatureInterface
{
    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param Logger $logger
     */
    public function __construct(
        Logger $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function validate(string $payload, string $signature, string $secret): bool
    {
        try {
            if (empty($signature) || empty($secret)) {
                $this->logger->warning('[Webhook] Missing signature or secret');

                return false;
            }

            // Extract timestamp and signature from header
            $parts = explode(',', $signature);
            $timestamp = null;
            $signatureValue = null;

            foreach ($parts as $part) {
                $keyValue = explode('=', $part, 2);
                if (count($keyValue) !== 2) {
                    continue;
                }

                if ($keyValue[0] === 't') {
                    $timestamp = $keyValue[1];
                } elseif ($keyValue[0] === 'v1') {
                    $signatureValue = $keyValue[1];
                }
            }

            if (!$timestamp || !$signatureValue) {
                $this->logger->warning('[Webhook] Invalid signature format');

                return false;
            }

            // Verify timestamp to prevent replay attacks
            $now = time();
            if (abs($now - (int) $timestamp) > 300) {  // 5 minutes tolerance
                $this->logger->warning('[Webhook] Timestamp too old');

                return false;
            }

            // Verify signature
            $signedPayload = $timestamp . '.' . $payload;
            $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

            if (!hash_equals($expectedSignature, $signatureValue)) {
                $this->logger->warning('[Webhook] Signature verification failed');

                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[Webhook] Error verifying signature: ' . $e->getMessage(), ['exception' => $e]);

            return false;
        }
    }
}
