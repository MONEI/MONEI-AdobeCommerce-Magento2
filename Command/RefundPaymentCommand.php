<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Command;

use Monei\Model\Payment;
use Monei\MoneiPayment\Api\Service\RefundPaymentInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for testing RefundPayment REST service.
 */
class RefundPaymentCommand extends Command
{
    /** @var RefundPaymentInterface */
    private $service;

    /**
     * Constructor for RefundPaymentCommand.
     *
     * @param RefundPaymentInterface $service Service for refunding payments
     * @param string|null $name Command name
     */
    public function __construct(
        RefundPaymentInterface $service,
        ?string $name = null
    ) {
        $this->service = $service;
        parent::__construct($name);
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('monei:moneiws:refundpayment');
        $this->setDescription('Check RefundPayment request functionality');

        parent::configure();
    }

    /**
     * Execute the command to test refund payment functionality.
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     *
     * @return int Exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = [
            'paymentId' => '558a2b0ed268c2fcd85edc16fce46f16763f9d10',
            'refundReason' => 'requested_by_customer',
            'amount' => 10,
        ];

        /** @var Payment $result */
        $result = $this->service->execute($data);

        $output->writeln('Response:');
        $output->writeln('Payment ID: ' . $result->getId());
        $output->writeln('Amount: ' . $result->getAmount());
        $output->writeln('Currency: ' . $result->getCurrency());

        // Handle status display safely
        $status = $result->getStatus();
        $statusDisplay = 'null';
        if ($status !== null) {
            $statusDisplay = method_exists($status, 'getValue') ? $status->getValue() : 'UNKNOWN';
        }
        $output->writeln('Status: ' . $statusDisplay);

        $output->writeln('Refunded Amount: ' . $result->getRefundedAmount());

        // Full object as JSON
        $output->writeln('Full JSON:');
        $output->writeln($result->__toString());

        return Command::SUCCESS;
    }
}
