<?php

namespace Monei\MoneiPayment\Test\Unit\Command;

use Magento\Framework\App\State;
use Monei\MoneiPayment\Api\Service\VerifyApplePayDomainInterface;
use Monei\MoneiPayment\Command\VerifyApplePayDomainCommand;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Test case for VerifyApplePayDomainCommand
 */
class VerifyApplePayDomainCommandTest extends TestCase
{
    /**
     * @var VerifyApplePayDomainCommand
     */
    private $command;

    /**
     * @var State|\PHPUnit\Framework\MockObject\MockObject
     */
    private $appStateMock;

    /**
     * @var VerifyApplePayDomainInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $verifyApplePayDomainMock;

    /**
     * @var Logger|\PHPUnit\Framework\MockObject\MockObject
     */
    private $loggerMock;

    protected function setUp(): void
    {
        $this->appStateMock = $this->createMock(State::class);
        $this->verifyApplePayDomainMock = $this->createMock(VerifyApplePayDomainInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);

        $this->command = new VerifyApplePayDomainCommand(
            $this->appStateMock,
            $this->verifyApplePayDomainMock,
            $this->loggerMock
        );
    }

    /**
     * Test configuration of the command
     */
    public function testCommandConfiguration()
    {
        // Verify command name and description
        $this->assertEquals('monei:verify-apple-pay-domain', $this->command->getName());
        $this->assertEquals('Register a domain with Apple Pay through MONEI', $this->command->getDescription());

        // Verify command arguments
        $arguments = $this->command->getDefinition()->getArguments();
        $this->assertArrayHasKey('domain', $arguments);
        $this->assertTrue($arguments['domain']->isRequired());
        $this->assertEquals('Domain to verify with Apple Pay', $arguments['domain']->getDescription());
    }
}
