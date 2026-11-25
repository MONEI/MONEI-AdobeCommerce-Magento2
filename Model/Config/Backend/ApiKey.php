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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Monei\MoneiPayment\Service\Connect\ConnectService;

/**
 * Backend model of the API key fields.
 *
 * - The page never shows a scoped key (mk_, see ApiKeyField): an empty submit of a mode with a scoped key keeps it.
 * - A manual change of a key clears the signing secret of its mode, which belongs to the old key. "Use Default"
 *   deletes the scope's secret with the key, so the inherited key comes with its own secret.
 */
class ApiKey extends CredentialValue
{
    /**
     * @var WriterInterface
     */
    private WriterInterface $configWriter;

    /**
     * @var bool Whether the scope had no key of its own before this save
     */
    private bool $wasInherited = false;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param RequestInterface $request
     * @param MessageManager $messageManager
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
        WriterInterface $configWriter,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->configWriter = $configWriter;
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
     * Keep the scoped key in use on an empty submit
     *
     * @return $this
     */
    public function beforeSave()
    {
        $inUse = $this->getOldValue();
        if ((string) $this->getValue() === '' && strpos($inUse, 'mk_') === 0) {
            $this->setValue($inUse);
        }
        $this->wasInherited = !$this->getId();

        return parent::beforeSave();
    }

    /**
     * Clear the signing secret of the key's mode when the key changed
     *
     * An empty value at this scope, never a delete: a delete lets the scope inherit a parent scope's secret.
     *
     * @return $this
     */
    public function afterSave()
    {
        if ($this->isValueChanged()) {
            $this->configWriter->save($this->secretPath(), '', $this->getScope(), (int) $this->getScopeId());
        } elseif ($this->wasInherited && strpos((string) $this->getValue(), 'mk_') === 0) {
            // The inherited scoped key becomes this scope's own: so does its secret, or a later connect of the parent
            // scope pairs this key with another secret.
            $secret = (string) $this->_config->getValue(
                $this->secretPath(),
                $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $this->getScopeCode()
            );
            if ($secret !== '') {
                $this->configWriter->save($this->secretPath(), $secret, $this->getScope(), (int) $this->getScopeId());
            }
        }

        return parent::afterSave();
    }

    /**
     * Delete the scope's signing secret with the key: a stale local secret must not pair with the inherited key
     *
     * @return $this
     */
    public function afterDelete()
    {
        if (!$this->skipped) {
            $this->configWriter->delete($this->secretPath(), $this->getScope(), (int) $this->getScopeId());
        }

        return parent::afterDelete();
    }

    /**
     * Get the signing secret path of the key's mode
     *
     * @return string
     */
    private function secretPath(): string
    {
        return ConnectService::path(self::MODES[$this->getPath()][0], 'signing_secret');
    }
}
