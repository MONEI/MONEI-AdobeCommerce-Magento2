<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Data;

interface OrderInterface
{
    public const ATTR_FIELD_MONEI_PAYMENT_ID = QuoteInterface::ATTR_FIELD_MONEI_PAYMENT_ID;
    public const ATTR_FIELD_MONEI_SAVE_TOKENIZATION = QuoteInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION;
}
