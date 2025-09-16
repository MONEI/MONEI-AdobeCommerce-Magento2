<?php

/**
 * MONEI Payment Module Log Level Source Model
 *
 * @category  Payment
 * @package   Monei_MoneiPayment
 * @author    Monei Team <dev@monei.com>
 * @copyright Copyright © 2023 Monei (https://monei.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link      https://monei.com
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for log level configuration
 *
 * @license  https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link     https://monei.com
 */
class LogLevel implements OptionSourceInterface
{
    // Use numeric values directly to support both Monolog 2.x and 3.x
    // These values are consistent across both versions
    public const LEVEL_ERROR = 400;    // MonologLogger::ERROR or Level::Error->value
    public const LEVEL_WARNING = 300;  // MonologLogger::WARNING or Level::Warning->value
    public const LEVEL_INFO = 200;     // MonologLogger::INFO or Level::Info->value
    public const LEVEL_DEBUG = 100;    // MonologLogger::DEBUG or Level::Debug->value

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
