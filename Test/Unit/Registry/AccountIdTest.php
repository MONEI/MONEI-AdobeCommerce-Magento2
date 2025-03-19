<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Registry;

use Monei\MoneiPayment\Registry\AccountId;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Monei\MoneiPayment\Registry\AccountId
 */
class AccountIdTest extends TestCase
{
    /**
     * @var AccountId
     */
    private $accountId;

    protected function setUp(): void
    {
        $this->accountId = new AccountId();
    }

    /**
     * Test that the account ID is null by default
     */
    public function testDefaultValueIsNull(): void
    {
        $this->assertNull($this->accountId->get());
    }

    /**
     * Test setting and getting the account ID
     */
    public function testSetAndGet(): void
    {
        $testAccountId = 'acc_123456789';
        $this->accountId->set($testAccountId);
        $this->assertEquals($testAccountId, $this->accountId->get());
    }

    /**
     * Test overwriting the account ID
     */
    public function testOverwriteValue(): void
    {
        $firstAccountId = 'acc_123456789';
        $secondAccountId = 'acc_987654321';

        $this->accountId->set($firstAccountId);
        $this->assertEquals($firstAccountId, $this->accountId->get());

        $this->accountId->set($secondAccountId);
        $this->assertEquals($secondAccountId, $this->accountId->get());
    }
}
