<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\Info;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Block\Info;
use Magento\Sales\Model\Order\Payment;
use Monei\MoneiPayment\Api\Data\PaymentInfoInterface;
use Monei\MoneiPayment\Api\Helper\PaymentMethodFormatterInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Service\Logger;

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
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var PaymentMethodFormatterInterface
     */
    public PaymentMethodFormatterInterface $paymentMethodFormatter;

    /**
     * Constructor.
     *
     * @param GetPaymentInterface $paymentService
     * @param Template\Context $context
     * @param Logger $logger
     * @param PaymentMethodFormatterInterface $paymentMethodFormatter
     * @param array $data
     */
    public function __construct(
        GetPaymentInterface $paymentService,
        Template\Context $context,
        Logger $logger,
        PaymentMethodFormatterInterface $paymentMethodFormatter,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->paymentService = $paymentService;
        $this->logger = $logger;
        $this->paymentMethodFormatter = $paymentMethodFormatter;
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

        // Get payment ID from payment's additional information
        $payment = $this->getInfo();
        $monei_payment_id = $payment->getAdditionalInformation(PaymentInfoInterface::PAYMENT_ID);

        if (!$monei_payment_id) {
            return null;
        }

        try {
            /** @var Payment $payment */
            $payment = $this->paymentService->execute($monei_payment_id);

            $paymentData = json_decode(json_encode($payment), true);
            $result = $this->processPaymentMethodData($paymentData['paymentMethod']);

            // Add the payment ID to the result
            if (isset($paymentData['id'])) {
                $result['id'] = $paymentData['id'];
            } else {
                // Fallback to the ID from the order if not available in API response
                $result['id'] = $monei_payment_id;
            }

            // Add the authorization code if available
            if (isset($paymentData['authorizationCode']) && !empty($paymentData['authorizationCode'])) {
                $result['authorizationCode'] = $paymentData['authorizationCode'];
            }

            return $result;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Process payment method data.
     *
     * @param array|null $paymentMethodData Payment method data
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
     * Get formatted payment method display
     *
     * @return string
     */
    public function getFormattedPaymentMethodDisplay()
    {
        $paymentInfo = $this->getPaymentInfo();
        if (!is_array($paymentInfo)) {
            return '';
        }

        return $this->paymentMethodFormatter->formatPaymentMethodDisplay($paymentInfo);
    }

    /**
     * Format wallet display from tokenization method
     *
     * @param string $walletValue
     * @return string
     */
    public function formatWalletDisplay(string $walletValue): string
    {
        return $this->paymentMethodFormatter->formatWalletDisplay($walletValue);
    }

    /**
     * Format phone number with proper spacing
     *
     * @param string $phoneNumber
     * @return string
     */
    public function formatPhoneNumber(string $phoneNumber): string
    {
        return $this->paymentMethodFormatter->formatPhoneNumber($phoneNumber);
    }

    /**
     * Get list of allowed payment information fields.
     */
    public function getInfoPayAllowed(): array
    {
        return self::INFO_PAY_ALLOWED;
    }
}
