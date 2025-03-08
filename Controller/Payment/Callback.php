<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Service\CallbackHelperInterface;
use Monei\MoneiPayment\Service\Logger;

/**
 * Controller for managing callbacks from Monei system
 */
class Callback extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{
    /**
     * @var string
     */
    private string $errorMessage = '';

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var CallbackHelperInterface
     */
    private CallbackHelperInterface $callbackHelper;

    /**
     * @param Context $context
     * @param Logger $logger
     * @param JsonFactory $resultJsonFactory
     * @param CallbackHelperInterface $callbackHelper
     */
    public function __construct(
        Context $context,
        Logger $logger,
        JsonFactory $resultJsonFactory,
        CallbackHelperInterface $callbackHelper
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->callbackHelper = $callbackHelper;
    }

    /**
     * Execute action based on request
     *
     * This endpoint handles asynchronous payment callback notifications from MONEI.
     * It returns an immediate 200 OK to acknowledge receipt, then processes the payment data.
     *
     * @return Json
     */
    public function execute()
    {
        try {
            $this->logger->debug('---------------------------------------------');
            $this->logger->debug('[Callback] Payment callback received');
            
            // Return 200 OK immediately to acknowledge receipt
            http_response_code(200);

            // Process the callback using our helper service
            $this->callbackHelper->processCallback($this->getRequest());

            return $this->resultJsonFactory->create()->setData(['received' => true]);
        } catch (Exception $e) {
            $this->logger->error('[Callback] Error processing callback: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return $this->resultJsonFactory->create()->setData(['error' => 'Internal error processing callback']);
        }
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        /** @var \Magento\Framework\App\Response\Http $response */
        $response = $this->getResponse();
        $response->setHttpResponseCode(403);
        $response->setReasonPhrase($this->errorMessage);

        return new InvalidRequestException($response);
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        try {
            $body = $request->getContent();
            $header = $request->getHeader('MONEI-Signature');
            
            if (empty($header)) {
                $this->errorMessage = 'Missing signature header';
                $this->logger->critical('[Callback CSRF] Missing signature header');
                return false;
            }
            
            $signature = is_array($header) ? implode(',', $header) : (string)$header;
            $isValid = $this->callbackHelper->verifyCallbackSignature($body, $signature);
            
            if (!$isValid) {
                $this->errorMessage = 'Invalid signature';
            }
            
            return $isValid;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->logger->critical('[Callback CSRF] ' . $e->getMessage());
            return false;
        }
    }
}
