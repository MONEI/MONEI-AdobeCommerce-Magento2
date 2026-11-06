<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Monei\MoneiPayment\Api\Service\Customer\CustomerServiceInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Logger;

/**
 * Deletes the MONEI payment method after a saved MONEI card is deleted from the vault.
 */
class DeleteMoneiPaymentMethod
{
    /**
     * @var CustomerServiceInterface
     */
    private CustomerServiceInterface $customerService;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param CustomerServiceInterface $customerService
     * @param StoreManagerInterface $storeManager
     * @param Logger $logger
     */
    public function __construct(
        CustomerServiceInterface $customerService,
        StoreManagerInterface $storeManager,
        Logger $logger
    ) {
        $this->customerService = $customerService;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Delete the MONEI payment method of a deleted MONEI vault token.
     *
     * @param PaymentTokenRepositoryInterface $subject
     * @param bool $result
     * @param PaymentTokenInterface $paymentToken
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterDelete(
        PaymentTokenRepositoryInterface $subject,
        bool $result,
        PaymentTokenInterface $paymentToken
    ): bool {
        if (!$result
            || $paymentToken->getPaymentMethodCode() !== Monei::CARD_CODE
            || !$paymentToken->getCustomerId()
        ) {
            return $result;
        }

        // A throw here reaches the buyer's card delete.
        try {
            $this->customerService->deletePaymentMethod(
                (int) $paymentToken->getCustomerId(),
                (int) $this->storeManager->getStore()->getId(),
                (string) $paymentToken->getGatewayToken()
            );
        } catch (\Throwable $e) {
            $this->logger->error('[MONEI Customers] Payment method delete failed', [
                'customer_id' => (int) $paymentToken->getCustomerId(),
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}
