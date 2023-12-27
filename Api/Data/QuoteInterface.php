<?php
/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Data;

interface QuoteInterface
{
    public const ATTR_FIELD_MONEI_PAYMENT_ID = 'monei_payment_id';
    public const ATTR_FIELD_MONEI_SAVE_TOKENIZATION = 'save_monei_tokenization';
}
