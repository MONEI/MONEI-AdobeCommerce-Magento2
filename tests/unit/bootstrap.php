<?php

/**
 * PHPUnit bootstrap for running the module's unit tests without a Magento
 * installation.
 *
 * Mirrors dev/tests/unit/framework/{bootstrap,autoload}.php from Magento. The
 * generated-classes autoloader is what makes this work: tests mock
 * OrderFactory, TransactionFactory and OrderPaymentExtensionInterface, which
 * only exist after setup:di:compile. The autoloader synthesises them on demand
 * instead, so the suite runs on a bare composer install.
 */

declare(strict_types=1);

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Code\Generator\Io;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\TestFramework\Unit\Autoloader\ExtensionAttributesGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\ExtensionAttributesInterfaceGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\FactoryGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader;
use Magento\Framework\TestFramework\Unit\Autoloader\ProxyGenerator;

$moduleRoot = dirname(__DIR__, 2);

// The module's own vendor when installed standalone (CI); otherwise the vendor
// of the Magento installation it lives in (app/code/Monei/MoneiPayment).
$candidates = [
    $moduleRoot . '/vendor/autoload.php',
    dirname($moduleRoot, 4) . '/vendor/autoload.php',
];

$autoload = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}

if (null === $autoload) {
    throw new RuntimeException('No vendor/autoload.php found. Run composer install.');
}

require_once $autoload;

if (!defined('TESTS_TEMP_DIR')) {
    define('TESTS_TEMP_DIR', sys_get_temp_dir() . '/monei-magento-unit');
}

$generatorIo = new Io(
    new File(),
    TESTS_TEMP_DIR . '/' . DirectoryList::getDefaultConfig()[DirectoryList::GENERATED_CODE][DirectoryList::PATH]
);
$generatedCodeAutoloader = new GeneratedClassesAutoloader(
    [
        new ExtensionAttributesGenerator(),
        new ExtensionAttributesInterfaceGenerator(),
        new FactoryGenerator(),
        new ProxyGenerator(),
    ],
    $generatorIo
);
spl_autoload_register([$generatedCodeAutoloader, 'load']);

\Magento\Framework\Phrase::setRenderer(new \Magento\Framework\Phrase\Renderer\Placeholder());

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('precision', '14');
ini_set('serialize_precision', '14');
