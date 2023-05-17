<?php

  namespace Monei\MoneiPayment\Setup\Patch\Data;
  use Magento\Framework\Setup\ModuleDataSetupInterface;
  use Magento\Framework\Setup\Patch\DataPatchInterface;
  use Monei\MoneiPayment\Model\Payment\Monei;

  class SetupSales implements DataPatchInterface
  {
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /** @var EavSetupFactory */
    private SalesSetupFactory $salesSetupFactory;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param SalesSetupFactory $salesSetupFactory
     */
    public function __construct(
      ModuleDataSetupInterface $moduleDataSetup,
      SalesSetupFactory $salesSetupFactory
    ) {
      $this->moduleDataSetup = $moduleDataSetup;
      $this->salesSetupFactory = $salesSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
      return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
      return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion()
    {
      return '1.0.0';
    }

    public function apply() {
      $setup = $this->salesSetupFactory->create(['setup' => $this->moduleDataSetup]);

      $salesSetup = $this->salesSetupFactory->create(['setup' => $setup]);

      $attributesInfo = [
        'label' => 'Monei payment Id',
        'type' => Table::TYPE_TEXT,
        'position' => 150,
        'visible' => true,
        'required' => false,
        'system' => 0,
        'is_user_defined' => 1,
      ];

      $salesSetup->addAttribute(
        'order',
        'monei_payment_id',
        $attributesInfo
      );

      $statuses = [
        Monei::STATUS_MONEI_PENDING => __('Monei - pending'),
        Monei::STATUS_MONEI_AUTHORIZED => __('Monei - Preauthorized'),
        Monei::STATUS_MONEI_EXPIRED => __('Monei - expired'),
        Monei::STATUS_MONEI_FAILED => __('Monei - failed'),
        Monei::STATUS_MONEI_SUCCEDED => __('Monei - succeeded'),
        Monei::STATUS_MONEI_PARTIALLY_REFUNDED => __('Monei - partially refunded'),
        Monei::STATUS_MONEI_REFUNDED => __('Monei - refunded'),
      ];
      foreach ($statuses as $code => $info) {
        $data[] = ['status' => $code, 'label' => $info];
      }
      $setup->getConnection()->insertArray(
        $setup->getTable('sales_order_status'),
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
          Monei::STATUS_MONEI_SUCCEDED => false,
          Monei::STATUS_MONEI_PARTIALLY_REFUNDED => false,
        ],
        'complete' => [
          Monei::STATUS_MONEI_REFUNDED => false,
        ],
      ];

      $statesCode = [];
      foreach ($statuses as $stateCode => $status) {
        foreach ($status as $statusCode => $isDefault) {
          $statesCode[] = $stateCode;
          $data[] = [
            'status' => $statusCode,
            'state' => $stateCode,
            'is_default' => $isDefault,
          ];
        }
      }

      $setup->getConnection()->insertArray(
        $setup->getTable('sales_order_status_state'),
        ['status', 'state', 'is_default'],
        $data
      );

      $setup->endSetup();
    }
  }