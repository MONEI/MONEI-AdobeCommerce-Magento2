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
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Monei\MoneiPayment\Service\Connect\ConnectService;

/**
 * Hide the invitation to connect from the admin user who dismisses it
 */
class DismissNotice extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Monei_MoneiPayment::config';

    /**
     * @var ConnectService
     */
    private ConnectService $connectService;

    /**
     * @var Session
     */
    private Session $authSession;

    /**
     * @param Context $context
     * @param ConnectService $connectService
     * @param Session $authSession
     */
    public function __construct(Context $context, ConnectService $connectService, Session $authSession)
    {
        parent::__construct($context);
        $this->connectService = $connectService;
        $this->authSession = $authSession;
    }

    /**
     * Dismiss and go back to the payment configuration
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $user = $this->authSession->getUser();
        if ($user !== null) {
            $this->connectService->dismissNotice((int) $user->getId());
        }

        $params = ['section' => 'payment'];
        foreach (['website', 'store'] as $name) {
            $value = $this->getRequest()->getParam($name);
            if (is_scalar($value) && (string) $value !== '') {
                $params[$name] = (string) $value;
            }
        }

        return $this->resultRedirectFactory->create()->setPath('adminhtml/system_config/edit', $params);
    }
}
