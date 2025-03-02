<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Command;

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

    public function __construct(
        RefundPaymentInterface $service,
        ?string $name = null
    ) {
        $this->service = $service;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('monei:moneiws:refundpayment');
        $this->setDescription('Check RefundPayment request functionality');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $data = [
            'paymentId' => '558a2b0ed268c2fcd85edc16fce46f16763f9d10',
            'refundReason' => 'requested_by_customer',
            'amount' => 10,
        ];
        $result = $this->service->execute($data);
        $output->writeln('Response:');
        $output->writeln(json_encode($result, JSON_PRETTY_PRINT));
    }
}
