<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Monei\MoneiPayment\Service\Connect\ConnectService;
use Monei\MoneiPayment\Service\Connect\ResultPage;
use Monei\MoneiPayment\Service\Logger;
use Throwable;

/**
 * The callback of "Connect with MONEI": the broker sends the merchant back here with code and state.
 *
 * A public route: the single-use state, stored only by the admin start route, is its only gate. The admin URL
 * carries a secret key, so it can never be a redirect URI.
 */
class Connect implements HttpGetActionInterface
{
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var RawFactory
     */
    private RawFactory $rawFactory;

    /**
     * @var ConnectService
     */
    private ConnectService $connectService;

    /**
     * @var ResultPage
     */
    private ResultPage $resultPage;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param RequestInterface $request
     * @param RawFactory $rawFactory
     * @param ConnectService $connectService
     * @param ResultPage $resultPage
     * @param Logger $logger
     */
    public function __construct(
        RequestInterface $request,
        RawFactory $rawFactory,
        ConnectService $connectService,
        ResultPage $resultPage,
        Logger $logger
    ) {
        $this->request = $request;
        $this->rawFactory = $rawFactory;
        $this->connectService = $connectService;
        $this->resultPage = $resultPage;
        $this->logger = $logger;
    }

    /**
     * Complete the connect and show its result
     *
     * @return Raw
     */
    public function execute(): Raw
    {
        $state = $this->request->getParam('state');
        $code = $this->request->getParam('code');
        try {
            $outcome = $this->connectService->complete(
                is_string($state) ? $state : '',
                is_string($code) ? $code : ''
            );
            $stateChecked = true;
        } catch (Throwable $e) {
            $stateChecked = false;
            // The class only: a message can hold the code.
            $this->logger->error('[Connect] Connect failed: ' . get_class($e));
            $outcome = ['switched' => [], 'problems' => [['mode' => null, 'code' => 'temporarily_unavailable']]];
        }
        $result = $this->rawFactory->create();
        $result->setHttpResponseCode(ConnectService::isRefused($outcome) ? 400 : 200);
        // The code is in this URL: it must never leave in a Referer.
        $result->setHeader('Referrer-Policy', 'no-referrer', true);
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $result->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
        $result->setContents($this->resultPage->render($outcome, $stateChecked));

        return $result;
    }
}
