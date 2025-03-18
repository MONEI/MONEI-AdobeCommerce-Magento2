<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\Model\PaymentMethods;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Helper class for payment method icons and formatting
 */
class PaymentMethod
{
    /**
     * Internal payment type identifiers used for icon and asset handling.
     * These are different from the payment method codes in Monei class which are used
     * for Magento's payment system identification.
     */
    private const TYPE_BIZUM = 'bizum';

    private const TYPE_GOOGLE_PAY = 'google_pay';
    private const TYPE_APPLE_PAY = 'apple_pay';
    private const TYPE_MBWAY = 'mbway';
    private const TYPE_MULTIBANCO = 'multibanco';
    private const TYPE_CARD = 'card';
    private const TYPE_DEFAULT = 'default';
    private const TYPE_PAYPAL = 'paypal';

    /**
     * Card brand identifiers used for specific card icons
     */
    private const CARD_TYPE_AMEX = 'amex';

    private const CARD_TYPE_DINERS = 'diners';
    private const CARD_TYPE_DISCOVER = 'discover';
    private const CARD_TYPE_JCB = 'jcb';
    private const CARD_TYPE_MAESTRO = 'maestro';
    private const CARD_TYPE_MASTERCARD = 'mastercard';
    private const CARD_TYPE_UNIONPAY = 'unionpay';
    private const CARD_TYPE_VISA = 'visa';

    /**
     * Map of Monei API payment method codes to internal icon type identifiers.
     * Maps the standardized API codes from PaymentMethods constants to our internal
     * asset identifiers. This allows us to maintain backward compatibility while
     * supporting the official MONEI SDK types.
     *
     * @var array
     */
    private $moneiToInternalTypeMap = [
        PaymentMethods::PAYMENT_METHODS_BIZUM => self::TYPE_BIZUM,
        PaymentMethods::PAYMENT_METHODS_GOOGLE_PAY => self::TYPE_GOOGLE_PAY,
        PaymentMethods::PAYMENT_METHODS_APPLE_PAY => self::TYPE_APPLE_PAY,
        PaymentMethods::PAYMENT_METHODS_MBWAY => self::TYPE_MBWAY,
        PaymentMethods::PAYMENT_METHODS_MULTIBANCO => self::TYPE_MULTIBANCO,
        PaymentMethods::PAYMENT_METHODS_CARD => self::TYPE_CARD,
        PaymentMethods::PAYMENT_METHODS_PAYPAL => self::TYPE_PAYPAL
    ];

    /**
     * @var array
     */
    private $methodDetails = [];

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Repository
     */
    private $assetRepo;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ThemeProviderInterface
     */
    private $themeProvider;

    /**
     * @var \Magento\Framework\View\Design\Theme\ThemeInterface
     */
    private $themeModel = null;

    /**
     * @var Emulation
     */
    private $appEmulation;

    /**
     * PaymentMethod constructor.
     *
     * @param RequestInterface $request
     * @param Repository $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param ThemeProviderInterface $themeProvider
     * @param Emulation $appEmulation
     */
    public function __construct(
        RequestInterface $request,
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ThemeProviderInterface $themeProvider,
        Emulation $appEmulation
    ) {
        $this->request = $request;
        $this->assetRepo = $assetRepo;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->themeProvider = $themeProvider;
        $this->appEmulation = $appEmulation;
    }

    /**
     * Get card icon URL
     *
     * @param string $brand Card brand
     * @return string|null Icon URL
     */
    public function getCardIcon($brand)
    {
        $icon = $this->getPaymentMethodIcon($brand);
        if ($icon) {
            return $icon;
        }

        return $this->getPaymentMethodIcon(self::TYPE_DEFAULT);
    }

    /**
     * Get formatted card label
     *
     * @param object $card Card details
     * @param bool $hideLast4 Whether to hide last 4 digits
     * @return string Formatted card label
     */
    public function getCardLabel($card, $hideLast4 = false)
    {
        if (!empty($card->last4) && !$hideLast4) {
            return __('•••• %1', $card->last4);
        }

        if (!empty($card->brand)) {
            return $this->getCardName($card->brand);
        }

        return __('Card');
    }

