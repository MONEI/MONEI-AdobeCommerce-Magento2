<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Command;

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

    public function __construct(
        CreatePaymentInterface $service,
        ?string $name = null
    ) {
        $this->service = $service;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('monei:moneiws:createpayment');
        $this->setDescription('Check CreatePayment request functionality');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
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
        $result = $this->service->execute($data);
        $output->writeln('Response:');
        $output->writeln(json_encode($result, JSON_PRETTY_PRINT));
    }
}
