<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

/**
 * Service to get available Monei payment methods by country.
 */
class CountryPaymentMethods
{
    /**
     * Service to get all available Monei payment methods.
     *
     * @var AvailablePaymentMethods
     */
    private AvailablePaymentMethods $availablePaymentMethods;

    /**
     * Constructor.
     *
     * @param AvailablePaymentMethods $availablePaymentMethods
     */
    public function __construct(AvailablePaymentMethods $availablePaymentMethods)
    {
        $this->availablePaymentMethods = $availablePaymentMethods;
    }

    /**
     * Get available payment methods for specific country.
     *
     * @param string $countryId Country code
     *
     * @return array Available payment methods
     */
    public function execute(string $countryId): array
    {
        $allPaymentMethods = $this->availablePaymentMethods->execute();
        $metadataPaymentMethods = $this->availablePaymentMethods->getMetadataPaymentMethods();
        foreach ($allPaymentMethods as $index => $paymentMethod) {
            if (isset($metadataPaymentMethods[$paymentMethod], $metadataPaymentMethods[$paymentMethod]['countries'])) {
                $countriesAvailable = $metadataPaymentMethods[$paymentMethod]['countries'];
                if (!\in_array($countryId, $countriesAvailable, true)) {
                    unset($allPaymentMethods[$index]);
                }
            }
        }

        return $allPaymentMethods;
    }
}
