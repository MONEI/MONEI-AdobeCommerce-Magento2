<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Logger;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Logger\Handler\Base;
use Magento\Store\Model\ScopeInterface;
use Monei\MoneiPayment\Model\Config\Source\LogLevel;
use Monei\MoneiPayment\Service\Logger;
use Monolog\Logger as MonologLogger;

/**
 * Log handler for Monei payment operations
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
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Filesystem\Driver\File $filesystem
     * @param string $filePath
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Filesystem\Driver\File $filesystem,
        $filePath = null
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($filesystem, $filePath);
        $this->initLogLevel();
    }

    /**
     * Initialize log level from system configuration
     *
     * @return void
     */
    private function initLogLevel(): void
    {
        $configuredLevel = (int) $this->scopeConfig->getValue(
            self::XML_PATH_LOG_LEVEL,
            ScopeInterface::SCOPE_STORE
        );

        // Default to ERROR level if not configured
        $this->loggerType = $configuredLevel ?: LogLevel::LEVEL_ERROR;
    }
}
