<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Logger;

use Monei\MoneiPayment\Service\Logger;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger as MonologLogger;

/**
 * Class Handler.
 */
class Handler extends Base
{
    /**
     * File name.
     *
     * @var string
     */
    protected $fileName = Logger::LOG_FILE_PATH;

    /**
     * Logger type.
     *
     * @var int
     */
    protected $loggerType = MonologLogger::DEBUG;
}
