<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Monei\MoneiPayment\Registry\AccountId as RegistryAccountId;

/**
 * Backend model for handling MONEI account ID configuration. A page loaded before a Connect or a Disconnect does
 * not write its account ID back (CredentialValue).
 */
class AccountId extends CredentialValue
{
    /**
     * Registry for storing account ID.
     *
     * @var RegistryAccountId
     */
    private RegistryAccountId $registryAccountId;

    /**
     * Config writer for persistent storage.
     *
     * @var WriterInterface
     */
    private WriterInterface $configWriter;

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param RequestInterface $request
     * @param MessageManager $messageManager
     * @param RegistryAccountId $registryAccountId
     * @param WriterInterface $configWriter
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        RequestInterface $request,
        MessageManager $messageManager,
        RegistryAccountId $registryAccountId,
        WriterInterface $configWriter,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->registryAccountId = $registryAccountId;
        $this->configWriter = $configWriter;
        $this->registry = $registry;
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $request,
            $messageManager,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Process value before saving.
     *
     * @return AccountId
     */
    public function beforeSave(): AccountId
    {
        parent::beforeSave();
        if ($this->isSaveAllowed() && $this->getValue()) {
            $this->registryAccountId->set($this->getValue());

            // Store the value in a more persistent way than the registry
            $this->configWriter->save(
                'monei/account_id_storage',
                $this->getValue(),
                $this->getScope(),
                $this->getScopeId()
            );
        }

        return $this;
    }

    /**
     * Get registry object
     *
     * @return Registry
     */
    protected function _getRegistry(): Registry
    {
        return $this->registry;
    }
}
