<?php

/**
 * Copyright © Monei. All rights reserved.
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Module\Dir;
use Psr\Log\LoggerInterface;

/**
 * Plugin for handling Apple Pay domain verification
 */
class ApplePayVerification
{
    private const MONEI_APPLE_PAY_FILE_URL = 'https://assets.monei.com/apple-pay/apple-developer-merchantid-domain-association/';

    /**
     * @var File
     */
    private $file;

    /**
     * @var Reader
     */
    private $moduleReader;

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
     * @param File $file
     * @param Reader $moduleReader
     * @param LoggerInterface $logger
     * @param ResponseHttp $response
     * @param Curl $curl
     */
    public function __construct(
        File $file,
        Reader $moduleReader,
        LoggerInterface $logger,
        ResponseHttp $response,
        Curl $curl
    ) {
        $this->file = $file;
        $this->moduleReader = $moduleReader;
        $this->logger = $logger;
        $this->response = $response;
        $this->curl = $curl;
    }

    /**
     * Intercept the dispatch process to serve the Apple Pay verification file
     *
     * @param FrontControllerInterface $subject
     * @param RequestInterface $request
     * @return ResultInterface|null
     */
    public function beforeDispatch(
        FrontControllerInterface $subject,
        RequestInterface $request
    ) {
        $pathInfo = $request->getPathInfo();

        // Check if this is a request for the Apple Pay verification file
        if ($pathInfo === '/.well-known/apple-developer-merchantid-domain-association') {
            $this->serveApplePayVerificationFile();
            // Return null to stop further execution
            return null;
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
                return;
            } else {
                $this->logger->error('[ApplePay] Failed to fetch verification file. Status code: ' . $statusCode);
                $this->response->setHttpResponseCode($statusCode);
                $this->response->sendResponse();
                return;
            }
        } catch (\Exception $e) {
            $this->logger->error('[ApplePay] Error serving verification file: ' . $e->getMessage());
            $this->response->setHttpResponseCode(500);
            $this->response->sendResponse();
            return;
        }
    }
}
