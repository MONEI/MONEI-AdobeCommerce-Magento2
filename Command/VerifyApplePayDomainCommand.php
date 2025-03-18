<?php

/**
 * Copyright © Monei. All rights reserved.
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Command;

use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Service\VerifyApplePayDomainInterface;
use Monei\MoneiPayment\Service\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to register a domain with Apple Pay
 */
class VerifyApplePayDomainCommand extends Command
{
    /**
     * Argument for domain name
     */
    private const DOMAIN_ARGUMENT = 'domain';

    /**
     * @var State
     */
    private $appState;

    /**
     * @var VerifyApplePayDomainInterface
     */
    private $verifyApplePayDomain;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param State $appState
     * @param VerifyApplePayDomainInterface $verifyApplePayDomain
     * @param Logger $logger
     * @param string|null $name
     */
    public function __construct(
        State $appState,
        VerifyApplePayDomainInterface $verifyApplePayDomain,
        Logger $logger,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->appState = $appState;
        $this->verifyApplePayDomain = $verifyApplePayDomain;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setName('monei:verify-apple-pay-domain')
            ->setDescription('Register a domain with Apple Pay through MONEI')
            ->addArgument(
                self::DOMAIN_ARGUMENT,
                InputArgument::REQUIRED,
                'Domain to verify with Apple Pay'
            )
            ->addOption(
                'store',
                's',
                InputArgument::OPTIONAL,
                'Store ID to use for API configuration',
                null
            );

        parent::configure();
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Exception $e) {
            // Area code already set
        }

        $domain = $input->getArgument(self::DOMAIN_ARGUMENT);
        $storeId = $input->getOption('store') ? (int) $input->getOption('store') : null;

        $output->writeln('<info>Attempting to register domain ' . $domain . ' with Apple Pay...</info>');
        if ($storeId !== null) {
            $output->writeln('<info>Using Store ID: ' . $storeId . '</info>');
        }

        try {
            $result = $this->verifyApplePayDomain->execute($domain, $storeId);
            $output->writeln('<info>Domain successfully registered with Apple Pay!</info>');
            $output->writeln('<info>Response: ' . json_encode($result) . '</info>');

            return Command::SUCCESS;
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $this->logger->error('Error when registering Apple Pay domain: ' . $e->getMessage());

            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln('<error>Unexpected error: ' . $e->getMessage() . '</error>');
            $this->logger->error('Unexpected error when registering Apple Pay domain: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
