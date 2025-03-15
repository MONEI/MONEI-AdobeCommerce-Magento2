<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Observer;

use Magento\Framework\DataObject\Copy;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Model\Payment\Monei;

class SaveOrderBeforeSalesModelQuoteObserver implements ObserverInterface
{
    /**
     * Service for copying data between objects.
     *
     * @var Copy
     */
    private Copy $objectCopyService;

    /**
     * Constructor.
     *
     * @param Copy $objectCopyService
     */
    public function __construct(Copy $objectCopyService)
    {
        $this->objectCopyService = $objectCopyService;
    }

    /**
     * Execute observer to copy payment data from quote to order.
     *
     * @param Observer $observer
     *
     * @return SaveOrderBeforeSalesModelQuoteObserver
     */
    public function execute(Observer $observer): SaveOrderBeforeSalesModelQuoteObserver
    {
        // @var Order $order
        $order = $observer->getEvent()->getData('order');
        // @var Quote $quote
        $quote = $observer->getEvent()->getData('quote');

        $this->objectCopyService->copyFieldsetToTarget('sales_convert_quote', 'to_order', $quote, $order);

        if (\in_array($order->getPayment()->getMethod(), Monei::PAYMENT_METHODS_MONEI, true)) {
            $order->setCanSendNewEmailFlag(false);
        }

        return $this;
    }
}
