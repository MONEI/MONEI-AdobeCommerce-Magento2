<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Monei\MoneiPayment\Api\Service\Checkout\CreateLoggedMoneiPaymentVaultInterface;
use Psr\Log\LoggerInterface;

/**
 * Controller for handling direct form submission and redirection for vault payments
 */
class VaultRedirect implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CreateLoggedMoneiPaymentVaultInterface
     */
    protected $createLoggedMoneiPaymentVault;

    /**
     * @var FormKeyValidator
     */
    protected $formKeyValidator;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Redirect
     */
    protected $redirect;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param CreateLoggedMoneiPaymentVaultInterface $createLoggedMoneiPaymentVault
     * @param FormKeyValidator $formKeyValidator
     * @param LoggerInterface $logger
     * @param Redirect $redirect
     * @param ManagerInterface $messageManager
     * @param RequestInterface $request
     */
    public function __construct(
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        CreateLoggedMoneiPaymentVaultInterface $createLoggedMoneiPaymentVault,
        FormKeyValidator $formKeyValidator,
        LoggerInterface $logger,
        Redirect $redirect,
        ManagerInterface $messageManager,
        RequestInterface $request
    ) {
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->createLoggedMoneiPaymentVault = $createLoggedMoneiPaymentVault;
        $this->formKeyValidator = $formKeyValidator;
        $this->logger = $logger;
        $this->redirect = $redirect;
        $this->messageManager = $messageManager;
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $this->redirect->setPath('checkout/cart');

        return new InvalidRequestException(
            $this->redirect,
            [__('Invalid form key. Please refresh the page.')]
        );
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // Check for form_key in the request
        $formKey = $request->getParam('form_key');
        if ($formKey) {
            // If form key exists, let Magento validate it normally
            return null;
        }

        // If there's no form key in the request, bypass CSRF validation
        // This allows both scenarios - form submissions with form key and direct API calls
        return true;
    }

    /**
     * Execute the vault redirect action.
     *
     * @return Redirect
     */
    public function execute()
    {
        // Default redirect path on error
        $redirectPath = 'checkout/cart';
        $params = [];

        try {
            // Verify form key
            if (!$this->formKeyValidator->validate($this->request)) {
                throw new LocalizedException(__('Invalid form key. Please refresh the page and try again.'));
            }

            // Get the public hash from the request
            $publicHash = (string) $this->request->getParam('public_hash');
            if (empty($publicHash)) {
                throw new LocalizedException(__('No payment method selected.'));
            }

            // Get the last real order
            /** @var OrderInterface $order */
            $order = $this->checkoutSession->getLastRealOrder();
            if (!$order || !$order->getEntityId()) {
                throw new LocalizedException(__('No order found. Please return to checkout and try again.'));
            }

            // Create the payment using the vault token and order data
            $result = $this->createLoggedMoneiPaymentVault->execute(
                (string) $order->getQuoteId(),
                $publicHash
            );

            // Store payment ID in the session
            if (isset($result['success']) && $result['success'] && !empty($result['payment_id'])) {
                $this->checkoutSession->setLastMoneiPaymentId($result['payment_id']);

                $this->logger->info('Vault payment created successfully', [
                    'order_id' => $order->getIncrementId(),
                    'payment_id' => $result['payment_id'],
                ]);
            }

            // Check for successful payment creation
            if (isset($result['success']) && $result['success'] && !empty($result['redirect_url'])) {
                // Set external URL for redirection
                $this->redirect->setUrl($result['redirect_url']);
                return $this->redirect;
            }

            throw new LocalizedException(__('Unable to process payment. Please try again.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->critical('MONEI Vault Redirect Error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            // Restore quote to cart when error occurs
            $this->restoreQuoteToCart();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while processing your payment. Please try again.'));
            $this->logger->critical('MONEI Vault Redirect Error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            // Restore quote to cart when error occurs
            $this->restoreQuoteToCart();
        }

        // Redirect to cart on failure
        $this->redirect->setPath($redirectPath, $params);
        return $this->redirect;
    }

    /**
     * Restore the quote to cart after a payment error
     *
     * @return void
     */
    private function restoreQuoteToCart(): void
    {
        try {
            $this->checkoutSession->restoreQuote();
            $order = $this->checkoutSession->getLastRealOrder();
            if ($order && $order->getIncrementId()) {
                $this->logger->info('Restored quote for order: ' . $order->getIncrementId());
            } else {
                $this->logger->info('Restored quote');
            }
        } catch (\Exception $e) {
            $orderId = null;
            $order = $this->checkoutSession->getLastRealOrder();
            if ($order) {
                $orderId = $order->getIncrementId();
            }

            if ($orderId) {
                $this->logger->error('Failed to restore quote for order ' . $orderId . ': ' . $e->getMessage());
            } else {
                $this->logger->error('Failed to restore quote: ' . $e->getMessage());
            }
        }
    }
}
