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
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface as ModuleConfig;
use Monei\MoneiPayment\Service\Connect\ConnectService;

/**
 * A config field that Connect and Disconnect also change: the API keys, the account IDs and the mode.
 *
 * Connect opens in a new tab, so the settings page loaded before a Connect or a Disconnect still posts the old
 * values. The page posts the fingerprint of each mode's key in use (ConnectButton). When a mode's key changed since
 * then, a save or a "Use Default" keeps that mode's key and account ID, and the mode field, and the page shows a
 * warning.
 */
class CredentialValue extends Value
{
    /** The request parameter of the fingerprints, by mode (live, test). */
    public const LOADED_PARAM = 'monei_loaded_keys';

    /** The modes whose key guards each field path. */
    public const MODES = [
        ModuleConfig::TEST_API_KEY => ['test'],
        ModuleConfig::TEST_ACCOUNT_ID => ['test'],
        ModuleConfig::PRODUCTION_API_KEY => ['live'],
        ModuleConfig::PRODUCTION_ACCOUNT_ID => ['live'],
        ModuleConfig::MODE => ['live', 'test'],
    ];

    /**
     * @var bool Whether this save or delete was skipped as stale
     */
    protected bool $skipped = false;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var MessageManager
     */
    private MessageManager $messageManager;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param RequestInterface $request
     * @param MessageManager $messageManager
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
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->request = $request;
        $this->messageManager = $messageManager;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Get the fingerprint of a key, which the settings page posts for the key it loaded
     *
     * @param string $key
     * @return string
     */
    public static function fingerprint(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Skip the save when a guarding mode's key changed since the page loaded
     *
     * @return $this
     */
    public function beforeSave()
    {
        if ($this->isStale()) {
            // No save runs no afterSave either.
            $this->_dataSaveAllowed = false;
            $this->skip();
        }

        return parent::beforeSave();
    }

    /**
     * Skip the delete ("Use Default" or "Use Website" checked) when a guarding mode's key changed since the page
     * loaded: a page loaded while the scope inherited the value posts the checked box
     *
     * @return $this
     */
    public function beforeDelete()
    {
        if ($this->isStale()) {
            // The resource model deletes the row of this id: none matches.
            $this->setId(null);
            $this->skip();
        }

        return parent::beforeDelete();
    }

    /**
     * Check whether a mode that guards this field has another key in use than the page loaded
     *
     * @return bool
     */
    private function isStale(): bool
    {
        $loaded = (array) $this->request->getParam(self::LOADED_PARAM);
        foreach (self::MODES[$this->getPath()] ?? [] as $mode) {
            $inUse = (string) $this->_config->getValue(
                ConnectService::path($mode, 'api_key'),
                $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $this->getScopeCode()
            );
            if (isset($loaded[$mode]) && $loaded[$mode] !== self::fingerprint($inUse)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mark this field as skipped and warn once per request
     *
     * @return void
     */
    private function skip(): void
    {
        $this->skipped = true;
        $this->messageManager->addUniqueMessages([
            $this->messageManager->createMessage(MessageInterface::TYPE_WARNING)->setText(
                (string) __('The MONEI keys changed in another tab, so the key fields were not saved. Reload this page.')
            ),
        ]);
    }
}
