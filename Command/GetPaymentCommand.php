<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Command;

use Monei\Model\Payment;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for testing GetPayment REST service.
 */
class GetPaymentCommand extends Command
{
    /** @var GetPaymentInterface */
    private $service;

    /**
     * Constructor.
     *
     * @param GetPaymentInterface $service Service for retrieving payment details
     * @param string|null $name Command name
     */
    public function __construct(
        GetPaymentInterface $service,
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
        $this->setName('monei:moneiws:getpayment');
        $this->setDescription('Check GetPayment request functionality');

        parent::configure();
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     *
     * @return int Exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = 'c5fbae6a0ffd8035a6563be76d9acdfa0a892f50';
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

        $output->writeln('Order ID: ' . $result->getOrderId());

        // You can still output the full JSON if needed
        $output->writeln('Full JSON:');
        $output->writeln($result->__toString());

        return Command::SUCCESS;
    }
}
