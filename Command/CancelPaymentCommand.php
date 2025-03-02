<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Command;

use Monei\MoneiPayment\Api\Service\CancelPaymentInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for testing CancelPayment REST service.
 */
class CancelPaymentCommand extends Command
{
    /** @var CancelPaymentInterface */
    private $service;

    public function __construct(
        CancelPaymentInterface $service,
        ?string $name = null
    ) {
        $this->service = $service;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('monei:moneiws:cancelpayment');
        $this->setDescription('Check CancelPayment request functionality');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $data = [
            'paymentId' => '8a437c2d2423283bc940c786196098cae3653dc4',
            'cancellationReason' => 'requested_by_customer',
        ];
        $result = $this->service->execute($data);
        $output->writeln('Response:');
        $output->writeln(json_encode($result, JSON_PRETTY_PRINT));
    }
}
