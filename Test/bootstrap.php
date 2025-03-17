<?php

/**
 * Bootstrap file for PHPUnit tests
 *
 * @copyright Copyright © Monei (https://monei.com)
 */

// @codingStandardsIgnoreFile
$baseDir = dirname(__DIR__);

// Try to use module vendor autoload first
if (file_exists($baseDir . '/vendor/autoload.php')) {
    require_once $baseDir . '/vendor/autoload.php';
}
// Otherwise use Magento vendor autoload
elseif (file_exists(dirname(dirname(dirname($baseDir))) . '/vendor/autoload.php')) {
    require_once dirname(dirname(dirname($baseDir))) . '/vendor/autoload.php';
} else {
    echo 'Autoload not found';
    exit(1);
}

// Include stubs for testing
require_once __DIR__ . '/Unit/Stubs/MoneiStubs.php';

/** Define application path constants */
define('BP', $baseDir);  // Monei module base path
define('TESTS_TEMP_DIR', BP . '/var/tmp');

// Create temp directory if it doesn't exist
if (!is_dir(TESTS_TEMP_DIR)) {
    @mkdir(TESTS_TEMP_DIR, 0777, true);
}

/** Set custom error handler to convert warnings to exceptions */
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Only handle warnings and notices to avoid interfering with more serious errors
    if (!(error_reporting() & $errno)) {
        return;
    }

    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
