<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
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

    /** @var OrderSender */
    protected $orderSender;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var OrderInterfaceFactory */
    private $orderFactory;

    /** @var MoneiPaymentModuleConfigInterface */
    private $moduleConfig;

    /** @var GenerateInvoiceInterface */
    private $generateInvoiceService;

    /** @var Context */
    private $context;

    /** @var MagentoRedirect */
    private $resultRedirectFactory;

    /** @var PendingOrderFactory */
    private $pendingOrderFactory;

    /** @var PendingOrderResource */
    private $pendingOrderResource;

    /** @var CreateVaultPayment */
    private $createVaultPayment;

    /** @var PaymentProcessor */
    private $paymentProcessor;

    /** @var Logger */
    private $logger;

    /**
     * @param OrderInterfaceFactory $orderFactory        Class from Magento\Sales\Api\Data namespace
     * @param PendingOrderFactory   $pendingOrderFactory Class from Monei\MoneiPayment\Model namespace
     */
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        OrderSender $orderSender,
        OrderInterfaceFactory $orderFactory,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        GenerateInvoiceInterface $generateInvoiceService,
        MagentoRedirect $resultRedirectFactory,
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
            $this->logger->error('Error in Complete controller: '.$e->getMessage());

            return $this->resultRedirectFactory->setPath('checkout/cart', ['_secure' => true]);
        }
    }
}
