<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\Info;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Block\Info;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;

/**
 * Monei payment info block.
 */
class Monei extends Info
{
    /** Payment information fields allowed to be displayed. */
    private const INFO_PAY_ALLOWED = [
        'last4',
        'brand',
        'phoneNumber',
    ];

    /**
     * Monei template.
     *
     * @var string
     */
    protected $_template = 'Monei_MoneiPayment::info/monei.phtml';

    /**
     * Payment service.
     *
     * @var GetPaymentInterface
     */
    private $paymentService;

    /**
     * Constructor.
     *
     * @param GetPaymentInterface $paymentService
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        GetPaymentInterface $paymentService,
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->paymentService = $paymentService;
    }

    /**
     * Get payment information.
     *
     * @throws LocalizedException
     *
     * @return array|null
     */
    public function getPaymentInfo()
    {
        if (!$this->getInfo() || !$this->getInfo()->getOrder()) {
            return null;
        }

        $monei_payment_id = $this->getInfo()->getOrder()->getData('monei_payment_id');
        if ($monei_payment_id) {
            $paymentData = $this->paymentService->execute($monei_payment_id);
            $paymentMethodData = \array_key_exists('paymentMethod', $paymentData)
                ? $paymentData['paymentMethod']
                : null;
            if ($paymentMethodData) {
                foreach ($paymentMethodData as $payKey => $payValue) {
                    if (\is_array($payValue)) {
                        foreach ($payValue as $key => $value) {
                            if ('expiration' === $key) {
                                $paymentMethodData[$payKey][$key] = date('m/y', $value);
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
     * Get payment title.
     *
     * @throws LocalizedException
     *
     * @return string|null
     */
    public function getPaymentTitle()
    {
        if ($this->getInfo() && $this->getInfo()->getOrder()) {
            return $this->getMethod()->getConfigData('title', $this->getInfo()->getOrder()->getStoreId());
        }

        return null;
    }

    /**
     * Get list of allowed payment information fields.
     */
    public function getInfoPayAllowed(): array
    {
        return self::INFO_PAY_ALLOWED;
    }
}
