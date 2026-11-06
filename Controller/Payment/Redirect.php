<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Address;
use Magento\Store\Model\StoreManagerInterface;
use Monei\Model\Payment;
use Monei\Model\PaymentNextAction;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Monei\MoneiPayment\Api\Service\Customer\CustomerServiceInterface;
use Monei\MoneiPayment\Service\Shared\PaymentMethodCodeMapper;

/**
 * Monei payment redirect controller.
 * Implements HttpGetActionInterface to specify it handles GET requests
 */
class Redirect implements HttpGetActionInterface
{
    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var CreatePaymentInterface
     */
    private CreatePaymentInterface $createPayment;

    /**
     * @var MagentoRedirect
     */
    private MagentoRedirect $resultRedirectFactory;

    /**
     * @var PaymentMethodCodeMapper
     */
    private PaymentMethodCodeMapper $paymentMethodCodeMapper;

    /**
     * @var CustomerServiceInterface
     */
    private CustomerServiceInterface $customerService;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * Constructor.
     *
     * @param Session $checkoutSession
     * @param CreatePaymentInterface $createPayment
     * @param MagentoRedirect $resultRedirectFactory
     * @param PaymentMethodCodeMapper $paymentMethodCodeMapper
     * @param CustomerServiceInterface $customerService
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Session $checkoutSession,
        CreatePaymentInterface $createPayment,
        MagentoRedirect $resultRedirectFactory,
        PaymentMethodCodeMapper $paymentMethodCodeMapper,
        CustomerServiceInterface $customerService,
        StoreManagerInterface $storeManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->createPayment = $createPayment;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->paymentMethodCodeMapper = $paymentMethodCodeMapper;
        $this->customerService = $customerService;
        $this->storeManager = $storeManager;
    }

    /**
     * Execute action to create payment and redirect to Monei.
     *
     * @return MagentoRedirect
     */
    public function execute()
    {
        // Ensure this page is not cached by Varnish
        $this->resultRedirectFactory->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $this->resultRedirectFactory->setHeader('Pragma', 'no-cache', true);

        /** @var OrderInterface $order */
        $order = $this->checkoutSession->getLastRealOrder();

        $data = [
            'amount' => (int) ($order->getBaseGrandTotal() * 100),
            'currency' => $order->getBaseCurrencyCode(),
            'orderId' => $order->getIncrementId(),
            'customer' => $this->getCustomerDetails($order),
            'billingDetails' => $this->getAddressDetails($order->getBillingAddress()),
            'shippingDetails' => $this->getAddressDetails($order->getBillingAddress()),
        ];

        $allowedPaymentMethods = $this->getAllowedPaymentMethods($order);
        if ($allowedPaymentMethods) {
            $data['allowedPaymentMethods'] = $allowedPaymentMethods;
        }

        if ($order->getCustomerId()) {
            $moneiCustomerId = $this->customerService->getOrCreateCustomerId(
                (int) $order->getCustomerId(),
                (int) $this->storeManager->getStore()->getId(),
                (string) $order->getCustomerEmail(),
                $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
                (string) $this->getCustomerPhone($order)
            );
            if ($moneiCustomerId !== null) {
                $data['customer_id'] = $moneiCustomerId;
                $data['platform_customer_id'] = (int) $order->getCustomerId();
            }
        }

        /** @var Payment $payment */
        $payment = $this->createPayment->execute($data);
        $nextAction = $payment->getNextAction();

        if ($nextAction instanceof PaymentNextAction && $nextAction->getRedirectUrl()) {
            $this->resultRedirectFactory->setUrl($nextAction->getRedirectUrl());

            return $this->resultRedirectFactory;
        }

        return $this->resultRedirectFactory->setPath(
            'monei/payment/complete',
            ['orderId' => $order->getEntityId()]
        );
    }

    /**
     * Get customer details from order for Monei payment.
     *
     * @param OrderInterface $order
     *
     * @return array|string[]
     */
    private function getCustomerDetails($order)
    {
        if (!$order->getEntityId()) {
            return [];
        }

        return [
            'email' => $order->getCustomerEmail(),
            'name' => $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
            'phone' => $this->getCustomerPhone($order),
        ];
    }

    /**
     * Get customer phone number from order.
     *
     * @param OrderInterface $order
     *
     * @return string
     */
    private function getCustomerPhone($order)
    {
        if ($order->getBillingAddress()) {
            return $order->getBillingAddress()->getTelephone();
        }

        return '';
    }

    /**
     * Get billing address details for Monei payment.
     *
     * @param Address $address
     *
     * @return array
     */
    private function getAddressDetails($address)
    {
        if (!$address->getEntityId()) {
            return [];
        }

        $streetAddress = $address->getStreet();
        $moneiAddress = [
            'name' => $address->getFirstname() . ' ' . $address->getLastname(),
            'email' => $address->getEmail(),
            'phone' => $address->getTelephone(),
            'company' => ($address->getCompany() ?? ''),
            'address' => [
                'country' => $address->getCountryId(),
                'city' => $address->getCity(),
                'line1' => ($streetAddress[0] ?? $streetAddress),
                'line2' => ($streetAddress[1] ?? ''),
                'zip' => $address->getPostcode(),
                'state' => $address->getRegion(),
            ],
        ];

        if (!$moneiAddress['company']) {
            unset($moneiAddress['company']);
        }

        if (!$moneiAddress['address']['line2']) {
            unset($moneiAddress['address']['line2']);
        }

        return $moneiAddress;
    }

    /**
     * Get allowed payment methods for the order.
     *
     * @param OrderInterface $order
     *
     * @return array
     */
    private function getAllowedPaymentMethods(OrderInterface $order): array
    {
        $payment = $order->getPayment();
        $paymentCode = $payment ? $payment->getMethod() : null;

        return $paymentCode
            ? $this->paymentMethodCodeMapper->execute($paymentCode)
            : [];
    }
}
