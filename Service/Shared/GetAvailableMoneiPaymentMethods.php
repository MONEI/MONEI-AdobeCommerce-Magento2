<?php

/**
 * @author Monei Team
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
    private array $availablePaymentMethods = [];
    private array $metadataPaymentMethods = [];

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

    public function execute(): array
    {
        if (!$this->availablePaymentMethods) {
            $availablePaymentMethods = $this->getPaymentMethodsService->execute();
            $this->availablePaymentMethods = $availablePaymentMethods['paymentMethods'] ?? [];
            $this->metadataPaymentMethods = $availablePaymentMethods['metadata'] ?? [];
        }

        return $this->availablePaymentMethods;
    }

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
