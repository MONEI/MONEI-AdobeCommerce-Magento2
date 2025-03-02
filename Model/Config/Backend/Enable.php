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
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Shared\GetAvailableMoneiPaymentMethods;
use Monei\MoneiPayment\Service\Shared\GetMoneiPaymentCodesByMagentoPaymentCode;

/**
 * Get Monei payment method configuration class.
 */
class Enable extends Value
{
    protected ManagerInterface $messageManager;
    private GetMoneiPaymentCodesByMagentoPaymentCode $getMoneiPaymentCodesByMagentoPaymentCode;
    private GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods;

    /**
     * Enable constructor.
     */
    public function __construct(
        GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods,
        GetMoneiPaymentCodesByMagentoPaymentCode $getMoneiPaymentCodesByMagentoPaymentCode,
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ManagerInterface $messageManager,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->getAvailableMoneiPaymentMethods = $getAvailableMoneiPaymentMethods;
        $this->getMoneiPaymentCodesByMagentoPaymentCode = $getMoneiPaymentCodesByMagentoPaymentCode;
        $this->messageManager = $messageManager;
    }

    protected function getAvailablePaymentMethods(): array
    {
        return $this->getAvailableMoneiPaymentMethods->execute();
    }

    protected function getMoneiPaymentCodesByMagentoPaymentCode(string $magentoPaymentCode): array
    {
        return $this->getMoneiPaymentCodesByMagentoPaymentCode->execute($magentoPaymentCode);
    }
}
