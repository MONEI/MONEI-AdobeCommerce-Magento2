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
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;

/**
 * A config field that Connect and Disconnect also write: the mode and the API keys.
 *
 * Connect opens in a new tab, so the settings page loaded before a Connect or a Disconnect still posts the old
 * values. The page posts the fingerprint of each value it loaded (ConnectButton); a save or a "Use Default" of a
 * field whose value changed since then keeps the value now in use.
 */
class CredentialValue extends Value
{
    /** The request parameter of the fingerprints, by name (live, test, mode). */
    public const LOADED_PARAM = 'monei_loaded_keys';

    /** The fingerprint name of each field path. */
    public const NAMES = [
        MoneiPaymentModuleConfigInterface::TEST_API_KEY => 'test',
        MoneiPaymentModuleConfigInterface::PRODUCTION_API_KEY => 'live',
        MoneiPaymentModuleConfigInterface::MODE => 'mode',
    ];

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param RequestInterface $request
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
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->request = $request;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Get the fingerprint of a value, which the settings page posts for the value it loaded
     *
     * @param string $value
     * @return string
     */
    public static function fingerprint(string $value): string
    {
        return substr(hash('sha256', $value), 0, 16);
    }

    /**
     * Skip the save when the page loaded another value than the value now in use
     *
     * @return $this
     */
    public function beforeSave()
    {
        if ($this->isStale()) {
            // No save runs no afterSave either.
            $this->_dataSaveAllowed = false;
        }

        return parent::beforeSave();
    }

    /**
     * Skip the delete ("Use Default" or "Use Website" checked) when the page loaded another value than the value
     * now in use: a page loaded while the scope inherited the value posts the checked box
     *
     * @return $this
     */
    public function beforeDelete()
    {
        if ($this->isStale()) {
            // The resource model deletes the row of this id: none matches.
            $this->setId(null);
        }

        return parent::beforeDelete();
    }

    /**
     * Check whether the settings page loaded another value than the value now in use
     *
     * @return bool
     */
    private function isStale(): bool
    {
        $name = self::NAMES[$this->getPath()] ?? null;
        $loaded = (array) $this->request->getParam(self::LOADED_PARAM);

        return $name !== null && isset($loaded[$name]) && $loaded[$name] !== self::fingerprint($this->getOldValue());
    }
}
