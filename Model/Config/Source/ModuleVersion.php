<?php declare(strict_types=1);

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

namespace Monei\MoneiPayment\Model\Config\Source;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Module\ResourceInterface;
use Magento\Framework\Registry;

/**
 * Config backend model for version display.
 */
class ModuleVersion extends Value
{
    /** @var ResourceInterface */
    protected $moduleResource;

    /**
     * Constructor for ModuleVersion backend model.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ResourceInterface $moduleResource
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ResourceInterface $moduleResource,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->moduleResource = $moduleResource;

        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Inject current installed module version as the config value.
     *
     * @return $this
     */
    public function afterLoad()
    {
        $version = $this->moduleResource->getDbVersion('Monei_MoneiPayment');

        $this->setValue($version);

        return $this;
    }

    /**
     * Get current installed module version.
     *
     * @return string|false
     */
    public function getModuleVersion()
    {
        return $this->moduleResource->getDbVersion('Monei_MoneiPayment');
    }
}
