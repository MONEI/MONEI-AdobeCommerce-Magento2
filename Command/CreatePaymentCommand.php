<?php

/**
 * @author Monei Team
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
    /**
     * @var CreatePaymentInterface
     */
    private $service;

    /**
     * @param CreatePaymentInterface $service
     * @param string|null            $name
     */
    public function __construct(
        CreatePaymentInterface $service,
        string $name = null
    ) {
        $this->service = $service;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('monei:moneiws:createpayment');
        $this->setDescription('Check CreatePayment request functionality');

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $data = [
            "amount"   => 110,
            "currency" => "EUR",
            "orderId"  => (string) rand(),
        ];
        $result = $this->service->execute($data);
        $output->writeln('Response:');
        // @codingStandardsIgnoreLine
        \print_r($result);
    }
}
