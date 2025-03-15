<?php

namespace Monei\MoneiPayment\Test\Unit\Command;

use Magento\Framework\App\State;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Monei\MoneiPayment\Command\UpdateStatusLabels;
use Monei\MoneiPayment\Setup\Patch\Data\UpdateOrderStatusLabels;
use PHPUnit\Framework\TestCase;

/**
 * Test case for UpdateStatusLabels command
 */
class UpdateStatusLabelsTest extends TestCase
{
    /**
     * @var UpdateStatusLabels
     */
    private $command;

    /**
     * @var ModuleDataSetupInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $moduleDataSetupMock;

    /**
     * @var UpdateOrderStatusLabels|\PHPUnit\Framework\MockObject\MockObject
     */
    private $updateOrderStatusLabelsMock;

    /**
     * @var State|\PHPUnit\Framework\MockObject\MockObject
     */
    private $stateMock;

    protected function setUp(): void
    {
        $this->moduleDataSetupMock = $this->createMock(ModuleDataSetupInterface::class);
        $this->updateOrderStatusLabelsMock = $this->createMock(UpdateOrderStatusLabels::class);
        $this->stateMock = $this->createMock(State::class);

        $this->command = new UpdateStatusLabels(
            $this->moduleDataSetupMock,
            $this->updateOrderStatusLabelsMock,
            $this->stateMock
        );
    }

    /**
     * Test configuration of the command
     */
    public function testCommandConfiguration()
    {
        // Verify command name and description
        $this->assertEquals('monei:update-status-labels', $this->command->getName());
        $this->assertEquals('Update MONEI order status labels to ensure proper formatting', $this->command->getDescription());
    }
}
