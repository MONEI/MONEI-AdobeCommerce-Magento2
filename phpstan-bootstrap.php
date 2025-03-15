<?php
/** PHPStan bootstrap file for Magento 2 modules */

// Path to Magento root
$magentoRootDir = realpath(__DIR__ . '/../../../../..');

// Include Composer autoloader
$composerAutoloader = $magentoRootDir . '/vendor/autoload.php';
if (file_exists($composerAutoloader)) {
    require_once $composerAutoloader;
}

// Include Magento's app/bootstrap.php if it exists
$magentoBootstrap = $magentoRootDir . '/app/bootstrap.php';
if (file_exists($magentoBootstrap)) {
    require_once $magentoBootstrap;
}

// Define some Magento constants if they're not already defined
if (!defined('BP')) {
    define('BP', $magentoRootDir);
}

// Register the module's own autoloader if needed
$moduleAutoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($moduleAutoloader)) {
    require_once $moduleAutoloader;
}
