<?php

/**
 * MONEI Payment Logger Handler
 *
 * @category  Payment
 * @package   Monei_MoneiPayment
 * @author    Monei Team <dev@monei.com>
 * @copyright Copyright © 2023 Monei (https://monei.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link      https://monei.com
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Logger;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Logger\Handler\Base;
use Magento\Store\Model\ScopeInterface;
use Monei\MoneiPayment\Model\Config\Source\LogLevel;
use Monei\MoneiPayment\Service\Logger;

/**
 * Log handler for Monei payment operations
 *
 * @category Payment
 * @package  Monei_MoneiPayment
 * @author   Monei Team <dev@monei.com>
 * @license  https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link     https://monei.com
 */
class Handler extends Base
{
    /**
     * Path to the log file for Monei payment operations.
     *
     * @var string
     */
    protected $fileName = Logger::LOG_FILE_PATH;

    /**
     * Configuration path for log level setting
     */
    private const XML_PATH_LOG_LEVEL = 'payment/monei/log_level';

    /**
     * Scope configuration
     *
     * @var ScopeConfigInterface
     */
    private $_scopeConfig;

    /**
     * Handler constructor
     *
     * @param ScopeConfigInterface $scopeConfig Scope configuration
     * @param File                 $filesystem  Filesystem driver
     * @param string|null          $filePath    Optional file path override
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        File $filesystem,
        $filePath = null
    ) {
        $this->_scopeConfig = $scopeConfig;
        parent::__construct($filesystem, $filePath);
        $this->_initLogLevel();
    }

    /**
     * Initialize log level from system configuration
     *
     * @return void
     */
    private function _initLogLevel(): void
    {
        $configuredLevel = (int) $this->_scopeConfig->getValue(
            self::XML_PATH_LOG_LEVEL,
            ScopeInterface::SCOPE_STORE
        );

        // Default to ERROR level if not configured
        $this->loggerType = $configuredLevel ?: LogLevel::LEVEL_ERROR;
    }
}
