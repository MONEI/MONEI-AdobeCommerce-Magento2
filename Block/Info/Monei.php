<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\Info;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;
use Magento\Sales\Model\Order\Payment;
use Monei\MoneiPayment\Api\Data\PaymentInfoInterface;
use Monei\MoneiPayment\Api\Helper\PaymentMethodFormatterInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Gateway\Config\Config;
use Monei\MoneiPayment\Model\Payment\Monei as MoneiPayment;
use Monei\MoneiPayment\Service\Logger;
use Monei\Model\Payment as MoneiModelPayment;

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
    private PaymentMethodFormatterInterface $paymentMethodFormatter;

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
            $this->logger->debug('Payment Info: Missing order information');
            return null;
        }

        // Get payment ID from payment's additional information
        $payment = $this->getInfo();
        $monei_payment_id = $payment->getAdditionalInformation(PaymentInfoInterface::PAYMENT_ID);

        if (!$monei_payment_id) {
            $this->logger->debug('Payment Info: Missing Monei payment ID in order', [
                'order_id' => $this->getInfo()->getOrder()->getIncrementId()
            ]);
            return null;
        }

        $this->logger->debug('Payment Info: Retrieving payment data', [
            'payment_id' => $monei_payment_id,
            'order_id' => $this->getInfo()->getOrder()->getIncrementId()
        ]);

        try {
            /** @var Payment $payment */
            $payment = $this->paymentService->execute($monei_payment_id);

            // Convert SDK Payment object to array using json_encode/json_decode
            $this->logger->debug('Payment Info: Converting Payment object to array');
            $paymentData = json_decode(json_encode($payment), true);

            $this->logger->debug('Payment Info: Payment data structure', [
                'has_payment_method' => isset($paymentData['paymentMethod']),
                'payment_data_keys' => array_keys($paymentData)
            ]);

            if (!isset($paymentData['paymentMethod'])) {
                $this->logger->debug('Payment Info: Missing paymentMethod key in payment data');
                return null;
            }

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

            $this->logger->debug('Payment Info: Processed payment method result', [
                'result' => $result
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->debug('Payment Info: Exception during payment data processing', [
                'payment_id' => $monei_payment_id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
            $this->logger->debug('Payment method data is null');
            return null;
        }

        $this->logger->debug('Processing payment method data', [
            'payment_method_keys' => array_keys($paymentMethodData)
        ]);

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

        $this->logger->debug('Final processed payment method data', [
            'result' => $result
        ]);

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
