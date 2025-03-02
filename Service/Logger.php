<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Monolog\Logger as MonologLogger;

/**
 * Monei logger class.
 */
class Logger extends MonologLogger
{
    public const LOG_FILE_PATH = '/var/log/monei.log';
}
