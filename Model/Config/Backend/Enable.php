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
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Shared\AvailablePaymentMethods;
use Monei\MoneiPayment\Service\Shared\PaymentMethodMap;

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
     * @var PaymentMethodMap
     */
    private PaymentMethodMap $paymentMethodMap;

    /**
     * Service to get available Monei payment methods.
     *
     * @var AvailablePaymentMethods
     */
    private AvailablePaymentMethods $availablePaymentMethods;

    /**
     * Enable constructor.
     *
     * @param AvailablePaymentMethods $availablePaymentMethods
     * @param PaymentMethodMap $paymentMethodMap
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
        AvailablePaymentMethods $availablePaymentMethods,
        PaymentMethodMap $paymentMethodMap,
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
        $this->availablePaymentMethods = $availablePaymentMethods;
        $this->paymentMethodMap = $paymentMethodMap;
        $this->messageManager = $messageManager;
    }

    /**
     * Retrieve available payment methods from Monei.
     *
     * @return array List of available payment method codes
     */
    protected function getAvailablePaymentMethods(): array
    {
        return $this->availablePaymentMethods->execute();
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
        return $this->paymentMethodMap->execute($magentoPaymentCode);
    }
}
