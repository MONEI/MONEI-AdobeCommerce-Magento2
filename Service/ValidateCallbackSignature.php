<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Monei\MoneiPayment\Api\Service\ValidateCallbackSignatureInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;

/**
 * Service for validating MONEI payment callback signatures
 */
class ValidateCallbackSignature implements ValidateCallbackSignatureInterface
{
    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $apiClient;

    /**
     * @param Logger $logger
     * @param MoneiApiClient $apiClient
     */
    public function __construct(
        Logger $logger,
        MoneiApiClient $apiClient
    ) {
        $this->logger = $logger;
        $this->apiClient = $apiClient;
    }

    /**
     * Validate callback signature
     *
     * @param string $payload The request body
     * @param string $signature The signature header
     * @param string $secret Optional callback secret
     *
     * @return bool True if signature is valid, false otherwise
     */
    public function validate(string $payload, string $signature, string $secret = ''): bool
    {
        $this->logger->debug('[Callback] Validating signature');

        if (empty($signature)) {
            $this->logger->warning('[Callback] Missing signature header');
            return false;
        }

        try {
            // If a secret is provided, use it directly
            if (!empty($secret)) {
                return $this->verifySignatureWithSecret($payload, $signature, $secret);
            }

            // Otherwise, get the webhook secret from the API client
            $webhookSecret = $this->apiClient->getWebhookSecret();
            if (empty($webhookSecret)) {
                $this->logger->warning('[Callback] No webhook secret available');
                return false;
            }

            return $this->verifySignatureWithSecret($payload, $signature, $webhookSecret);
        } catch (\Exception $e) {
            $this->logger->error('[Callback] Error validating signature: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return false;
        }
    }

    /**
     * Verify signature using the provided secret
     *
     * @param string $payload
     * @param string $signature
     * @param string $secret
     * @return bool
     */
    private function verifySignatureWithSecret(string $payload, string $signature, string $secret): bool
    {
        // Extract timestamp and signatures from the header
        if (!preg_match('/t=(\d+),v1=([a-f0-9]+)/', $signature, $matches)) {
            $this->logger->warning('[Callback] Invalid signature format');
            return false;
        }

        $timestamp = $matches[1];
        $signatureHash = $matches[2];

        // Check if the timestamp is too old (older than 5 minutes)
        $now = time();
        if ($now - (int)$timestamp > 300) {
            $this->logger->warning('[Callback] Signature timestamp too old');
            return false;
        }

        // Compute the expected signature
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        // Compare signatures
        $isValid = hash_equals($expectedSignature, $signatureHash);

        if (!$isValid) {
            $this->logger->warning('[Callback] Invalid signature');
        } else {
            $this->logger->debug('[Callback] Signature validated successfully');
        }

        return $isValid;
    }
}
