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
class AvailablePaymentMethods
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
     * AvailablePaymentMethods constructor.
     *
     * @param GetPaymentMethodsInterface $getPaymentMethodsService
     */
    public function __construct(
        GetPaymentMethodsInterface $getPaymentMethodsService
    ) {
        $this->getPaymentMethodsService = $getPaymentMethodsService;
    }

    /**
     * Load payment methods data from API if not already loaded
     *
     * @return void
     */
    private function loadData(): void
    {
        if (!$this->availablePaymentMethods && !$this->metadataPaymentMethods) {
            /** @var PaymentMethods $response */
            $response = $this->getPaymentMethodsService->execute();
            $this->availablePaymentMethods = $response->getPaymentMethods() ?? [];
            $this->metadataPaymentMethods = $response->getMetadata() ?? [];
        }
    }

    /**
     * Get available payment methods.
     *
     * @return array List of available payment methods
     */
    public function execute(): array
    {
        $this->loadData();
        return $this->availablePaymentMethods;
    }

    /**
     * Get metadata for payment methods.
     *
     * @return \Monei\Model\PaymentMethodsMetadata|array Metadata for payment methods
     */
    public function getMetadataPaymentMethods()
    {
        $this->loadData();
        return $this->metadataPaymentMethods;
    }
}
