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
    /**
     * Payment information fields allowed to be displayed.
     */
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
     * Service for retrieving payment information from Monei.
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
        if (!$monei_payment_id) {
            return null;
        }

        $paymentData = $this->paymentService->execute($monei_payment_id);
        if (!\array_key_exists('paymentMethod', $paymentData)) {
            return null;
        }

        return $this->processPaymentMethodData($paymentData['paymentMethod']);
    }

    /**
     * Process payment method data.
     *
     * @param array|null $paymentMethodData Payment method data
     *
     * @return array|null Processed payment method data
     */
    private function processPaymentMethodData(?array $paymentMethodData): ?array
    {
        if (!$paymentMethodData) {
            return null;
        }

        $result = [];
        foreach ($paymentMethodData as $payKey => $payValue) {
            if (!\is_array($payValue)) {
                $result[$payKey] = $payValue;

                continue;
            }

            foreach ($payValue as $key => $value) {
                $result[$key] = $key === 'expiration' ? date('m/y', $value) : $value;
            }
        }

        return $result;
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
