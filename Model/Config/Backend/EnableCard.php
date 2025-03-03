<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Backend;

use Monei\MoneiPayment\Model\Payment\Monei;

class EnableCard extends Enable
{
    protected const PAYMENT_METHOD_CODE = Monei::CARD_CODE;

    /**
     * Process value before saving.
     *
     * @return EnableCard
     */
    public function beforeSave(): EnableCard
    {
        if ($this->getValue() && !$this->isPaymentAvailable()) {
            $this->setValue('0');
            // phpcs:disable
            $this->messageManager->addNoticeMessage(
                __('Card payment method is not available in your Monei account. Please enable it in your Monei account to use it.')
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