    /**
     * Get card name from brand
     *
     * @param string $brand Card brand
     * @return string Card name
     */
    protected function getCardName($brand)
    {
        if (empty($brand)) {
            return 'Card';
        }

        $details = $this->getPaymentMethodDetails();
        if (isset($details[$brand])) {
            return $details[$brand]['name'];
        }

        return ucfirst($brand);
    }

    /**
     * Get payment method icon
     *
     * @param string $code Payment method code
     * @return string|null Icon URL
     */
    public function getPaymentMethodIcon($code)
    {
        $details = $this->getPaymentMethodDetails();
        if (isset($details[$code])) {
            return $details[$code]['icon'];
        }

        return null;
    }

    /**
     * Get payment method name
     *
     * @param string $code Payment method code
     * @return string Payment method name
     */
    public function getPaymentMethodName($code)
    {
        $details = $this->getPaymentMethodDetails();

        if (isset($details[$code])) {
            return $details[$code]['name'];
        }

        return ucwords(str_replace('_', ' ', $code));
    }

    /**
     * Get security code (CVC) icon
     *
     * @return string Icon URL
     */
    public function getCVCIcon()
    {
        return $this->getViewFileUrl('Monei_MoneiPayment::img/security-code.svg');
    }

    /**
     * Get all payment method details
     *
     * @return array Payment method details
     */
    public function getPaymentMethodDetails()
    {
        if (!empty($this->methodDetails)) {
            return $this->methodDetails;
        }

        return $this->methodDetails = [
            // Payment methods
            self::TYPE_BIZUM => [
                'name' => 'Bizum',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/bizum.svg'),
                'width' => '70px',
                'height' => '22px'
            ],
            self::TYPE_GOOGLE_PAY => [
                'name' => 'Google Pay',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/google-pay.svg'),
                'width' => '50px',
                'height' => '22px'
            ],
            self::TYPE_APPLE_PAY => [
                'name' => 'Apple Pay',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/apple-pay.svg'),
                'width' => '50px',
                'height' => '22px'
            ],
            self::TYPE_MBWAY => [
                'name' => 'MBWay',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/mbway.svg'),
                'width' => '45px',
                'height' => '22px'
            ],
            self::TYPE_MULTIBANCO => [
                'name' => 'Multibanco',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/multibanco.svg'),
                'width' => '105px',
                'height' => '22px'
            ],
            self::TYPE_DEFAULT => [
                'name' => 'Card',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/cards/default.svg'),
                'width' => '40px',
                'height' => '22px'
            ],
            self::TYPE_PAYPAL => [
                'name' => 'PayPal',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/paypal.svg'),
                'width' => '83px',
                'height' => '22px'
            ],
            // Card brands
            self::CARD_TYPE_AMEX => [
                'name' => 'American Express',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/cards/amex.svg'),
                'width' => '40px',
                'height' => '22px'
            ],
            self::CARD_TYPE_DINERS => [
                'name' => 'Diners Club',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/cards/diners.svg'),
                'width' => '40px',
                'height' => '22px'
            ],
            self::CARD_TYPE_DISCOVER => [
                'name' => 'Discover',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/cards/discover.svg'),
                'width' => '40px',
                'height' => '22px'
            ],
            self::CARD_TYPE_JCB => [
                'name' => 'JCB',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/cards/jcb.svg'),
                'width' => '40px',
                'height' => '22px'
            ],
            self::CARD_TYPE_MAESTRO => [
                'name' => 'Maestro',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/cards/maestro.svg'),
                'width' => '40px',
                'height' => '22px'
            ],
            self::CARD_TYPE_MASTERCARD => [
                'name' => 'MasterCard',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/cards/mastercard.svg'),
                'width' => '40px',
                'height' => '22px'
            ],
            self::CARD_TYPE_UNIONPAY => [
                'name' => 'UnionPay',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/cards/unionpay.svg'),
                'width' => '40px',
                'height' => '22px'
            ],
            self::CARD_TYPE_VISA => [
                'name' => 'Visa',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/cards/visa.svg'),
                'width' => '40px',
                'height' => '22px'
            ],
            // Generic card
            self::TYPE_CARD => [
                'name' => 'Card',
                'icon' => $this->getViewFileUrl('Monei_MoneiPayment::img/cards/default.svg'),
                'width' => '40px',
                'height' => '22px'
            ],
        ];
    }

