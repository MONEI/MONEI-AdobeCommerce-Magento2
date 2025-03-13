<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Service\Checkout\CreateLoggedMoneiPaymentVaultInterface;

/**
 * Controller for handling direct form submission and redirection for vault payments
 */
class VaultRedirect extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
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
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param CreateLoggedMoneiPaymentVaultInterface $createLoggedMoneiPaymentVault
     */
    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        CreateLoggedMoneiPaymentVaultInterface $createLoggedMoneiPaymentVault
    ) {
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->createLoggedMoneiPaymentVault = $createLoggedMoneiPaymentVault;
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('checkout/cart');

        return new InvalidRequestException(
            $resultRedirect,
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
     * Execute controller action to process payment with vault
     *
     * @return Redirect
     */
    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            // Check if customer is logged in
            if (!$this->customerSession->isLoggedIn()) {
                throw new LocalizedException(__('You must be logged in to use saved payment methods.'));
            }

            $publicHash = $this->getRequest()->getParam('public_hash');
            if (empty($publicHash)) {
                throw new LocalizedException(__('Missing saved card information.'));
            }

            // Try to get cart ID from request params first, then from session
            $cartId = $this->getRequest()->getParam('quote_id');

            // If not in request, try to get from session
            if (empty($cartId)) {
                $cartId = (string) $this->checkoutSession->getQuoteId();
            }

            if (empty($cartId)) {
                throw new LocalizedException(__('No active cart found. Please return to the cart page and try again.'));
            }

            // Call the service to create payment and get redirect URL
            $result = $this->createLoggedMoneiPaymentVault->execute($cartId, $publicHash);

            // If we have a redirect URL, redirect directly to it
            if (!empty($result[0]['redirectUrl'])) {
                return $resultRedirect->setUrl($result[0]['redirectUrl']);
            }

            // If no redirect URL was returned, redirect to checkout page with error
            $this->messageManager->addErrorMessage(__('There was a problem processing your payment. Please try again.'));
            return $resultRedirect->setPath('checkout/cart');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('checkout/cart');
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong with the payment. Please try again later.')
            );
            return $resultRedirect->setPath('checkout/cart');
        }
    }
}
