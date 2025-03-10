<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

namespace Monei\MoneiPayment\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\NonTransactionableInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Data patch to update Monei order status labels with proper formatting.
 */
class UpdateOrderStatusLabels implements DataPatchInterface, NonTransactionableInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * Constructor for UpdateOrderStatusLabels
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [
            SetupSales::class
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
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $statusTable = $this->moduleDataSetup->getTable('sales_order_status');
        $connection = $this->moduleDataSetup->getConnection();

        // Define the proper status labels with correct formatting
        $statusLabels = [
            Monei::STATUS_MONEI_PENDING => __('MONEI - Pending'),
            Monei::STATUS_MONEI_AUTHORIZED => __('MONEI - Authorized'),
            Monei::STATUS_MONEI_EXPIRED => __('MONEI - Expired'),
            Monei::STATUS_MONEI_FAILED => __('MONEI - Failed'),
            Monei::STATUS_MONEI_SUCCEEDED => __('MONEI - Succeeded'),
            Monei::STATUS_MONEI_PARTIALLY_REFUNDED => __('MONEI - Partially Refunded'),
            Monei::STATUS_MONEI_REFUNDED => __('MONEI - Refunded'),
        ];

        // Update each status label
        foreach ($statusLabels as $statusCode => $label) {
            $connection->update(
                $statusTable,
                ['label' => $label],
                ['status = ?' => $statusCode]
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }
}
