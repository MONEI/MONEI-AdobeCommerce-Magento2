<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Adminhtml\Order\Creditmemo;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoader;
use Magento\Sales\Helper\Data as SalesData;
use Magento\Sales\Model\Order\Email\Sender\CreditmemoSender;
use Psr\Log\LoggerInterface;

/**
 * Save creditmemo controller.
 */
class Save extends Action implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session.
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_Sales::sales_creditmemo';

    /**
     * @var CreditmemoLoader
     */
    protected $creditmemoLoader;

    /**
     * @var CreditmemoSender
     */
    protected $creditmemoSender;

    /**
     * @var SalesData
     */
    private $salesData;

    /**
     * Constructor for Save controller.
     *
     * @param Action\Context $context
     * @param CreditmemoLoader $creditmemoLoader
     * @param CreditmemoSender $creditmemoSender
     * @param SalesData|null $salesData
     */
    public function __construct(
        Action\Context $context,
        CreditmemoLoader $creditmemoLoader,
        CreditmemoSender $creditmemoSender,
        ?SalesData $salesData = null
    ) {
        $this->creditmemoLoader = $creditmemoLoader;
        $this->creditmemoSender = $creditmemoSender;
        $this->salesData = $salesData ?: ObjectManager::getInstance()->get(SalesData::class);
        parent::__construct($context);
    }

    /**
     * Save creditmemo action.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPost('creditmemo');
        if (!empty($data['comment_text'])) {
            $this->_getSession()->setCommentText($data['comment_text']);
        }

        try {
            $this->creditmemoLoader->setOrderId($this->getRequest()->getParam('order_id'));
            $this->creditmemoLoader->setCreditmemoId($this->getRequest()->getParam('creditmemo_id'));
            $this->creditmemoLoader->setCreditmemo($this->getRequest()->getParam('creditmemo'));
            $this->creditmemoLoader->setInvoiceId($this->getRequest()->getParam('invoice_id'));
            $creditmemo = $this->creditmemoLoader->load();
            if ($creditmemo) {
                if (!$creditmemo->isValidGrandTotal()) {
                    throw new LocalizedException(
                        __("The credit memo's total must be positive.")
                    );
                }

                if (isset($data['refund_reason']) && !empty($data['refund_reason'])) {
                    $creditmemo->setData('refund_reason', $data['refund_reason']);
                }

                if (!empty($data['comment_text'])) {
                    $creditmemo->addComment(
                        $data['comment_text'],
                        isset($data['comment_customer_notify']),
                        isset($data['is_visible_on_front'])
                    );

                    $creditmemo->setCustomerNote($data['comment_text']);
                    $creditmemo->setCustomerNoteNotify(isset($data['comment_customer_notify']));
                }

                if (isset($data['do_offline'])) {
                    if (!$data['do_offline'] && !empty($data['refund_customerbalance_return_enable'])) {
                        throw new LocalizedException(
                            __('Cannot create online refund for Refund to Store Credit.')
                        );
                    }
                }

                $creditmemoManagement = $this->_objectManager->create(
                    CreditmemoManagementInterface::class
                );
                $creditmemo->getOrder()->setCustomerNoteNotify(!empty($data['send_email']));
                $doOffline = isset($data['do_offline']) && (bool) $data['do_offline'];
                $creditmemoManagement->refund($creditmemo, $doOffline);

                if (!empty($data['send_email']) && $this->salesData->canSendNewCreditMemoEmail()) {
                    $this->creditmemoSender->send($creditmemo);
                }

                $this->messageManager->addSuccessMessage(__('You created the credit memo.'));
                $this->_getSession()->getCommentText(true);
                $resultRedirect->setPath('sales/order/view', ['order_id' => $creditmemo->getOrderId()]);

                return $resultRedirect;
            }
            /** @var \Magento\Backend\Model\View\Result\Forward $resultForward */
            $resultForward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
            $resultForward->forward('noroute');

            return $resultForward;
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->_getSession()->setFormData($data);
        } catch (\Exception $e) {
            $this->_objectManager->get(LoggerInterface::class)->critical($e);
            $this->messageManager->addErrorMessage(__("We can't save the credit memo right now."));
        }
        $resultRedirect->setPath('sales/*/new', ['_current' => true]);

        return $resultRedirect;
    }
}
