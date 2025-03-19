<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model;

use Monei\MoneiPayment\Service\Api\MoneiApiClient as ServiceMoneiApiClient;

/**
 * Client for interacting with the MONEI API
 * This class extends the Service implementation to maintain backward compatibility
 */
class MoneiApiClient extends ServiceMoneiApiClient
{
    // This class extends the Service implementation to maintain backward compatibility
    // No additional methods needed as it's just a compatibility layer
}
