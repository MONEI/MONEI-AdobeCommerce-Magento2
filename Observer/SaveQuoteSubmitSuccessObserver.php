<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Model\Payment\Monei;

class SaveQuoteSubmitSuccessObserver implements ObserverInterface
{
    /**
     * @param Observer $observer
     * @return SaveQuoteSubmitSuccessObserver
     */
    public function execute(Observer $observer): SaveQuoteSubmitSuccessObserver
    {
        /* @var Order $order */
        $order = $observer->getEvent()->getData('order');

        if (\in_array($order->getPayment()->getMethod(), Monei::PAYMENT_METHODS_MONEI, true)) {
            $order->setCanSendNewEmailFlag(true);
        }

        return $this;
    }
}
