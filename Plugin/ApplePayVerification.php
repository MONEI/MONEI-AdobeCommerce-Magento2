<?php

/**
 * Copyright © Monei. All rights reserved.
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Payment\Helper\Data as PaymentHelper;
use Psr\Log\LoggerInterface;
use Closure;

/**
 * Plugin for handling Apple Pay domain verification
 */
class ApplePayVerification
{
    private const MONEI_APPLE_PAY_FILE_URL = 'https://assets.monei.com/apple-pay/apple-developer-merchantid-domain-association/';
    private const PAYMENT_METHOD_CODE = 'monei_google_apple';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ResponseHttp
     */
    private $response;

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param LoggerInterface $logger
     * @param ResponseHttp $response
     * @param Curl $curl
     * @param PaymentHelper $paymentHelper
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        LoggerInterface $logger,
        ResponseHttp $response,
        Curl $curl,
        PaymentHelper $paymentHelper,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->logger = $logger;
        $this->response = $response;
        $this->curl = $curl;
        $this->paymentHelper = $paymentHelper;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Around plugin for dispatch to handle Apple Pay verification file requests
     *
     * @param FrontControllerInterface $subject
     * @param Closure $proceed
     * @param RequestInterface $request
     * @return ResultInterface|null
     */
    public function aroundDispatch(
        FrontControllerInterface $subject,
        Closure $proceed,
        RequestInterface $request
    ) {
        $pathInfo = rtrim($request->getPathInfo() ?? '', '/');

        // Check if this is a request for the Apple Pay verification file and if Apple Pay is enabled
        if ($pathInfo === '/.well-known/apple-developer-merchantid-domain-association' &&
                $this->isApplePayEnabled()) {
            $this->serveApplePayVerificationFile();
            return null;
        }

        return $proceed($request);
    }

    /**
     * Check if Apple Pay is enabled
     *
     * @return bool
     */
    private function isApplePayEnabled(): bool
    {
        try {
            $method = $this->paymentHelper->getMethodInstance(self::PAYMENT_METHOD_CODE);
            return $method->isActive();
        } catch (\Exception $e) {
            $this->logger->error('[ApplePay] Error checking if payment method is enabled: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Serve the Apple Pay verification file
     *
     * @return void
     */
    private function serveApplePayVerificationFile(): void
    {
        try {
            $this->curl->get(self::MONEI_APPLE_PAY_FILE_URL);
            $statusCode = $this->curl->getStatus();

            if ($statusCode === 200) {
                $fileContent = $this->curl->getBody();
                $this->response->setHeader('Content-Type', 'text/plain');
                $this->response->setBody($fileContent);
                $this->response->sendResponse();
            } else {
                $this->logger->error('[ApplePay] Failed to fetch verification file. Status code: ' . $statusCode);
                $this->response->setHttpResponseCode($statusCode);
                $this->response->sendResponse();
            }
        } catch (\Exception $e) {
            $this->logger->error('[ApplePay] Error serving verification file: ' . $e->getMessage());
            $this->response->setHttpResponseCode(500);
            $this->response->sendResponse();
        }
    }
}
