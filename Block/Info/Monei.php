<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\Info;

use Magento\Framework\View\Element\Template;
use Magento\Payment\Block\Info;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;

class Monei extends Info
{
    private const INFO_PAY_ALLOWED = [
        'last4',
        'brand',
        'phoneNumber',
    ];

    /**
     * Monei template
     *
     * @var string
     */
    protected $_template = 'Monei_MoneiPayment::info/monei.phtml';

    /**
     * @var GetPaymentInterface
     */
    private $paymentService;

    public function __construct(
        GetPaymentInterface $paymentService,
        Template\Context    $context,
        array               $data = []
    ) {
        parent::__construct($context, $data);
        $this->paymentService = $paymentService;
    }

    /**
     * @return array|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getPaymentInfo()
    {
        if (!$this->getInfo() || !$this->getInfo()->getOrder()) {
            return null;
        }

        $monei_payment_id = $this->getInfo()->getOrder()->getData('monei_payment_id');
        if ($monei_payment_id) {
            $paymentData = $this->paymentService->execute($monei_payment_id);
            $paymentMethodData = array_key_exists('paymentMethod', $paymentData) ? $paymentData['paymentMethod'] : null;
            if ($paymentMethodData) {
                foreach ($paymentMethodData as $payKey => $payValue) {
                    if (is_array($payValue)) {
                        foreach ($payValue as $key => $value) {
                            if ($key == 'expiration') {
                                $paymentMethodData[$payKey][$key] = date("m/y", $value);
                            } else {
                                $paymentMethodData[$key] = $value;
                            }
                        }

                        unset($paymentMethodData[$payKey]);
                    }
                }
            }

            return $paymentMethodData;
        }
    }

    /**
     * @return mixed|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getPaymentTitle()
    {
        if ($this->getInfo() && $this->getInfo()->getOrder()) {
            return $this->getMethod()->getConfigData('title', $this->getInfo()->getOrder()->getStoreId());
        }

        return null;
    }

    public function getInfoPayAllowed(): array
    {
        return self::INFO_PAY_ALLOWED;
    }
}
