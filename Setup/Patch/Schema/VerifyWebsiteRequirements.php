<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Psr\Log\LoggerInterface;

/**
 * Patch is responsible for verifying that prerequisites for the module are met
 */
class VerifyWebsiteRequirements implements SchemaPatchInterface
{
    /**
     * @var SchemaSetupInterface
     */
    private $schemaSetup;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SchemaSetupInterface $schemaSetup
     * @param LoggerInterface $logger
     */
    public function __construct(
        SchemaSetupInterface $schemaSetup,
        LoggerInterface $logger
    ) {
        $this->schemaSetup = $schemaSetup;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->schemaSetup->startSetup();

        $connection = $this->schemaSetup->getConnection();
        $storeTable = $this->schemaSetup->getTable('store');
        $storeWebsiteTable = $this->schemaSetup->getTable('store_website');

        // Only proceed if website/store tables already exist
        if ($connection->isTableExists($storeTable) && $connection->isTableExists($storeWebsiteTable)) {
            // Check if website table has records
            $websiteCount = $connection->fetchOne(
                $connection->select()->from($storeWebsiteTable, 'COUNT(*)')
            );

            // Check if store table has records
            $storeCount = $connection->fetchOne(
                $connection->select()->from($storeTable, 'COUNT(*)')
            );

            // Log warning if prerequisites not met
            if (!$websiteCount || !$storeCount) {
                $this->logger->warning(
                    'MONEI Payment module requires a default website and store to be set up before installation. '
                    . 'Websites: ' . $websiteCount . ', Stores: ' . $storeCount
                );
            } else {
                $this->logger->info(
                    'MONEI Payment prerequisites verified. '
                    . 'Websites: ' . $websiteCount . ', Stores: ' . $storeCount
                );
            }
        }

        $this->schemaSetup->endSetup();
        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
