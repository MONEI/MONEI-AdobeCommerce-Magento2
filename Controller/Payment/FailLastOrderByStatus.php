<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Monei\MoneiPayment\Api\Service\SetOrderStatusAndStateInterface;

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
     * @param SetOrderStatusAndStateInterface $setOrderStatusAndStateService Service to set order status
     * @param ManagerInterface $messageManager Message manager
     * @param MagentoRedirect $resultRedirectFactory Redirect factory
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderInterfaceFactory $orderFactory,
        SetOrderStatusAndStateInterface $setOrderStatusAndStateService,
        ManagerInterface $messageManager,
        MagentoRedirect $resultRedirectFactory
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->context = $context;
        parent::__construct(
            $context,
            $checkoutSession,
            $orderFactory,
            $setOrderStatusAndStateService,
            $messageManager,
            $resultRedirectFactory
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
