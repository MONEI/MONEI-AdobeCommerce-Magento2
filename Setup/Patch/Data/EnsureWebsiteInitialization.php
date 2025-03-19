<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Patch to ensure the default website is properly initialized
 * before any operations that require it
 */
class EnsureWebsiteInitialization implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var WebsiteRepositoryInterface
     */
    private WebsiteRepositoryInterface $websiteRepository;

    /**
     * @var StoreRepositoryInterface
     */
    private StoreRepositoryInterface $storeRepository;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param StoreManagerInterface $storeManager
     * @param WebsiteRepositoryInterface $websiteRepository
     * @param StoreRepositoryInterface $storeRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        StoreManagerInterface $storeManager,
        WebsiteRepositoryInterface $websiteRepository,
        StoreRepositoryInterface $storeRepository,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->storeManager = $storeManager;
        $this->websiteRepository = $websiteRepository;
        $this->storeRepository = $storeRepository;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        try {
            // Verify default website exists and is accessible
            $defaultWebsite = $this->storeManager->getDefaultStoreView();
            if (!$defaultWebsite) {
                $this->logger->warning('Default website is not defined. This may cause issues with MONEI Payment module.');
            } else {
                $this->logger->info('Default website verified with ID: ' . $defaultWebsite->getId());
            }
        } catch (\Exception $e) {
            // Don't throw an exception to prevent installation from failing,
            // but log the issue for troubleshooting
            $this->logger->error(
                'Error verifying default website: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        // Make sure this runs after core modules that set up stores
        return [
            \Magento\Store\Setup\Patch\Schema\InitializeStoresAndWebsites::class
        ];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * Revert patch
     */
    public function revert()
    {
        // Nothing to revert since we're only verifying
    }
}
