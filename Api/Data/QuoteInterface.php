<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Data;

interface QuoteInterface
{
    public const ATTR_FIELD_MONEI_PAYMENT_ID = 'monei_payment_id';
    public const ATTR_FIELD_MONEI_SAVE_TOKENIZATION = 'save_monei_tokenization';
}
