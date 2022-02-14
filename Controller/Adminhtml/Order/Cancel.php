<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Adminhtml\Order;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Monei\MoneiPayment\Api\Service\CancelPaymentInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderManagementInterface;
use Psr\Log\LoggerInterface;

/**
 * Admin controller for cancel order with Monei payment.
 */
class Cancel extends Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var CancelPaymentInterface
     */
    private $cancelPaymentService;

    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::cancel';

    /**
     * @param Context                  $context
     * @param JsonFactory              $resultJsonFactory
     * @param CancelPaymentInterface   $cancelPaymentService
     * @param OrderManagementInterface $orderManagement
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CancelPaymentInterface $cancelPaymentService,
        OrderManagementInterface $orderManagement
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cancelPaymentService = $cancelPaymentService;
        $this->orderManagement = $orderManagement;
    }

    /**
     * @inerhitDoc
     */
    public function execute(): Json
    {
        $params = $this->getRequest()->getParams();
        /** @var Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        if (!isset($params['payment_id']) || !isset($params['cancel_reason']) || !isset($params['order_id'])) {
            $this->messageManager->addErrorMessage(__('You have not canceled the item.'));
            $url = $this->getUrl('sales/*/');
            $response = [
                'redirectUrl' => $url,
            ];

            return $resultJson->setData($response);
        }

        $data = [
            'paymentId' => $params['payment_id'],
            'cancellationReason' => $params['cancel_reason'],
        ];
        $response = $this->cancelPaymentService->execute($data);

        if (isset($response['error']) && $response['error'] === true) {
            $this->messageManager->addErrorMessage($response['errorMessage']);
            $url = $this->getUrl('sales/*/');
            $response = [
                'redirectUrl' => $url,
            ];

            return $resultJson->setData($response);
        }

        try {
            $this->orderManagement->cancel($params['order_id']);
            $this->messageManager->addSuccessMessage(__('You canceled the order.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('You have not canceled the item.'));
            $this->_objectManager->get(LoggerInterface::class)->critical($e);
        }

        $url = $this->getUrl('sales/order/view', ['order_id' => $params['order_id']]);
        $response = [
            'redirectUrl' => $url,
        ];

        return $resultJson->setData($response);
    }
}
