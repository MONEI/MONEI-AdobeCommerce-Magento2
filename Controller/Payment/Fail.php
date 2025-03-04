<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Logger;

/**
 * Monei payment fail controller.
 */
class Fail implements ActionInterface
{
    /**
     * Factory for creating order objects.
     *
     * @var OrderInterfaceFactory
     */
    protected $orderFactory;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var MagentoRedirect
     */
    private $resultRedirectFactory;

    /**
     * @var PaymentProcessor
     */
    private $paymentProcessor;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * Constructor for Fail controller.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderInterfaceFactory $orderFactory
     * @param ManagerInterface $messageManager
     * @param MagentoRedirect $resultRedirectFactory
     * @param PaymentProcessor $paymentProcessor
     * @param Logger $logger
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
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->paymentProcessor = $paymentProcessor;
        $this->logger = $logger;
    }

    /**
     * Execute action for payment failure handling.
     *
     * @return MagentoRedirect
     */
    public function execute()
    {
        $data = $this->context->getRequest()->getParams();
        if (isset($data['orderId'])) {
            try {
                // Create a payment DTO with failed status
                $paymentData = [
                    'id' => $data['paymentId'] ?? 'unknown',
                    'orderId' => $data['orderId'],
                    'status' => $data['status'] ?? Status::FAILED,
                    'amount' => $data['amount'] ?? 0,
                    'currency' => $data['currency'] ?? 'EUR'
                ];

                $payment = PaymentDTO::fromArray($paymentData);

                // Load the order
                $order = $this->orderFactory->create()->loadByIncrementId($data['orderId']);

                // Process the failed payment
                $this->paymentProcessor->processPayment($order, $payment);

                $this->logger->info(sprintf(
                    '[Payment failed] Order %s, payment %s',
                    $data['orderId'],
                    $paymentData['id']
                ));
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    '[Error processing failed payment] Order %s: %s',
                    $data['orderId'],
                    $e->getMessage()
                ));
            }

            $this->checkoutSession->restoreQuote();
            $this->messageManager->addErrorMessage(
                __('Something went wrong while processing the payment. Quote was restored.')
            );
        } else {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while processing the payment. Cannot restore previous quote.')
            );
        }

        return $this->resultRedirectFactory->setPath('checkout/cart', ['_secure' => true]);
    }
}
