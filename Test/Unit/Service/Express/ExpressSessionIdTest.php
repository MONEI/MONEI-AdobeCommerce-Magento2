<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Express;

use Magento\Checkout\Model\Session;
use Monei\MoneiPayment\Service\Express\ExpressSessionId;
use PHPUnit\Framework\TestCase;

/**
 * Test for ExpressSessionId.
 */
class ExpressSessionIdTest extends TestCase
{
    /**
     * The page reads the id when the buttons mount and the server reads it again at
     * place-order. A new id on the second read would make MONEI refuse the token.
     *
     * @return void
     */
    public function testKeepsOneIdForTheSession(): void
    {
        $stored = [];
        $session = $this
            ->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->addMethods(['setData'])
            ->getMock();
        $session->method('getData')->willReturnCallback(function ($key) use (&$stored) {
            return $stored[$key] ?? null;
        });
        $session->method('setData')->willReturnCallback(function ($key, $value) use (&$stored, $session) {
            $stored[$key] = $value;

            return $session;
        });

        $service = new ExpressSessionId($session);
        $first = $service->get();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first);
        $this->assertSame($first, $service->get());
    }
}
