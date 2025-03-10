<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Payment;

use Monei\Model\PaymentMethods;
use Monei\MoneiPayment\Model\Payment\Status;

/**
 * Monei payment method class.
 */
class Monei
{
    public const STATUS_MONEI_PENDING = 'monei_pending';

    public const STATUS_MONEI_AUTHORIZED = 'monei_authorized';

    public const STATUS_MONEI_EXPIRED = 'monei_expired';

    public const STATUS_MONEI_FAILED = 'monei_failed';

    /**
     * Constant defining the succeeded status.
     * The original misspelled version was STATUS_MONEI_SUCCEDED but had the correctly spelled value 'monei_succeeded'
     */
    public const STATUS_MONEI_SUCCEEDED = 'monei_succeeded';

    /**
     * @deprecated Use STATUS_MONEI_SUCCEEDED instead. Kept for backward compatibility.
     */
    public const STATUS_MONEI_SUCCEDED = self::STATUS_MONEI_SUCCEEDED;

    public const STATUS_MONEI_PARTIALLY_REFUNDED = 'monei_partially_refunded';

    public const STATUS_MONEI_REFUNDED = 'monei_refunded';

    public const CODE = 'monei';

    public const CARD_CODE = 'monei_card';

    public const CC_VAULT_CODE = 'monei_cc_vault';

    public const VAULT_TYPE = 'card';

    public const BIZUM_CODE = 'monei_bizum';

    public const GOOGLE_APPLE_CODE = 'monei_google_apple';

    public const MULTIBANCO_REDIRECT_CODE = 'monei_multibanco_redirect';

    public const MBWAY_REDIRECT_CODE = 'monei_mbway_redirect';

    public const PAYMENT_METHODS_MONEI = [
        self::CODE,
        self::CARD_CODE,
        self::CC_VAULT_CODE,
        self::BIZUM_CODE,
        self::GOOGLE_APPLE_CODE,
        self::MULTIBANCO_REDIRECT_CODE,
        self::MBWAY_REDIRECT_CODE,
    ];

    public const MONEI_GOOGLE_CODE = PaymentMethods::PAYMENT_METHODS_GOOGLE_PAY;

    public const MONEI_APPLE_CODE = PaymentMethods::PAYMENT_METHODS_APPLE_PAY;

    public const MAPPER_MAGENTO_MONEI_PAYMENT_CODE = [
        self::BIZUM_CODE => [PaymentMethods::PAYMENT_METHODS_BIZUM],
        self::GOOGLE_APPLE_CODE => [self::MONEI_GOOGLE_CODE, self::MONEI_APPLE_CODE],
        self::CARD_CODE => [PaymentMethods::PAYMENT_METHODS_CARD],
        self::MULTIBANCO_REDIRECT_CODE => [PaymentMethods::PAYMENT_METHODS_MULTIBANCO],
        self::MBWAY_REDIRECT_CODE => [PaymentMethods::PAYMENT_METHODS_MBWAY],
    ];

    public const MAPPER_MAGENTO_MONEI_PAYMENT_CODE_REDIRECT = [
        self::MULTIBANCO_REDIRECT_CODE => [PaymentMethods::PAYMENT_METHODS_MULTIBANCO],
        self::MBWAY_REDIRECT_CODE => [PaymentMethods::PAYMENT_METHODS_MBWAY],
    ];
}
