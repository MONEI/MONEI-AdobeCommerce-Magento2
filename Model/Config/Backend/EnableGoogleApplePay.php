<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Backend;

use Monei\MoneiPayment\Model\Payment\Monei;

class EnableGoogleApplePay extends Enable
{
    protected const PAYMENT_METHOD_CODE = Monei::GOOGLE_APPLE_CODE;

    /**
     * Process value before saving.
     *
     * @return EnableGoogleApplePay
     */
    public function beforeSave(): EnableGoogleApplePay
    {
        if ($this->getValue() && !$this->isPaymentAvailable()) {
            $this->setValue('0');
            // phpcs:disable
            $this->messageManager->addNoticeMessage(
                __('Google Pay and Apple Pay payment methods are not available in your Monei account. Enable at least one of them in your Monei account to use it.')
            );
            // phpcs:enable
        }

        return parent::beforeSave();
    }

    /**
     * Check if payment method is available in Monei account
     *
     * @return bool
     */
    private function isPaymentAvailable(): bool
    {
        $availablePaymentMethods = $this->getAvailablePaymentMethods();
        $moneiPaymentCodes = $this->getMoneiPaymentCodesByMagentoPaymentCode(self::PAYMENT_METHOD_CODE);

        foreach ($moneiPaymentCodes as $moneiPaymentCode) {
            if (\in_array($moneiPaymentCode, $availablePaymentMethods, true)) {
                return true;
            }
        }

        return false;
    }
}
