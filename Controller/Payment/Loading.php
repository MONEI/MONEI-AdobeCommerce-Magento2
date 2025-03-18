<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Monei\MoneiPayment\Service\Logger;

/**
 * Controller for displaying the loading page while waiting for payment confirmation
 */
class Loading implements HttpGetActionInterface
{
    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param ResultFactory $resultFactory
     * @param RequestInterface $request
     * @param Logger $logger
     */
    public function __construct(
        ResultFactory $resultFactory,
        RequestInterface $request,
        Logger $logger
    ) {
        $this->resultFactory = $resultFactory;
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * Display loading page for payment confirmation
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $paymentId = $this->request->getParam('payment_id');

        $this->logger->info('[Loading] Displaying loading page', [
            'payment_id' => $paymentId ?? 'unknown',
        ]);

        // Create and render the loading page
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        return $resultPage;
    }
}
