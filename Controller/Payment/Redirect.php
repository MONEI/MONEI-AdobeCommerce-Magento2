<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Monei payment redirect controller
 */
class Redirect implements ActionInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var CreatePaymentInterface
     */
    private $createPayment;

    /**
     * @var MagentoRedirect
     */
    private $resultRedirectFactory;

    /**
     * @param Session $checkoutSession
     * @param CreatePaymentInterface $createPayment
     * @param MagentoRedirect $resultRedirectFactory
     */
    public function __construct(
        Session $checkoutSession,
        CreatePaymentInterface $createPayment,
        MagentoRedirect $resultRedirectFactory
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->createPayment = $createPayment;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        /**
         * @var $order OrderInterface
         */
        $order = $this->checkoutSession->getLastRealOrder();
        $data = [
            "amount"   => $order->getGrandTotal() * 100,
            "orderId"  => (string) $order->getIncrementId(),
            "currency" => $order->getBaseCurrencyCode(),
        ];

        $result = $this->createPayment->execute($data);
        if (!isset($result['error']) && isset($result['nextAction']['redirectUrl'])) {
            $this->resultRedirectFactory->setUrl($result['nextAction']['redirectUrl']);
            return $this->resultRedirectFactory;
        }

        return $this->resultRedirectFactory->setPath(
            'monei/payment/fail',
            ['orderId' => $order->getEntityId()]
        );
    }
}
