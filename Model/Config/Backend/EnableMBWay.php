<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Backend;

use Monei\MoneiPayment\Model\Payment\Monei;

class EnableMBWay extends Enable
{
    protected const PAYMENT_METHOD_CODE = Monei::MBWAY_REDIRECT_CODE;

    /**
     * @return EnableBizum
     */
    public function beforeSave(): EnableMBWay
    {
        if (!$this->isPaymentAvailable() && $this->getValue()) {
            $this->setValue("0");
            // phpcs:disable
            $this->messageManager->addNoticeMessage(
                __("MBWay payment method is not available in your Monei account. Please enable it in your Monei account to use it."));
            // phpcs:enable
        }

        return parent::beforeSave();
    }

    /**
     * @return bool
     */
    private function isPaymentAvailable(): bool
    {
        $availablePaymentMethods = $this->getAvailablePaymentMethods();
        $moneiPaymentCodes = $this->getMoneiPaymentCodesByMagentoPaymentCode(self::PAYMENT_METHOD_CODE);

        foreach ($moneiPaymentCodes as $moneiPaymentCode) {
            if (in_array($moneiPaymentCode, $availablePaymentMethods, true)) {
                return true;
            }
        }

        return false;
    }
}
