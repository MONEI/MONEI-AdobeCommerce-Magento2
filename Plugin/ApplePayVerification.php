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
    private const APPLE_PAY_PATH = '/.well-known/apple-developer-merchantid-domain-association';

    /**
     * @var LoggerInterface
     */
    private $_logger;

    /**
     * @var ResponseHttp
     */
    private $_response;

    /**
     * @var Curl
     */
    private $_curl;

    /**
     * @var PaymentHelper
     */
    private $_paymentHelper;

    /**
     * @var ScopeConfigInterface
     */
    private $_scopeConfig;

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
        $this->_logger = $logger;
        $this->_response = $response;
        $this->_curl = $curl;
        $this->_paymentHelper = $paymentHelper;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * Around plugin for dispatch to handle Apple Pay verification file requests
     *
     * @param FrontControllerInterface $subject
     * @param Closure $proceed
     * @param RequestInterface $request
     * @return ResultInterface|ResponseHttp|null
     */
    public function aroundDispatch(
        FrontControllerInterface $subject,
        Closure $proceed,
        RequestInterface $request
    ) {
        // Get the request URI and normalize it for comparison
        $requestUri = $request->getRequestUri();
        $pathInfo = parse_url($requestUri, PHP_URL_PATH);
        $pathInfo = rtrim($pathInfo, '/');

        // Check if this is a request for the Apple Pay verification file
        if ($pathInfo === self::APPLE_PAY_PATH && $this->_isApplePayEnabled()) {
            $this->_logger->info('[ApplePay] Processing Apple Pay verification request');
            return $this->_handleApplePayVerification();
        }

        return $proceed($request);
    }

    /**
     * Check if Apple Pay is enabled
     *
     * @return bool
     */
    private function _isApplePayEnabled(): bool
    {
        try {
            $method = $this->_paymentHelper->getMethodInstance(self::PAYMENT_METHOD_CODE);
            return $method->isActive();
        } catch (\Exception $e) {
            $this->_logger->error('[ApplePay] Error checking if payment method is enabled: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle Apple Pay verification request
     *
     * @return ResponseHttp
     */
    private function _handleApplePayVerification(): ResponseHttp
    {
        try {
            $this->_curl->get(self::MONEI_APPLE_PAY_FILE_URL);
            $statusCode = $this->_curl->getStatus();

            if ($statusCode === 200) {
                $fileContent = $this->_curl->getBody();
                $this->_response->setHeader('Content-Type', 'text/plain', true);
                $this->_response->setBody($fileContent);
                $this->_response->setStatusCode(200);
                $this->_logger->info('[ApplePay] Successfully served verification file');
            } else {
                $this->_logger->error('[ApplePay] Failed to fetch verification file. Status code: ' . $statusCode);
                $this->_response->setStatusCode($statusCode ?: 500);
            }
        } catch (\Exception $e) {
            $this->_logger->error('[ApplePay] Error serving verification file: ' . $e->getMessage());
            $this->_response->setStatusCode(500);
        }

        return $this->_response;
    }
}
