<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Monei\MoneiPayment\Registry\AccountId as RegistryAccountId;

class AccountId extends Value
{
    /**
     * Registry for storing account ID
     *
     * @var RegistryAccountId
     */
    private RegistryAccountId $registryAccountId;

    /**
     * Constructor
     *
     * @param RegistryAccountId $registryAccountId
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        RegistryAccountId $registryAccountId,
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->registryAccountId = $registryAccountId;
    }

    /**
     * Process value before saving
     *
     * @return AccountId
     */
    public function beforeSave(): AccountId
    {
        if ($this->getValue()) {
            $this->registryAccountId->set($this->getValue());
        }

        return parent::beforeSave();
    }
}