    /**
     * Get view file URL
     *
     * @param string $fileId File ID
     * @return string|null File URL
     */
    protected function getViewFileUrl($fileId)
    {
        try {
            $params = [
                '_secure' => $this->request->isSecure()
            ];

            // Start store emulation to ensure proper area context
            $storeId = $this->storeManager->getStore()->getId();
            $this->appEmulation->startEnvironmentEmulation($storeId);

            try {
                $url = $this->assetRepo->getUrlWithParams($fileId, $params);
            } finally {
                // Stop emulation regardless of success or failure
                $this->appEmulation->stopEnvironmentEmulation();
            }

            return $url;
        } catch (LocalizedException $e) {
            return null;
        }
    }

    /**
     * Get theme model
     *
     * @return \Magento\Framework\View\Design\Theme\ThemeInterface
     */
    protected function getThemeModel()
    {
        if ($this->themeModel) {
            return $this->themeModel;
        }

        $themeId = $this->scopeConfig->getValue(
            DesignInterface::XML_PATH_THEME_ID,
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        $this->themeModel = $this->themeProvider->getThemeById($themeId);

        return $this->themeModel;
    }

    /**
     * Get icon from payment type and optional card type
     *
     * @param string $type Payment type
     * @param string $cardType Card type (default: visa)
     * @param string|null $format Image format (optional)
     * @return string Icon URL
     */
    public function getIconFromPaymentType($type, $cardType = self::CARD_TYPE_VISA, $format = null)
    {
        // Map payment method types from Monei API to our internal constants
        if (isset($this->moneiToInternalTypeMap[$type])) {
            $type = $this->moneiToInternalTypeMap[$type];
        } else {
            // Check if this is a Magento payment method code and try to map via PAYMENT_METHOD_MAP
            foreach (Monei::PAYMENT_METHOD_MAP as $magentoCode => $moneiCodes) {
                if (in_array($type, $moneiCodes) && isset($this->moneiToInternalTypeMap[$type])) {
                    $type = $this->moneiToInternalTypeMap[$type];

                    break;
                }
            }
        }

        if ($type === self::TYPE_CARD) {
            $icon = $this->getCardIcon($cardType);
        } else {
            $icon = $this->getPaymentMethodIcon($type);
        }

        if (!$icon) {
            $icon = $this->getPaymentMethodIcon(self::TYPE_DEFAULT);
        }

        if ($format) {
            $icon = str_replace('.svg', ".$format", $icon);
        }

        return $icon;
    }

    /**
     * Get payment method icon width
     *
     * @param string $code Payment method code
     * @return string|null Icon width
     */
    public function getPaymentMethodWidth($code)
    {
        $details = $this->getPaymentMethodDetails();
        if (isset($details[$code], $details[$code]['width'])) {
            return $details[$code]['width'];
        }

        return null;
    }

    /**
     * Get payment method icon height
     *
     * @param string $code Payment method code
     * @return string|null Icon height
     */
    public function getPaymentMethodHeight($code)
    {
        $details = $this->getPaymentMethodDetails();
        if (isset($details[$code], $details[$code]['height'])) {
            return $details[$code]['height'];
        }

        return null;
    }

    /**
     * Get payment method dimensions
     *
     * @param string $code Payment method code
     * @return array Width and height values
     */
    public function getPaymentMethodDimensions($code)
    {
        $details = $this->getPaymentMethodDetails();
        if (isset($details[$code])) {
            return [
                'width' => $details[$code]['width'] ?? '40px',
                'height' => $details[$code]['height'] ?? '30px'
            ];
        }

        return [
            'width' => '40px',
            'height' => '30px'
        ];
    }

    /**
     * Get payment method information by Monei payment method code
     *
     * @param string $moneiCode The Monei payment method code
     * @return array|null Payment method details
     */
    public function getPaymentMethodByMoneiCode($moneiCode)
    {
        if (isset($this->moneiToInternalTypeMap[$moneiCode])) {
            $internalType = $this->moneiToInternalTypeMap[$moneiCode];
            $details = $this->getPaymentMethodDetails();

            if (isset($details[$internalType])) {
                return $details[$internalType];
            }
        }

        return null;
    }
}
