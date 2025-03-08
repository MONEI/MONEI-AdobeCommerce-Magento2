<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Monei\MoneiPayment\Api\Service\CancelPaymentInterface;
use Psr\Log\LoggerInterface;

/**
 * Admin controller for cancel order with Monei payment.
 */
class Cancel extends Action
{
    /**
     * Authorization level of a basic admin session.
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_Sales::cancel';

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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * Constructor for Cancel controller.
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param CancelPaymentInterface $cancelPaymentService
     * @param OrderManagementInterface $orderManagement
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CancelPaymentInterface $cancelPaymentService,
        OrderManagementInterface $orderManagement,
        LoggerInterface $logger,
        SerializerInterface $serializer
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cancelPaymentService = $cancelPaymentService;
        $this->orderManagement = $orderManagement;
        $this->logger = $logger;
        $this->serializer = $serializer;
    }

    /**
     * @inheritdoc
     *
     * @return Json
     */
    public function execute(): Json
    {
        $params = $this->getRequest()->getParams();

        /** @var Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        if (!isset($params['payment_id']) || !isset($params['cancel_reason']) || !isset($params['order_id'])) {
            $this->messageManager->addErrorMessage(__('Required parameters are missing. Cannot proceed with cancellation.'));
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

        try {
            $response = $this->cancelPaymentService->execute($data);

            // Log the response from the cancel payment service
            $this->logger->debug('[Cancel payment controller]');
            $this->logger->debug(sprintf('[Order ID] %s', $params['order_id']));
            $this->logger->debug('[Cancel payment response]');
            $this->logger->debug(json_encode($response, JSON_PRETTY_PRINT));

            if (isset($response['error']) && true === $response['error']) {
                $errorMessage = isset($response['errorMessage'])
                    ? __('MONEI payment cancellation failed: %1', $response['errorMessage'])
                    : __('MONEI payment cancellation failed. Please check the logs for more details.');
                $this->messageManager->addErrorMessage($errorMessage);
                $url = $this->getUrl('sales/order/view', ['order_id' => $params['order_id']]);

                return $resultJson->setData(['redirectUrl' => $url]);
            }

            try {
                $this->orderManagement->cancel($params['order_id']);
                $this->messageManager->addSuccessMessage(__('The order has been successfully canceled.'));
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Order cancellation failed: %1', $e->getMessage()));
                $this->logger->critical('[Order Cancel Exception] ' . $e->getMessage());
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->critical('[LocalizedException] ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An unexpected error occurred during payment cancellation. Please check the logs for more details.'));
            $this->logger->critical('[Exception] ' . $e->getMessage());
        }

        $url = $this->getUrl('sales/order/view', ['order_id' => $params['order_id']]);
        $response = [
            'redirectUrl' => $url,
        ];

        return $resultJson->setData($response);
    }
}
