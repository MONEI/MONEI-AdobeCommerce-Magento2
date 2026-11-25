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
 * Clear the credentials of both modes at the config scope being edited.
 *
 * A forged disconnect would stop checkout and remove the Live account check of the next connect: the framework
 * checks ADMIN_RESOURCE and the form key before execute().
 */
class Disconnect extends Action implements HttpPostActionInterface
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
     * Disconnect and go back to the payment configuration
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $website = $this->getRequest()->getParam('website');
        $store = $this->getRequest()->getParam('store');
        $scopeOfRequest = $this->callbackUrl->scope($website, $store);
        if ($scopeOfRequest === null) {
            $this->messageManager->addErrorMessage(__('This scope does not exist. Nothing was changed.'));

            return $this->resultRedirectFactory->create()->setPath(
                'adminhtml/system_config/edit',
                ['section' => 'payment']
            );
        }
        $result = $this->connectService->disconnect(...$scopeOfRequest);
        if ($result['cleared']) {
            $this->messageManager->addSuccessMessage(
                __('MONEI disconnected. The keys stay active until you revoke them in the MONEI Dashboard.')
            );
        }
        foreach ($result['locked'] as $mode) {
            $this->messageManager->addWarningMessage(ConnectService::message($mode, 'locked'));
        }

        return $this->resultRedirectFactory->create()->setPath(
            'adminhtml/system_config/edit',
            array_filter(['section' => 'payment', 'website' => $website, 'store' => $store])
        );
    }
}
