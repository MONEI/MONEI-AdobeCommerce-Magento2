<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

/**
 * Monei capture payment REST integration service interface.
 */
interface CapturePaymentInterface
{
    /**
     * Service execute method.
     *
     * @param array $data
     */
    public function execute(array $data): array;
}
