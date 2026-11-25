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
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Service\Connect\ConnectService;

/**
 * Backend model of the API key fields: a manual change of a key clears the signing secret of its mode, which
 * belongs to the old key.
 */
class ApiKey extends Value
{
    private const MODES = [
        MoneiPaymentModuleConfigInterface::TEST_API_KEY => 'test',
        MoneiPaymentModuleConfigInterface::PRODUCTION_API_KEY => 'live',
    ];

    /**
     * @var WriterInterface
     */
    private WriterInterface $configWriter;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
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
        WriterInterface $configWriter,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->configWriter = $configWriter;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Clear the signing secret of the key's mode when the key changed
     *
     * An empty value at this scope, never a delete: a delete lets the scope inherit a parent scope's secret.
     *
     * @return ApiKey
     */
    public function afterSave(): ApiKey
    {
        $mode = self::MODES[$this->getPath()] ?? null;
        if ($mode !== null && $this->isValueChanged()) {
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
