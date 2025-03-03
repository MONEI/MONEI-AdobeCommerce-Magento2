<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monei\MoneiPayment\Service\Logger;
use Monolog\Logger as MonologLogger;

class Handler extends Base
{
    /**
     * Path to the log file for Monei payment operations.
     *
     * @var string
     */
    protected $fileName = Logger::LOG_FILE_PATH;

    /**
     * Logging level for Monei payment operations.
     *
     * @var int
     */
    protected $loggerType = MonologLogger::DEBUG;
}
