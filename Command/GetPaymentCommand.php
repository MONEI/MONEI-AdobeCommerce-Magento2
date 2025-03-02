<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Command;

use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for testing GetPayment REST service.
 */
class GetPaymentCommand extends Command
{
    /**
     * @var GetPaymentInterface
     */
    private $service;

    public function __construct(
        GetPaymentInterface $service,
        ?string $name = null
    ) {
        $this->service = $service;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('monei:moneiws:getpayment');
        $this->setDescription('Check GetPayment request functionality');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $data = 'c5fbae6a0ffd8035a6563be76d9acdfa0a892f50';
        $result = $this->service->execute($data);
        $output->writeln('Response:');
        $output->writeln(json_encode($result, JSON_PRETTY_PRINT));
    }
}
