<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Payment;

/**
 * Monei payment method class
 */
class Monei
{
    public const ORDER_STATUS_PENDING = 'PENDING';

    public const ORDER_STATUS_AUTHORIZED = 'AUTHORIZED';

    public const ORDER_STATUS_EXPIRED = 'EXPIRED';

    public const ORDER_STATUS_CANCELED = 'CANCELED';

    public const ORDER_STATUS_FAILED = 'FAILED';

    public const ORDER_STATUS_SUCCEEDED = 'SUCCEEDED';

    public const ORDER_STATUS_PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';

    public const ORDER_STATUS_REFUNDED = 'REFUNDED';

    public const STATUS_MONEI_PENDING = 'monei_pending';

    public const STATUS_MONEI_AUTHORIZED = 'monei_authorized';

    public const STATUS_MONEI_EXPIRED = 'monei_expired';

    public const STATUS_MONEI_FAILED = 'monei_failed';

    public const STATUS_MONEI_SUCCEDED = 'monei_succeeded';

    public const STATUS_MONEI_PARTIALLY_REFUNDED = 'monei_partially_refunded';

    public const STATUS_MONEI_REFUNDED = 'monei_refunded';

    public const CODE = 'monei';

    public const CARD_CODE = 'monei_card';

    public const CC_VAULT_CODE = 'monei_cc_vault';

    public const VAULT_TYPE = 'card';

    public const BIZUM_CODE = 'monei_bizum';
}
