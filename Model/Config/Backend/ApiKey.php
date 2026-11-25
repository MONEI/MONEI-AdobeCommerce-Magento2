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
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Monei\MoneiPayment\Service\Connect\ConnectService;

/**
 * Backend model of the API key fields.
 *
 * - The page never shows a scoped key (mk_): only "•••• <last 4>". An empty or masked submit keeps the key in use.
 * - A manual change of a key clears the signing secret of its mode, which belongs to the old key.
 */
class ApiKey extends CredentialValue
{
    private const MASK = '••••';

    /**
     * @var WriterInterface
     */
    private WriterInterface $configWriter;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param RequestInterface $request
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
        WriterInterface $configWriter,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->configWriter = $configWriter;
        parent::__construct($context, $registry, $config, $cacheTypeList, $request, $resource, $resourceCollection, $data);
    }

    /**
     * Get the masked form of a key
     *
     * @param string $key
     * @return string
     */
    public static function mask(string $key): string
    {
        return self::MASK . ' ' . substr($key, -4);
    }

    /**
     * Show a scoped key masked
     *
     * @return $this
     */
    public function afterLoad()
    {
        if (strpos((string) $this->getValue(), 'mk_') === 0) {
            $this->setValue(self::mask((string) $this->getValue()));
        }

        return parent::afterLoad();
    }

    /**
     * Keep the key in use on a masked submit, or on an empty submit of a scoped key
     *
     * @return $this
     */
    public function beforeSave()
    {
        $value = (string) $this->getValue();
        $inUse = $this->getOldValue();
        if (strpos($value, self::MASK) === 0 || ($value === '' && strpos($inUse, 'mk_') === 0)) {
            $this->setValue($inUse);
        }

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
        $mode = self::NAMES[$this->getPath()] ?? null;
        if (in_array($mode, ['live', 'test'], true) && $this->isValueChanged()) {
            $this->configWriter->save(
                ConnectService::path($mode, 'signing_secret'),
                '',
                $this->getScope(),
                (int) $this->getScopeId()
            );
        }

        return parent::afterSave();
    }
}
