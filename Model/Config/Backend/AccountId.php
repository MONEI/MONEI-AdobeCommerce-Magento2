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
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Model\Context;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Monei\MoneiPayment\Registry\AccountId as RegistryAccountId;

class AccountId extends Value
{
    /**
     * Registry for storing account ID.
     *
     * @var RegistryAccountId
     */
    private RegistryAccountId $registryAccountId;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * Constructor.
     *
     * @param RegistryAccountId $registryAccountId
     * @param Context $context
     * @param WriterInterface $configWriter
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        RegistryAccountId $registryAccountId,
        Context $context,
        WriterInterface $configWriter,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->configWriter = $configWriter;
        $this->registryAccountId = $registryAccountId;
        parent::__construct($context, $this->_getRegistry(), $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Process value before saving.
     *
     * @return AccountId
     */
    public function beforeSave(): AccountId
    {
        if ($this->getValue()) {
            $this->registryAccountId->set($this->getValue());
            
            // Store the value in a more persistent way than the registry
            $this->configWriter->save(
                'monei/account_id_storage',
                $this->getValue(),
                $this->getScope(),
                $this->getScopeId()
            );
        }

        return parent::beforeSave();
    }
    
    /**
     * Get registry object
     * 
     * @return object
     */
    protected function _getRegistry()
    {
        // Return empty object to satisfy parent constructor
        // This is a temporary solution while we migrate away from Registry
        return new \stdClass();
    }
}
