<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Service;

use Monei\MoneiPayment\Model\Service\PaymentAmountMatcher;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Monei\MoneiPayment\Model\Service\PaymentAmountMatcher
 */
class PaymentAmountMatcherTest extends TestCase
{
    /**
     * @dataProvider paymentProvider
     */
    public function testMatches(int $amountInCents, string $currency, bool $expected): void
    {
        $matcher = new PaymentAmountMatcher();

        $this->assertSame($expected, $matcher->matches($amountInCents, $currency, 19.99, 'EUR'));
    }

    public static function paymentProvider(): array
    {
        return [
            'exact amount' => [1999, 'EUR', true],
            // In-site and redirect payments cast 19.99 * 100 to int, which gives 1998
            'truncated amount' => [1998, 'EUR', true],
            'smaller amount' => [1000, 'EUR', false],
            'other currency' => [1999, 'USD', false],
        ];
    }
}
