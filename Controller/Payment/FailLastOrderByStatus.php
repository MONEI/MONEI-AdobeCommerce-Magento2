<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Logger;

/**
 * Monei payment fail insite controller.
 */
class FailLastOrderByStatus extends Fail
{
    /**
     * Controller context.
     *
     * @var Context
     */
    private Context $context;

    /**
     * Session manager for handling customer checkout data.
     *
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * Constructor for FailLastOrderByStatus controller.
     *
     * @param Context $context Application context
     * @param Session $checkoutSession Checkout session
     * @param OrderInterfaceFactory $orderFactory Order factory
     * @param ManagerInterface $messageManager Message manager
     * @param MagentoRedirect $resultRedirectFactory Redirect factory
     * @param PaymentProcessor $paymentProcessor Payment processor
     * @param Logger $logger Logger
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderInterfaceFactory $orderFactory,
        ManagerInterface $messageManager,
        MagentoRedirect $resultRedirectFactory,
        PaymentProcessor $paymentProcessor,
        Logger $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->context = $context;
        parent::__construct(
            $context,
            $checkoutSession,
            $orderFactory,
            $messageManager,
            $resultRedirectFactory,
            $paymentProcessor,
            $logger
        );
    }

    /**
     * Execute the failure handling for the last real order.
     *
     * Takes the last real order from the checkout session and sets its
     * order ID in the request parameters before delegating to the parent
     * execute method for standard failure processing.
     *
     * @return MagentoRedirect Redirect to the appropriate page after failure handling
     */
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        $request = $this->context->getRequest();
        $params = $request->getParams();
        $params['orderId'] = $order->getIncrementId();
        $request->setParams($params);

        return parent::execute();
    }
}
