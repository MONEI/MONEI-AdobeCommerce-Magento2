<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Sales\Api\Data\OrderInterface;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Monei\MoneiPayment\Service\Shared\GetMoneiPaymentCodesByMagentoPaymentCodeRedirect;

/**
 * Monei payment redirect controller
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
     * @param Session $checkoutSession
     * @param CreatePaymentInterface $createPayment
     * @param MagentoRedirect $resultRedirectFactory
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
     * @inheritDoc
     */
    public function execute()
    {
        /**
         * @var $order OrderInterface
         */
        $order = $this->checkoutSession->getLastRealOrder();

        $data = [
            'amount'            => $order->getBaseGrandTotal() * 100,
            'orderId'           => (string) $order->getIncrementId(),
            'currency'          => $order->getBaseCurrencyCode(),
            'customer'          => $this->getCustomerDetails($order),
            'billingDetails'    => $this->getAddressDetails($order->getBillingAddress()),
            'shippingDetails'   => $this->getAddressDetails($order->getShippingAddress())
        ];

        $allowedPaymentMethods =  $this->getAllowedPaymentMethods($order);
        if ($allowedPaymentMethods) {
            $data['allowedPaymentMethods'] = $allowedPaymentMethods;
        }

        $result = $this->createPayment->execute($data);
        if (!isset($result['error']) && isset($result['nextAction']['redirectUrl'])) {
            $this->resultRedirectFactory->setUrl($result['nextAction']['redirectUrl']);
            return $this->resultRedirectFactory;
        }

        return $this->resultRedirectFactory->setPath(
            'monei/payment/fail',
            ['orderId' => $order->getEntityId()]
        );
    }

    /**
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
            'name'  => $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
            'phone' => $this->getCustomerPhone($order)
        ];
    }

    /**
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
     * @param \Magento\Sales\Model\Order\Address $address
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
            'name'      => $address->getFirstname() . ' ' . $address->getLastname(),
            'email'     => $address->getEmail(),
            'phone'     => $address->getTelephone(),
            'company'   => ($address->getCompany() ?? ''),
            'address'   => [
                'country'   => $address->getCountryId(),
                'city'      => $address->getCity(),
                'line1'     => ($streetAddress[0] ?? $streetAddress),
                'line2'     => ($streetAddress[1] ?? ''),
                'zip'       => $address->getPostcode(),
                'state'     => $address->getRegion(),
            ]
        ];

        if (!$moneiAddress['company']) {
            unset($moneiAddress['company']);
        }

        if (!$moneiAddress['address']['line2']) {
            unset($moneiAddress['address']['line2']);
        }

        return $moneiAddress;
    }

    private function getAllowedPaymentMethods(OrderInterface $order): array
    {
        $payment = $order->getPayment();
        $paymentCode = $payment ? $payment->getMethod() : null;

        return $paymentCode
            ? $this->getMoneiPaymentCodesByMagentoPaymentCodeRedirect->execute($paymentCode)
            : [];
    }
}
