<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Monolog\Logger as MonologLogger;

/**
 * Source model for log level configuration
 */
class LogLevel implements OptionSourceInterface
{
    public const LEVEL_ERROR = MonologLogger::ERROR;
    public const LEVEL_WARNING = MonologLogger::WARNING;
    public const LEVEL_INFO = MonologLogger::INFO;
    public const LEVEL_DEBUG = MonologLogger::DEBUG;

    /**
     * Get available log level options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::LEVEL_ERROR, 'label' => __('Error')],
            ['value' => self::LEVEL_WARNING, 'label' => __('Warning')],
            ['value' => self::LEVEL_INFO, 'label' => __('Info')],
            ['value' => self::LEVEL_DEBUG, 'label' => __('Debug')],
        ];
    }
}
