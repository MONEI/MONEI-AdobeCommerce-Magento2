<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Address;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Monei\MoneiPayment\Service\Shared\GetMoneiPaymentCodesByMagentoPaymentCodeRedirect;
use OpenAPI\Client\Model\Payment;
use OpenAPI\Client\Model\PaymentNextAction;

/**
 * Monei payment redirect controller.
 */
class Redirect implements ActionInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var CreatePaymentInterface
     */
    private $createPayment;

    /**
     * @var MagentoRedirect
     */
    private $resultRedirectFactory;

    /**
     * @var GetMoneiPaymentCodesByMagentoPaymentCodeRedirect
     */
    private $getMoneiPaymentCodesByMagentoPaymentCodeRedirect;

    /**
     * Constructor.
     *
     * @param Session $checkoutSession
     * @param CreatePaymentInterface $createPayment
     * @param MagentoRedirect $resultRedirectFactory
     * @param GetMoneiPaymentCodesByMagentoPaymentCodeRedirect $getMoneiPaymentCodesByMagentoPaymentCodeRedirect
     */
    public function __construct(
        Session $checkoutSession,
        CreatePaymentInterface $createPayment,
        MagentoRedirect $resultRedirectFactory,
        GetMoneiPaymentCodesByMagentoPaymentCodeRedirect $getMoneiPaymentCodesByMagentoPaymentCodeRedirect
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->createPayment = $createPayment;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->getMoneiPaymentCodesByMagentoPaymentCodeRedirect = $getMoneiPaymentCodesByMagentoPaymentCodeRedirect;
    }

    /**
     * Execute action to create payment and redirect to Monei.
     *
     * @return MagentoRedirect
     */
    public function execute()
    {
        /** @var OrderInterface $order */
        $order = $this->checkoutSession->getLastRealOrder();

        $data = [
            'amount' => $order->getBaseGrandTotal() * 100,
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

        /** @var Payment $payment */
        $payment = $this->createPayment->execute($data);
        $nextAction = $payment->getNextAction();
        
        if ($nextAction instanceof PaymentNextAction && $nextAction->getRedirectUrl()) {
            $this->resultRedirectFactory->setUrl($nextAction->getRedirectUrl());
            return $this->resultRedirectFactory;
        }

        return $this->resultRedirectFactory->setPath(
            'monei/payment/fail',
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
     * @return array|void
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
            ? $this->getMoneiPaymentCodesByMagentoPaymentCodeRedirect->execute($paymentCode)
            : [];
    }
}
