<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Command;

use Monei\Model\Payment;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for testing CreatePayment REST service.
 */
class CreatePaymentCommand extends Command
{
    /** @var CreatePaymentInterface */
    private $service;

    /**
     * Constructor for CreatePaymentCommand.
     *
     * @param CreatePaymentInterface $service Service for creating payments
     * @param string|null $name Command name
     */
    public function __construct(
        CreatePaymentInterface $service,
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
        $this->setName('monei:moneiws:createpayment');
        $this->setDescription('Check CreatePayment request functionality');

        parent::configure();
    }

    /**
     * Execute the command to test payment creation functionality.
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     *
     * @return int Exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = [
            'amount' => 110,
            'currency' => 'EUR',
            'orderId' => (string) rand(),
            'customer' => [
                'email' => 'testing-customer@monei.com',
                'name' => 'Testing customer',
                'phone' => '+34678678678',
            ],
            'billingDetails' => [
                'name' => 'Testing customer',
                'email' => 'testing-customer@monei.com',
                'phone' => '+34678678678',
                'company' => 'Testing company',
                'address' => [
                    'country' => 'ES',
                    'city' => 'Madrid',
                    'line1' => 'Fake Street',
                    'line2' => '1',
                    'zip' => '28001',
                    'state' => 'Madrid',
                ],
            ],
            'shippingDetails' => [
                'name' => 'Testing customer',
                'email' => 'testing-customer@monei.com',
                'phone' => '+34678678678',
                'company' => 'Testing company',
                'address' => [
                    'country' => 'ES',
                    'city' => 'Madrid',
                    'line1' => 'Fake Street',
                    'line2' => '1',
                    'zip' => '28001',
                    'state' => 'Madrid',
                ],
            ],
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

        $output->writeln('Order ID: ' . $result->getOrderId());

        // Full object as JSON
        $output->writeln('Full JSON:');
        $output->writeln($result->__toString());

        return Command::SUCCESS;
    }
}
