<?php

namespace Monei\MoneiPayment\Command;

use Magento\Framework\App\State;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Monei\MoneiPayment\Setup\Patch\Data\UpdateOrderStatusLabels;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command to manually execute the UpdateOrderStatusLabels data patch
 */
class UpdateStatusLabels extends Command
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var UpdateOrderStatusLabels
     */
    private $updateOrderStatusLabels;

    /**
     * @var State
     */
    private $state;

    /**
     * Constructor
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param UpdateOrderStatusLabels $updateOrderStatusLabels
     * @param State $state
     * @param string|null $name
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        UpdateOrderStatusLabels $updateOrderStatusLabels,
        State $state,
        string $name = null
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->updateOrderStatusLabels = $updateOrderStatusLabels;
        $this->state = $state;
        parent::__construct($name);
    }

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('monei:update-status-labels')
            ->setDescription('Update MONEI order status labels to ensure proper formatting');

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
            $this->state->emulateAreaCode(
                \Magento\Framework\App\Area::AREA_ADMINHTML,
                function () use ($output) {
                    $output->writeln('<info>Updating MONEI order status labels...</info>');
                    $this->updateOrderStatusLabels->apply();
                    $output->writeln('<info>MONEI order status labels updated successfully!</info>');
                }
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
