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
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Module\Dir;
use Psr\Log\LoggerInterface;

/**
 * Plugin for handling Apple Pay domain verification
 */
class ApplePayVerification
{
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
     * @param File $file
     * @param Reader $moduleReader
     * @param LoggerInterface $logger
     * @param ResponseHttp $response
     */
    public function __construct(
        File $file,
        Reader $moduleReader,
        LoggerInterface $logger,
        ResponseHttp $response
    ) {
        $this->file = $file;
        $this->moduleReader = $moduleReader;
        $this->logger = $logger;
        $this->response = $response;
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
        $filePath = $this->moduleReader->getModuleDir(
            Dir::MODULE_VIEW_DIR,
            'Monei_MoneiPayment'
        ) . '/frontend/web/apple-developer-merchantid-domain-association';

        try {
            if ($this->file->isExists($filePath)) {
                $fileContent = $this->file->fileGetContents($filePath);
                $this->response->setHeader('Content-Type', 'text/plain');
                $this->response->setBody($fileContent);
                $this->response->sendResponse();
                exit;
            } else {
                $this->logger->error('Apple Pay verification file not found: ' . $filePath);
                $this->response->setHttpResponseCode(404);
                $this->response->sendResponse();
                exit;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error serving Apple Pay verification file: ' . $e->getMessage());
            $this->response->setHttpResponseCode(500);
            $this->response->sendResponse();
            exit;
        }
    }
}
