<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

use Monei\Model\PaymentMethods;
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
     * @var \Monei\Model\PaymentMethodsMetadata|array
     */
    private $metadataPaymentMethods = [];

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
            /** @var PaymentMethods $response */
            $response = $this->getPaymentMethodsService->execute();
            $this->availablePaymentMethods = $response->getPaymentMethods() ?? [];
            $this->metadataPaymentMethods = $response->getMetadata() ?? [];
        }

        return $this->availablePaymentMethods;
    }

    /**
     * Get metadata for payment methods.
     *
     * @return \Monei\Model\PaymentMethodsMetadata|array Metadata for payment methods
     */
    public function getMetadataPaymentMethods()
    {
        if (!$this->metadataPaymentMethods) {
            /** @var PaymentMethods $response */
            $response = $this->getPaymentMethodsService->execute();
            $this->availablePaymentMethods = $response->getPaymentMethods() ?? [];
            $this->metadataPaymentMethods = $response->getMetadata() ?? [];
        }

        return $this->metadataPaymentMethods;
    }
}
