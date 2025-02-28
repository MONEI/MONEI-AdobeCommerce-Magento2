<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

class GetAvailableMoneiPaymentMethodsByCountry
{
    private GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods;

    public function __construct(GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods)
    {
        $this->getAvailableMoneiPaymentMethods = $getAvailableMoneiPaymentMethods;
    }

    public function execute(string $countryId): array
    {
        $allPaymentMethods = $this->getAvailableMoneiPaymentMethods->execute();
        $metadataPaymentMethods = $this->getAvailableMoneiPaymentMethods->getMetadataPaymentMethods();
        foreach ($allPaymentMethods as $index => $paymentMethod) {
            if (isset($metadataPaymentMethods[$paymentMethod])
                && isset($metadataPaymentMethods[$paymentMethod]['countries'])
            ) {
                $countriesAvailable = $metadataPaymentMethods[$paymentMethod]['countries'];
                if (!\in_array($countryId, $countriesAvailable, true)) {
                    unset($allPaymentMethods[$index]);
                }
            }
        }

        return $allPaymentMethods;
    }
}
