<?php
/**
 * VSCode IDE Helper for Magento 2 Development
 *
 * This file helps VSCode and Intelephense recognize Magento class paths.
 * It is not included in the module, just used for development.
 *
 * How to use:
 * 1. Make sure you have the Intelephense extension installed in VSCode
 * 2. Ensure your .vscode/settings.json has the correct include paths
 * 3. This file can be used as a reference for autocompletion
 */

// Define the path to the Magento root directory relative to this file
define('MAGENTO_ROOT', dirname(dirname(dirname(dirname(__DIR__)))));

// Include the Magento autoloader
// This helps with class autocompletion and navigation
if (file_exists(MAGENTO_ROOT . '/vendor/autoload.php')) {
    require_once MAGENTO_ROOT . '/vendor/autoload.php';
}

// Include the Magento app bootstrap
// This helps with constant definitions and framework initialization
if (file_exists(MAGENTO_ROOT . '/app/bootstrap.php')) {
    require_once MAGENTO_ROOT . '/app/bootstrap.php';
}

/**
 * Common Magento 2 Class References
 *
 * These class references help VSCode with autocompletion.
 * You can add more classes that you commonly use in your module.
 */

// Core Framework Classes
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Framework\View\Result\PageFactory;

// Payment Related Classes
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Payment\Gateway\ConfigFactoryInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

// Add more classes as needed for your specific module
