<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
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
