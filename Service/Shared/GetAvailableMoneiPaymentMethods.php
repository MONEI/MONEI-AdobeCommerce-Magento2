<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

use Monei\MoneiPayment\Api\Service\GetPaymentMethodsInterface;

/**
 * Get Monei payment method configuration class.
 */
class GetAvailableMoneiPaymentMethods
{
    /**
     * Collection of payment methods supported by Monei.
     *
     * @var array
     */
    private array $availablePaymentMethods = [];

    /**
     * Metadata for payment methods.
     *
     * @var array
     */
    private array $metadataPaymentMethods = [];

    /**
     * Payment methods service.
     *
     * @var GetPaymentMethodsInterface
     */
    private GetPaymentMethodsInterface $getPaymentMethodsService;

    /**
     * GetAvailableMoneiPaymentMethods constructor.
     *
     * @param GetPaymentMethodsInterface $getPaymentMethodsService
     */
    public function __construct(
        GetPaymentMethodsInterface $getPaymentMethodsService
    ) {
        $this->getPaymentMethodsService = $getPaymentMethodsService;
    }

    /**
     * Get available payment methods.
     *
     * @return array List of available payment methods
     */
    public function execute(): array
    {
        if (!$this->availablePaymentMethods) {
            $availablePaymentMethods = $this->getPaymentMethodsService->execute();
            $this->availablePaymentMethods = $availablePaymentMethods['paymentMethods'] ?? [];
            $this->metadataPaymentMethods = $availablePaymentMethods['metadata'] ?? [];
        }

        return $this->availablePaymentMethods;
    }

    /**
     * Get metadata for payment methods.
     *
     * @return array Metadata for payment methods
     */
    public function getMetadataPaymentMethods(): array
    {
        if (!$this->metadataPaymentMethods) {
            $availablePaymentMethods = $this->getPaymentMethodsService->execute();
            $this->availablePaymentMethods = $availablePaymentMethods['paymentMethods'] ?? [];
            $this->metadataPaymentMethods = $availablePaymentMethods['metadata'] ?? [];
        }

        return $this->metadataPaymentMethods;
    }
}
