<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

namespace Monei\MoneiPayment\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\NonTransactionableInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Setup\SalesSetup;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Setup patch for adding Monei-specific sales attributes and order statuses.
 */
class SetupSales implements DataPatchInterface, NonTransactionableInterface
{
    /**
     * Module data setup interface for database operations.
     *
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * Sales setup for adding attributes to sales entities.
     *
     * @var SalesSetup
     */
    private SalesSetup $salesSetup;

    /**
     * Constructor for SetupSales.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup Module data setup interface
     * @param SalesSetup $salesSetup Sales setup for adding attributes
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        SalesSetup $salesSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->salesSetup = $salesSetup;
    }

    /**
     * Get dependencies for this patch.
     *
     * @return array Empty array as this patch has no dependencies
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get aliases for this patch.
     *
     * @return array Empty array as this patch has no aliases
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Get the version of this patch.
     *
     * @return string The version number
     */
    public static function getVersion(): string
    {
        return '1.1.9';
    }

    /**
     * Apply the patch.
     *
     * Adds Monei-specific order attributes and order statuses to the database.
     *
     * @return self
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $attributesInfo = [
            'label' => 'Monei payment Id',
            'type' => 'text',
            'position' => 150,
            'visible' => true,
            'required' => false,
            'system' => 0,
            'is_user_defined' => 1,
        ];

        $this->salesSetup->addAttribute(
            'order',
            'monei_payment_id',
            $attributesInfo
        );

        $statuses = [
            Monei::STATUS_MONEI_PENDING => __('MONEI - Pending'),
            Monei::STATUS_MONEI_AUTHORIZED => __('MONEI - Authorized'),
            Monei::STATUS_MONEI_EXPIRED => __('MONEI - Expired'),
            Monei::STATUS_MONEI_FAILED => __('MONEI - Failed'),
            Monei::STATUS_MONEI_SUCCEEDED => __('MONEI - Succeeded'),
            Monei::STATUS_MONEI_PARTIALLY_REFUNDED => __('MONEI - Partially Refunded'),
            Monei::STATUS_MONEI_REFUNDED => __('MONEI - Refunded'),
        ];
        foreach ($statuses as $code => $info) {
            $data[] = ['status' => $code, 'label' => $info];
        }
        $this->moduleDataSetup->getConnection()->insertArray(
            $this->moduleDataSetup->getTable('sales_order_status'),
            ['status', 'label'],
            $data
        );

        $data = [];
        $statuses = [
            'pending_payment' => [
                Monei::STATUS_MONEI_PENDING => false,
                Monei::STATUS_MONEI_AUTHORIZED => false,
            ],
            'canceled' => [
                Monei::STATUS_MONEI_EXPIRED => false,
                Monei::STATUS_MONEI_FAILED => false,
            ],
            'processing' => [
                Monei::STATUS_MONEI_SUCCEEDED => false,
                Monei::STATUS_MONEI_PARTIALLY_REFUNDED => false,
            ],
            'complete' => [
                Monei::STATUS_MONEI_REFUNDED => false,
            ],
        ];

        foreach ($statuses as $stateCode => $status) {
            foreach ($status as $statusCode => $isDefault) {
                $data[] = [
                    'status' => $statusCode,
                    'state' => $stateCode,
                    'is_default' => $isDefault,
                ];
            }
        }

        $this->moduleDataSetup->getConnection()->insertArray(
            $this->moduleDataSetup->getTable('sales_order_status_state'),
            ['status', 'state', 'is_default'],
            $data
        );

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }
}
