<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService as MagentoInvoiceService;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;

/**
 * Service for invoice operations
 */
class InvoiceService
{
    /**
     * @var MagentoInvoiceService
     */
    private MagentoInvoiceService $magentoInvoiceService;

    /**
     * @var InvoiceRepositoryInterface
     */
    private InvoiceRepositoryInterface $invoiceRepository;

    /**
     * @var TransactionFactory
     */
    private TransactionFactory $transactionFactory;

    /**
     * @var InvoiceSender
     */
    private InvoiceSender $invoiceSender;

    /**
     * @var LockManagerInterface
     */
    private LockManagerInterface $lockManager;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var ModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * @param MagentoInvoiceService $magentoInvoiceService
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param TransactionFactory $transactionFactory
     * @param InvoiceSender $invoiceSender
     * @param LockManagerInterface $lockManager
     * @param Logger $logger
     * @param ModuleConfigInterface $moduleConfig
     */
    public function __construct(
        MagentoInvoiceService $magentoInvoiceService,
        InvoiceRepositoryInterface $invoiceRepository,
        TransactionFactory $transactionFactory,
        InvoiceSender $invoiceSender,
        LockManagerInterface $lockManager,
        Logger $logger,
        MoneiPaymentModuleConfigInterface $moduleConfig
    ) {
        $this->magentoInvoiceService = $magentoInvoiceService;
        $this->invoiceRepository = $invoiceRepository;
        $this->transactionFactory = $transactionFactory;
        $this->invoiceSender = $invoiceSender;
        $this->lockManager = $lockManager;
        $this->logger = $logger;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * Process invoice creation with protection against duplicate operations
     *
     * @param Order $order
     * @param string|null $transactionId
     * @param bool $save
     * @return Invoice|null
     * @throws LocalizedException
     */
    public function processInvoice(
        Order $order,
        ?string $transactionId = null,
        bool $save = true
    ): ?Invoice {
        // Use a lock to prevent concurrent invoice operations
        return $this->lockManager->executeWithPaymentLock(
            $order->getIncrementId(),
            $order->getPayment()->getLastTransId() ?? $transactionId ?? 'order-payment',
            function () use ($order, $transactionId, $save) {
                try {
                    // If order already has an invoice, don't create a new one
                    if (!$order->canInvoice()) {
                        $this->logger->info(
                            'Order already has an invoice, skipping invoice creation',
                            ['order_id' => $order->getIncrementId()]
                        );

                        return null;
                    }

                    // Create a new invoice
                    $invoice = $this->magentoInvoiceService->prepareInvoice($order);

                    if (!$invoice->getTotalQty()) {
                        $this->logger->warning(
                            'Cannot create invoice with zero items',
                            ['order_id' => $order->getIncrementId()]
                        );

                        return null;
                    }

                    // Set transaction ID
                    if ($transactionId) {
                        $invoice->setTransactionId($transactionId);
                        $order->getPayment()->setLastTransId($transactionId);
                    }

                    // Register and capture the invoice
                    $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                    $invoice->register();

                    if ($save) {
                        $this->saveInvoice($invoice, $order);
                    }

                    return $invoice;
                } catch (\Exception $e) {
                    // Handle already captured payments gracefully
                    if ($this->isAlreadyCapturedError($e)) {
                        $this->logger->info(
                            'Payment was already captured, no need to create an invoice',
                            ['order_id' => $order->getIncrementId()]
                        );

                        return null;
                    }

                    // Log the error with full details
                    $this->logger->error(
                        'Error during invoice processing: ' . $e->getMessage(),
                        ['order_id' => $order->getIncrementId(), 'exception' => $e]
                    );

                    // Pass the error message through
                    throw new LocalizedException(
                        __('MONEI Payment Error: %1', $e->getMessage())
                    );
                }
            }
        );
    }

    /**
     * Save the invoice and related entities
     *
     * @param Invoice $invoice
     * @param Order $order
     * @return void
     */
    private function saveInvoice(Invoice $invoice, Order $order): void
    {
        $transaction = $this->transactionFactory->create();
        $transaction->addObject($invoice);
        $transaction->addObject($order);
        $transaction->save();

        // Send invoice email if needed
        try {
            // Check if invoice emails should be sent based on configuration
            if ($this->moduleConfig->shouldSendInvoiceEmail($order->getStoreId())) {
                $this->logger->debug('[Sending invoice email]');
                $this->invoiceSender->send($invoice);
            }
        } catch (\Exception $e) {
            $this->logger->warning(
                'Error sending invoice email: ' . $e->getMessage(),
                ['order_id' => $order->getIncrementId()]
            );
        }
    }

    /**
     * Check if exception indicates payment was already captured
     *
     * @param \Exception $e
     * @return bool
     */
    private function isAlreadyCapturedError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());

