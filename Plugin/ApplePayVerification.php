<?php

/**
 * Copyright © Monei. All rights reserved.
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Module\Dir;
use Psr\Log\LoggerInterface;
use Exception;

/**
 * Plugin for handling Apple Pay domain verification
 */
class ApplePayVerification
{
    private const MONEI_APPLE_PAY_FILE_URL = 'https://assets.monei.com/apple-pay/apple-developer-merchantid-domain-association/';
    private const APPLE_PAY_VERIFICATION_PATH = '/.well-known/apple-developer-merchantid-domain-association';

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
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * @param File $file
     * @param Reader $moduleReader
     * @param LoggerInterface $logger
     * @param ResponseHttp $response
     * @param Curl $curl
     * @param ResultFactory $resultFactory
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        File $file,
        Reader $moduleReader,
        LoggerInterface $logger,
        ResponseHttp $response,
        Curl $curl,
        ResultFactory $resultFactory,
        ManagerInterface $eventManager
    ) {
        $this->file = $file;
        $this->moduleReader = $moduleReader;
        $this->logger = $logger;
        $this->response = $response;
        $this->curl = $curl;
        $this->resultFactory = $resultFactory;
        $this->eventManager = $eventManager;
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
        // Get the request URI in a way that works with both real requests and unit tests
        $requestUri = '';
        if ($request instanceof RequestHttp) {
            $requestUri = $request->getRequestUri();
        } else {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        }

        // Check if this is a request for the Apple Pay verification file
        if (strpos($requestUri, self::APPLE_PAY_VERIFICATION_PATH) !== false) {
            try {
                $this->eventManager->dispatch('controller_action_predispatch');

                // Prepare Apple Pay verification response
                $this->curl->get(self::MONEI_APPLE_PAY_FILE_URL);
                $statusCode = $this->curl->getStatus();

                if ($statusCode === 200) {
                    $fileContent = $this->curl->getBody();

                    /** @var Raw $result */
                    $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
                    $result->setHttpResponseCode(200);
                    $result->setHeader('Content-Type', 'text/plain');
                    $result->setContents($fileContent);

                    // Returning a Result object will prevent normal dispatch
                    return $result;
                } else {
                    $this->logger->error('[ApplePay] Failed to fetch verification file. Status code: ' . $statusCode);

                    /** @var Raw $result */
                    $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
                    $result->setHttpResponseCode($statusCode);

                    return $result;
                }
            } catch (Exception $e) {
                $this->logger->error('[ApplePay] Error serving verification file: ' . $e->getMessage());

                /** @var Raw $result */
                $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
                $result->setHttpResponseCode(500);

                return $result;
            }
        }

        return null;
    }
}
