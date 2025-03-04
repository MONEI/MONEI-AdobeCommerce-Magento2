<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Api\Service\ValidateWebhookSignatureInterface;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Model\PaymentDataProvider\WebhookPaymentDataProvider;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Logger;
use Exception;

/**
 * Controller for managing callback from Monei system
 */
class Callback implements CsrfAwareActionInterface, HttpPostActionInterface
{
    /**
     * @var string
     */
    private string $errorMessage = '';

    /**
     * @var Context
     */
    private Context $context;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var WebhookPaymentDataProvider
     */
    private WebhookPaymentDataProvider $webhookPaymentDataProvider;

    /**
     * @var GenerateInvoiceInterface
     */
    private GenerateInvoiceInterface $generateInvoiceService;

    /**
     * @var MagentoRedirect
     */
    private MagentoRedirect $resultRedirectFactory;

    /**
     * @var PaymentProcessor
     */
    private PaymentProcessor $paymentProcessor;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var ValidateWebhookSignatureInterface
     */
    private ValidateWebhookSignatureInterface $validateWebhookSignatureService;

    /**
     * @param Context $context
     * @param Logger $logger
     * @param WebhookPaymentDataProvider $webhookPaymentDataProvider
     * @param GenerateInvoiceInterface $generateInvoiceService
     * @param MagentoRedirect $resultRedirectFactory
     * @param PaymentProcessor $paymentProcessor
     * @param OrderRepositoryInterface $orderRepository
     * @param ValidateWebhookSignatureInterface $validateWebhookSignatureService
     */
    public function __construct(
        Context $context,
        Logger $logger,
        WebhookPaymentDataProvider $webhookPaymentDataProvider,
        GenerateInvoiceInterface $generateInvoiceService,
        MagentoRedirect $resultRedirectFactory,
        PaymentProcessor $paymentProcessor,
        OrderRepositoryInterface $orderRepository,
        ValidateWebhookSignatureInterface $validateWebhookSignatureService
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->generateInvoiceService = $generateInvoiceService;
        $this->logger = $logger;
        $this->context = $context;
        $this->webhookPaymentDataProvider = $webhookPaymentDataProvider;
        $this->paymentProcessor = $paymentProcessor;
        $this->orderRepository = $orderRepository;
        $this->validateWebhookSignatureService = $validateWebhookSignatureService;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        try {
            $content = $this->context->getRequest()->getContent();
            $signatureHeader = $this->context->getRequest()->getHeader('MONEI-Signature');

            // Log the entire request for debugging purposes
            $this->logger->debug('[Callback controller]');
            $this->logger->debug('[Request body] ' . json_encode(json_decode($content), JSON_PRETTY_PRINT));
            $this->logger->debug('[Signature header] ' . $signatureHeader);

            // Extract payment data from webhook
            $paymentData = $this->webhookPaymentDataProvider->extractFromWebhook($content, $signatureHeader);

            // Process the payment
            $this->processPayment($paymentData);

            return $this->resultRedirectFactory->setPath('/');
        } catch (Exception $e) {
            $this->logger->critical('[Callback error] ' . $e->getMessage());
            $this->logger->critical('[Request body] ' . ($content ?? 'No content'));

            return $this->resultRedirectFactory->setPath('/');
        }
    }

    /**
     * Process the payment data
     *
     * @param PaymentDTO $paymentData
     * @return void
     */
    private function processPayment(PaymentDTO $paymentData): void
    {
        try {
            // Get the order from the repository
            $orderId = $paymentData->getOrderId();
            if (!$orderId) {
                $this->logger->critical('[Payment processing error] No order ID in payment data', [
                    'payment_id' => $paymentData->getId()
                ]);
                return;
            }

            try {
                $order = $this->orderRepository->get($orderId);
                // Use the payment processor to handle the payment
                $this->paymentProcessor->processPayment($order, $paymentData);
            } catch (NoSuchEntityException $e) {
                $this->logger->critical('[Payment processing error] Order not found', [
                    'payment_id' => $paymentData->getId(),
                    'order_id' => $orderId
                ]);
            }
        } catch (Exception $e) {
            $this->logger->critical('[Payment processing error] ' . $e->getMessage(), [
                'payment_id' => $paymentData->getId(),
                'order_id' => $paymentData->getOrderId()
            ]);
        }
    }

    /**
     * @inheritdoc
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        /** @var ResponseHttp $response */
        $response = $this->context->getResponse();
        $response->setHttpResponseCode(403);
        $response->setReasonPhrase($this->errorMessage);

        return new InvalidRequestException($response);
    }

    /**
     * @inheritdoc
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $content = $request->getContent();
        $signatureHeader = $request->getHeader('MONEI-Signature');

        $this->logger->debug('[Callback validation]');
        $this->logger->debug('[Signature header] ' . $signatureHeader);
        $this->logger->debug('[Request body] ' . json_encode(json_decode($content), JSON_PRETTY_PRINT));

        try {
            // Use the webhook data provider to verify the signature
            $this->webhookPaymentDataProvider->extractFromWebhook($content, $signatureHeader);
            return true;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->logger->critical('[Signature verification error] ' . $e->getMessage());
            $this->logger->critical('[Request body] ' . json_encode(json_decode($content), JSON_PRETTY_PRINT));
            return false;
        }
    }
}
