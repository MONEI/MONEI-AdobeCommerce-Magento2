<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Model\PendingOrder;
use Monei\MoneiPayment\Model\PendingOrderFactory;
use Monei\MoneiPayment\Model\ResourceModel\PendingOrder as PendingOrderResource;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Service\Order\CreateVaultPayment;
use Monei\MoneiPayment\Service\Order\PaymentProcessor;

/**
 * Monei payment complete controller.
 */
class Complete implements ActionInterface
{
    /** Controller source identifier. */
    private const SOURCE = 'complete';

    /**
     * Handles sending order confirmation emails.
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * Provides access to order data storage.
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * Creates new order instances.
     * @var OrderInterfaceFactory
     */
    private $orderFactory;

    /**
     * Provides configuration settings for the Monei payment module.
     * @var MoneiPaymentModuleConfigInterface
     */
    private $moduleConfig;

    /**
     * Service for generating invoices.
     * @var GenerateInvoiceInterface
     */
    private $generateInvoiceService;

    /**
     * Provides access to controller context data.
     * @var Context
     */
    private $context;

    /**
     * Creates redirect response objects.
     * @var MagentoRedirect
     */
    private $resultRedirectFactory;

    /**
     * Creates pending order instances.
     * @var PendingOrderFactory
     */
    private $pendingOrderFactory;

    /**
     * Manages persistence of pending order data.
     * @var PendingOrderResource
     */
    private $pendingOrderResource;

    /**
     * Service for creating vault payment records.
     * @var CreateVaultPayment
     */
    private $createVaultPayment;

    /**
     * Processes payment operations.
     * @var PaymentProcessor
     */
    private $paymentProcessor;

    /**
     * Provides logging functionality for the controller.
     * @var Logger
     */
    private $logger;

    /**
     * Constructor for Complete payment controller.
     *
     * Initializes the controller with all required dependencies for handling
     * the Monei payment completion process.
     *
     * @param Context $context Application context
     * @param OrderRepositoryInterface $orderRepository Repository for order operations
     * @param OrderSender $orderSender Service for sending order confirmation emails
     * @param OrderInterfaceFactory $orderFactory Factory for creating order objects
     * @param MoneiPaymentModuleConfigInterface $moduleConfig Monei payment module configuration
     * @param GenerateInvoiceInterface $generateInvoiceService Service for generating invoices
     * @param MagentoRedirect $resultRedirectFactory Factory for creating redirect responses
     * @param PendingOrderFactory $pendingOrderFactory Factory for creating pending order objects
     * @param PendingOrderResource $pendingOrderResource Resource model for pending orders
     * @param CreateVaultPayment $createVaultPayment Service for creating vault payments
     * @param PaymentProcessor $paymentProcessor Service for processing payments
     * @param Logger $logger Logging service
     */
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        OrderSender $orderSender,
        /** @phpstan-ignore-next-line */
        OrderInterfaceFactory $orderFactory,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        GenerateInvoiceInterface $generateInvoiceService,
        MagentoRedirect $resultRedirectFactory,
        /** @phpstan-ignore-next-line */
        PendingOrderFactory $pendingOrderFactory,
        PendingOrderResource $pendingOrderResource,
        CreateVaultPayment $createVaultPayment,
        PaymentProcessor $paymentProcessor,
        Logger $logger
    ) {
        $this->createVaultPayment = $createVaultPayment;
        $this->context = $context;
        $this->orderRepository = $orderRepository;
        $this->orderSender = $orderSender;
        $this->orderFactory = $orderFactory;
        $this->moduleConfig = $moduleConfig;
        $this->generateInvoiceService = $generateInvoiceService;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->pendingOrderFactory = $pendingOrderFactory;
        $this->pendingOrderResource = $pendingOrderResource;
        $this->paymentProcessor = $paymentProcessor;
        $this->logger = $logger;
    }

    /**
     * Execute payment completion logic.
     *
     * Processes the payment result from Monei based on the received parameters.
     * Redirects to success page if payment was successful or to cart if it wasn't.
     *
     * @return MagentoRedirect Redirects to success page or cart based on payment status
     */
    public function execute()
    {
        $data = $this->context->getRequest()->getParams();

        // Check if we have the required parameters
        if (!isset($data['status'], $data['orderId'])) {
            $this->logger->error('Complete controller called with missing parameters');

            return $this->resultRedirectFactory->setPath('checkout/cart', ['_secure' => true]);
        }

        try {
            $processed = $this->paymentProcessor->processPayment($data, self::SOURCE);

            // Redirect based on payment status, even if processing failed
            // (we want to show the appropriate page to the customer)
            if (Monei::ORDER_STATUS_AUTHORIZED === $data['status']
                || Monei::ORDER_STATUS_SUCCEEDED === $data['status']
            ) {
                return $this->resultRedirectFactory->setPath(
                    'checkout/onepage/success',
                    ['_secure' => true]
                );
            }

            return $this->resultRedirectFactory->setPath(
                'checkout/cart',
                ['_secure' => true]
            );
        } catch (\Exception $e) {
            $this->logger->error('Error in Complete controller: ' . $e->getMessage());

            return $this->resultRedirectFactory->setPath('checkout/cart', ['_secure' => true]);
        }
    }
}
