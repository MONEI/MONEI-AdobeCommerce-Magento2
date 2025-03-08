<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\Model\PaymentStatus;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;

/**
 * Service for capturing MONEI payments
 */
class CaptureService
{
    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $moneiApiClient;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var InvoiceRepositoryInterface
     */
    private InvoiceRepositoryInterface $invoiceRepository;

    /**
     * @var LockManagerInterface
     */
    private LockManagerInterface $lockManager;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * @param MoneiApiClient $moneiApiClient
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param LockManagerInterface $lockManager
     * @param Logger $logger
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     */
    public function __construct(
        MoneiApiClient $moneiApiClient,
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        LockManagerInterface $lockManager,
        Logger $logger,
        MoneiPaymentModuleConfigInterface $moduleConfig
    ) {
        $this->moneiApiClient = $moneiApiClient;
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->lockManager = $lockManager;
        $this->logger = $logger;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * Capture a payment for an invoice
     *
     * @param InvoiceInterface $invoice
     * @return bool
     * @throws LocalizedException
     */
    public function capturePayment(InvoiceInterface $invoice): bool
    {
        $order = $this->orderRepository->get($invoice->getOrderId());
        $payment = $order->getPayment();

        if (!$payment) {
            throw new LocalizedException(__('Payment not found for order %1', $order->getIncrementId()));
        }

        $paymentId = $payment->getAdditionalInformation('monei_payment_id');
        if (!$paymentId) {
            throw new LocalizedException(__('MONEI payment ID not found for order %1', $order->getIncrementId()));
        }

        // Acquire lock to prevent concurrent captures
        $lockAcquired = $this->lockManager->lockPayment($paymentId);
        if (!$lockAcquired) {
            throw new LocalizedException(__('Cannot capture payment. Another capture operation is in progress.'));
        }

        try {
            $amount = (int) round($invoice->getGrandTotal() * 100);
            $currency = $order->getOrderCurrencyCode();

            $this->logger->info(sprintf(
                'Capturing payment %s for order %s, amount: %s %s',
                $paymentId,
                $order->getIncrementId(),
                $amount / 100,
                $currency
            ));

            $captureResult = $this->moneiApiClient->capturePayment($paymentId, $amount, $currency);

            if (!isset($captureResult['id']) || $captureResult['status'] !== PaymentStatus::SUCCEEDED) {
                $errorMessage = $captureResult['message'] ?? 'Unknown error';
                $this->logger->error(sprintf(
                    'Failed to capture payment %s: %s',
                    $paymentId,
                    $errorMessage
                ), ['capture_result' => $captureResult]);

                throw new LocalizedException(__('Failed to capture payment: %1', $errorMessage));
            }

            // Update invoice with capture information
            $invoice->setTransactionId($captureResult['id']);
            $invoice->setRequestedCaptureCase(InvoiceInterface::CAPTURE_ONLINE);
            $this->invoiceRepository->save($invoice);

            // Update payment information
            $payment->setAdditionalInformation('monei_capture_id', $captureResult['id']);
            $payment->setAdditionalInformation('monei_is_captured', true);
            $this->orderRepository->save($order);

            $this->logger->info(sprintf(
                'Successfully captured payment %s for order %s',
                $paymentId,
                $order->getIncrementId()
            ));

            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error capturing payment %s: %s',
                $paymentId,
                $e->getMessage()
            ), ['exception' => $e]);

            throw new LocalizedException(__('Error capturing payment: %1', $e->getMessage()));
        } finally {
            // Release the lock
            $this->lockManager->unlockPayment($paymentId);
        }
    }

    /**
     * Check if a payment can be captured
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function canCapture(OrderInterface $order): bool
    {
        try {
            $payment = $order->getPayment();
            if (!$payment) {
                return false;
            }

            $paymentId = $payment->getAdditionalInformation('monei_payment_id');
            if (!$paymentId) {
                return false;
            }

            $isCaptured = (bool) $payment->getAdditionalInformation('monei_is_captured');
            if ($isCaptured) {
                return false;
            }

            // Check payment status in MONEI
            $paymentInfo = $this->moneiApiClient->getPayment($paymentId);

            return isset($paymentInfo['status']) && $paymentInfo['status'] === PaymentStatus::AUTHORIZED;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error checking if payment can be captured for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ), ['exception' => $e]);

            return false;
        }
    }

    /**
     * Void a payment
     *
     * @param OrderInterface $order
     * @return bool
     * @throws LocalizedException
     */
    public function voidPayment(OrderInterface $order): bool
    {
        $payment = $order->getPayment();

        if (!$payment) {
            throw new LocalizedException(__('Payment not found for order %1', $order->getIncrementId()));
        }

        $paymentId = $payment->getAdditionalInformation('monei_payment_id');
        if (!$paymentId) {
            throw new LocalizedException(__('MONEI payment ID not found for order %1', $order->getIncrementId()));
        }

        // Acquire lock to prevent concurrent operations
        $lockAcquired = $this->lockManager->lockPayment($paymentId);
        if (!$lockAcquired) {
            throw new LocalizedException(__('Cannot void payment. Another operation is in progress.'));
        }

        try {
            $this->logger->info(sprintf(
                'Voiding payment %s for order %s',
                $paymentId,
                $order->getIncrementId()
            ));

            $voidResult = $this->moneiApiClient->voidPayment($paymentId);

            if (!isset($voidResult['id']) || $voidResult['status'] !== PaymentStatus::CANCELED) {
                $errorMessage = $voidResult['message'] ?? 'Unknown error';
                $this->logger->error(sprintf(
                    'Failed to void payment %s: %s',
                    $paymentId,
                    $errorMessage
                ), ['void_result' => $voidResult]);

                throw new LocalizedException(__('Failed to void payment: %1', $errorMessage));
            }

            // Update payment information
            $payment->setAdditionalInformation('monei_is_voided', true);
            $this->orderRepository->save($order);

            $this->logger->info(sprintf(
                'Successfully voided payment %s for order %s',
                $paymentId,
                $order->getIncrementId()
            ));

            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error voiding payment %s: %s',
                $paymentId,
                $e->getMessage()
            ), ['exception' => $e]);

            throw new LocalizedException(__('Error voiding payment: %1', $e->getMessage()));
        } finally {
            // Release the lock
            $this->lockManager->unlockPayment($paymentId);
        }
    }

    /**
     * Check if a payment can be voided
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function canVoid(OrderInterface $order): bool
    {
        try {
            $payment = $order->getPayment();
            if (!$payment) {
                return false;
            }

            $paymentId = $payment->getAdditionalInformation('monei_payment_id');
            if (!$paymentId) {
                return false;
            }

            $isVoided = (bool) $payment->getAdditionalInformation('monei_is_voided');
            if ($isVoided) {
                return false;
            }

            // Check payment status in MONEI
            $paymentInfo = $this->moneiApiClient->getPayment($paymentId);

            return isset($paymentInfo['status']) && $paymentInfo['status'] === PaymentStatus::AUTHORIZED;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error checking if payment can be voided for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ), ['exception' => $e]);

            return false;
        }
    }
}
