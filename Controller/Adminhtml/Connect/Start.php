<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Adminhtml\Connect;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Monei\MoneiPayment\Service\Connect\CallbackUrl;
use Monei\MoneiPayment\Service\Connect\ConnectService;

/**
 * Start a connect for the config scope being edited: store a state and redirect to the broker.
 *
 * Only an admin with the MONEI config resource can start: the framework checks ADMIN_RESOURCE and the form key
 * before execute(), so a refused request stores no state.
 */
class Start extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Monei_MoneiPayment::config';

    /**
     * @var ConnectService
     */
    private ConnectService $connectService;

    /**
     * @var CallbackUrl
     */
    private CallbackUrl $callbackUrl;

    /**
     * @param Context $context
     * @param ConnectService $connectService
     * @param CallbackUrl $callbackUrl
     */
    public function __construct(Context $context, ConnectService $connectService, CallbackUrl $callbackUrl)
    {
        parent::__construct($context);
        $this->connectService = $connectService;
        $this->callbackUrl = $callbackUrl;
    }

    /**
     * Redirect to the broker
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $website = $this->getRequest()->getParam('website');
        $store = $this->getRequest()->getParam('store');
        [$scope, $scopeId] = CallbackUrl::scopeOf($website, $store);
        $redirectUri = $this->callbackUrl->redirectUri($scope, $scopeId);
        if ($redirectUri === null) {
            $this->messageManager->addErrorMessage(
                __('Connect with MONEI is not available for the URL of this store. Use manual keys.')
            );

            return $this->resultRedirectFactory->create()->setPath(
                'adminhtml/system_config/edit',
                array_filter(['section' => 'payment', 'website' => $website, 'store' => $store])
            );
        }

        return $this->resultRedirectFactory->create()->setUrl(
            $this->connectService->begin($redirectUri, $scope, $scopeId)
        );
    }
}