        return (
            strpos($message, 'already been captured') !== false ||
            strpos($message, 'has already been paid') !== false ||
            strpos($message, 'already a paid invoice') !== false ||
            strpos($message, 'duplicated operation') !== false ||
            strpos($message, 'transaction has already been captured') !== false
        );
    }

    /**
     * Create pending invoice for authorized payment
     *
     * @param Order $order
     * @param string $paymentId
     * @param bool $save
     * @return Invoice|null
     * @throws LocalizedException
     */
    public function createPendingInvoice(
        Order $order,
        string $paymentId,
        bool $save = true
    ): ?Invoice {
        // Use a lock to prevent concurrent invoice operations
        return $this->lockManager->executeWithPaymentLock(
            $order->getIncrementId(),
            $paymentId,
            function () use ($order, $paymentId, $save) {
                // If order already has an invoice, don't create a new one
                if (!$order->canInvoice()) {
                    $this->logger->info(
                        'Order already has an invoice, skipping pending invoice creation',
                        ['order_id' => $order->getIncrementId()]
                    );

                    return null;
                }

                // Create a new invoice
                $invoice = $this->magentoInvoiceService->prepareInvoice($order);

                // Link the payment ID to the invoice for later capture
                $invoice->setTransactionId($paymentId);
                $order->getPayment()->setLastTransId($paymentId);

                // Set to pending - don't capture yet
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
                $invoice->register();

                if ($save) {
                    $this->saveInvoice($invoice, $order);
                }

                $this->logger->info(
                    'Created pending invoice for authorized payment',
                    ['order_id' => $order->getIncrementId(), 'payment_id' => $paymentId]
                );

                return $invoice;
            }
        );
    }

    /**
     * Process partial invoice with protection against multiple partial captures
     *
     * @param Order $order
     * @param array $qtys
     * @param string|null $transactionId
     * @param bool $save
     * @return Invoice|null
     * @throws LocalizedException
     */
    public function processPartialInvoice(
        Order $order,
        array $qtys,
        ?string $transactionId = null,
        bool $save = true
    ): ?Invoice {
        return $this->lockManager->executeWithPaymentLock(
            $order->getIncrementId(),
            $order->getPayment()->getLastTransId() ?? $transactionId ?? 'order-payment',
            function () use ($order, $qtys, $transactionId, $save) {
                try {
                    // Check if there's already a partial invoice
                    if ($this->hasPartialCapture($order)) {
                        throw new LocalizedException(
                            __('MONEI only supports a single partial capture per payment. Please capture the full remaining amount.')
                        );
                    }

                    if (!$order->canInvoice()) {
                        $this->logger->info(
                            'Cannot create partial invoice - order already fully invoiced',
                            ['order_id' => $order->getIncrementId()]
                        );

                        return null;
                    }

                    // Create a partial invoice
                    $invoice = $this->magentoInvoiceService->prepareInvoice($order, $qtys);

                    if (!$invoice->getTotalQty()) {
                        throw new LocalizedException(
                            __('Cannot create an invoice without items.')
                        );
                    }

                    if ($transactionId) {
                        $invoice->setTransactionId($transactionId);
                        $order->getPayment()->setLastTransId($transactionId);
                    }

                    $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                    $invoice->register();

                    if ($save) {
                        $this->saveInvoice($invoice, $order);
                    }

                    return $invoice;
                } catch (\Exception $e) {
                    $this->logger->error(
                        'Error during partial invoice processing: ' . $e->getMessage(),
                        ['order_id' => $order->getIncrementId(), 'exception' => $e]
                    );

                    throw $e;
                }
            }
        );
    }

    /**
     * Check if order already has partial captures
     *
     * @param Order $order
     * @return bool
     */
    private function hasPartialCapture(Order $order): bool
    {
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->getState() == Invoice::STATE_PAID &&
                $invoice->getBaseGrandTotal() < $order->getBaseGrandTotal()
            ) {
                return true;
            }
        }

        return false;
    }
}
