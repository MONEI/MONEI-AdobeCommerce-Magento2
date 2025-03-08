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
    /**
     * Message manager for admin notifications.
     *
     * @var ManagerInterface
     */
    protected ManagerInterface $messageManager;

    /**
     * Service to get Monei payment codes by Magento payment code.
     *
     * @var GetMoneiPaymentCodesByMagentoPaymentCode
     */
    private GetMoneiPaymentCodesByMagentoPaymentCode $getMoneiPaymentCodesByMagentoPaymentCode;

    /**
     * Service to get available Monei payment methods.
     *
     * @var GetAvailableMoneiPaymentMethods
     */
    private GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods;

    /**
     * Enable constructor.
     *
     * @param GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods
     * @param GetMoneiPaymentCodesByMagentoPaymentCode $getMoneiPaymentCodesByMagentoPaymentCode
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ManagerInterface $messageManager
     * @param ?AbstractResource $resource
     * @param ?AbstractDb $resourceCollection
     * @param array $data
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

    /**
     * Retrieve available payment methods from Monei.
     *
     * @return array List of available payment method codes
     */
    protected function getAvailablePaymentMethods(): array
    {
        return $this->getAvailableMoneiPaymentMethods->execute();
    }

    /**
     * Get Monei payment codes that correspond to a specific Magento payment code.
     *
     * @param string $magentoPaymentCode Magento payment method code
     *
     * @return array List of corresponding Monei payment method codes
     */
    protected function getMoneiPaymentCodesByMagentoPaymentCode(string $magentoPaymentCode): array
    {
        return $this->getMoneiPaymentCodesByMagentoPaymentCode->execute($magentoPaymentCode);
    }
}
