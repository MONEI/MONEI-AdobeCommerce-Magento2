<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Connect;

/**
 * CallbackVerifier: which payment a MONEI callback may process.
 *
 * Every test names the bug it catches.
 */
class CallbackVerifierTest extends VerifierTestCase
{
    /**
     * The fields the payment processor reads, in a fixed order; a missing one shows as 'missing'.
     */
    private static function six(array $payment): array
    {
        $six = [];
        foreach (['id', 'orderId', 'amount', 'status', 'statusCode', 'statusMessage'] as $key) {
            $six[$key] = array_key_exists($key, $payment) ? $payment[$key] : 'missing';
        }

        return $six;
    }

    /**
     * Bug: a connected store fetches on every callback, or drops a body that v2 proves.
     */
    public function testAValidV2ReturnsTheBodyPaymentWithoutAFetch(): void
    {
        $body = self::body(self::paymentFields('AUTHORIZED'));

        $decision = $this->verifier()->verify($body, self::sign($body, 'v2', self::SECRET));

        $this->assertSame([200, 'AUTHORIZED'], [$decision['status'], $decision['payment']['status']]);
        $this->assertSame([], $this->fetches);
    }

    /**
     * Bug: a callback that v2 does not prove changes the order from its body. The v1-only row is a callback of an
     * order made before the connect: it settles through the fetched payment, with the store's own key.
     *
     * @dataProvider unprovenSignatures
     */
    public function testAConnectedStoreFetchesThePaymentAndIgnoresTheBody(string $elementKey, ?string $element): void
    {
        $body = self::body(self::paymentFields('SUCCEEDED', ['statusMessage' => 'from the body']));
        $signature = $element === null ? null : self::sign($body, $element, $elementKey);

        $decision = $this->verifier()->verify($body, $signature);

        $this->assertSame([[self::KEY, self::ID]], $this->fetches);
        $this->assertSame(200, $decision['status']);
        $this->assertSame('Transaction approved', $decision['payment']['statusMessage']);
    }

    public function unprovenSignatures(): array
    {
        return [
            'v1 only, the old pk_ key' => ['pk_test_old', 'v1'],
            'v2 of a replaced secret' => ['mksec_test_replaced', 'v2'],
            'no header' => ['', null],
        ];
    }

    /**
     * Bug: the two paths hand the payment processor different shapes (snake_case model data, a missing key), so a
     * fetched payment does not settle the order.
     */
    public function testBothPathsReturnTheSameShape(): void
    {
        $expected = [
            'id' => self::ID,
            'orderId' => '000000101',
            'amount' => 1000,
            'status' => 'SUCCEEDED',
            'statusCode' => 'E000',
            'statusMessage' => 'Transaction approved',
        ];
        $body = self::body($this->remote);

        $signed = $this->verifier()->verify($body, self::sign($body, 'v2', self::SECRET));
        $fetched = $this->verifier()->verify($body, null);

        $this->assertSame($expected, self::six($signed['payment']));
        $this->assertSame($expected, self::six($fetched['payment']));
    }

    /**
     * Bug: a forged body or a fetched payment that does not belong to the order changes it. Each answers 200, so
     * MONEI does not retry.
     *
     * @dataProvider paymentsThatChangeNothing
     */
    public function testAFetchedPaymentThatDoesNotFitChangesNothing(array $remote, bool $paid): void
    {
        $this->remote = $remote;
        $this->orders['000000101']['paid'] = $paid;

        $decision = $this->verifier()->verify(self::body(self::paymentFields('SUCCEEDED')), null);

        $this->assertSame([200, null], [$decision['status'], $decision['payment']]);
        $this->assertCount(1, $this->fetches);
    }

    public function paymentsThatChangeNothing(): array
    {
        return [
            'forged SUCCEEDED, MONEI says PENDING' => [self::paymentFields('PENDING'), false],
            'FAILED for an order already paid' => [self::paymentFields('FAILED'), true],
            'another orderId' => [self::paymentFields('SUCCEEDED', ['orderId' => '000000102']), false],
            'the same orderId, another amount' => [self::paymentFields('SUCCEEDED', ['amount' => 1001]), false],
            'another payment id' => [self::paymentFields('SUCCEEDED', ['id' => str_repeat('b', 32)]), false],
        ];
    }

    /**
     * Bug: a failed fetch answers 200 or settles from the body, so MONEI never retries and the order stays open.
     */
    public function testAFailedFetchAnswers503(): void
    {
        $this->fetchFails = true;

        $decision = $this->verifier()->verify(self::body($this->remote), null);

        $this->assertSame([503, null], [$decision['status'], $decision['payment']]);
    }

    /**
     * Bug: a body that cannot be a callback of this store makes the store call MONEI (a fetch amplifier).
     *
     * @dataProvider bodiesRefusedBeforeAFetch
     */
    public function testAFailedPreCheckAnswers400WithNoFetch(array $fields, ?string $signature, string $storedId): void
    {
        $this->orders['000000101']['paymentId'] = $storedId;

        $decision = $this->verifier()->verify(self::body($fields), $signature);

        $this->assertSame(400, $decision['status']);
        $this->assertSame([], $this->fetches);
    }

    public function bodiesRefusedBeforeAFetch(): array
    {
        return [
            'id differs from the order monei_payment_id' => [self::paymentFields('SUCCEEDED'), null, str_repeat('c', 32)],
            'no order for the orderId' => [self::paymentFields('SUCCEEDED', ['orderId' => '999']), null, ''],
            'malformed id' => [self::paymentFields('SUCCEEDED', ['id' => '../payments']), null, ''],
            'a header that does not parse' => [self::paymentFields('SUCCEEDED'), 'v1=abc', ''],
        ];
    }

    /**
     * Bug: one order makes unbounded fetches, or the quota never frees up.
     */
    public function testTheOrderQuotaRefusesThe6thAttemptInAMinuteAndFreesAfter61S(): void
    {
        $body = self::body($this->remote);
        $statuses = [];
        for ($i = 0; $i < 6; $i++) {
            $statuses[] = $this->verifier()->verify($body, null)['status'];
        }
        $this->now += 61;
        $statuses[] = $this->verifier()->verify($body, null)['status'];

        $this->assertSame([200, 200, 200, 200, 200, 400, 200], $statuses);
        $this->assertCount(6, $this->fetches);
    }

    /**
     * Bug: many orders together make unbounded fetches.
     */
    public function testTheStoreQuotaRefusesThe61stAttemptInAMinute(): void
    {
        for ($order = 1; $order <= 13; $order++) {
            $this->orders["o$order"] = ['paymentId' => '', 'total' => 10.0, 'paid' => false];
        }
        $last = 0;
        for ($attempt = 0; $attempt < 61; $attempt++) {
            $order = 'o' . (intdiv($attempt, 5) + 1);
            $body = self::body(self::paymentFields('SUCCEEDED', ['orderId' => $order]));
            $last = $this->verifier()->verify($body, null)['status'];
        }

        $this->assertSame(400, $last);
        $this->assertCount(60, $this->fetches);
    }

    /**
     * Bug: a manual pk_ store loses its v1 check (a forged body settles an order), or starts to fetch.
     */
    public function testAManualPkStoreVerifiesV1AsToday(): void
    {
        $this->config = [self::STORE . 'mode' => '1', self::STORE . 'test_api_key' => 'pk_test_manual'];
        $body = self::body($this->remote);
        $verifier = $this->verifier();

        $valid = $verifier->verify($body, self::sign($body, 'v1', 'pk_test_manual'));
        $forged = $verifier->verify($body, self::sign($body, 'v1', 'pk_test_other'));
        $absent = $verifier->verify($body, null);

        $this->assertSame([200, 401, 401], [$valid['status'], $forged['status'], $absent['status']]);
        $this->assertSame('SUCCEEDED', $valid['payment']['status']);
        $this->assertSame([], $this->fetches);
    }

    /**
     * Bug: a mode without a key accepts a v1 made with an empty key, which anyone can compute.
     */
    public function testAModeWithoutAKeyRefusesAV1OfTheEmptyKey(): void
    {
        $this->config = [];
        $body = self::body($this->remote);

        $decision = $this->verifier()->verify($body, self::sign($body, 'v1', ''));

        $this->assertSame(401, $decision['status']);
    }

    /**
     * Bug: a callback of the other mode is checked with the selected mode's key, so Live callbacks of a store in
     * test mode fail. Each mode verifies with its own key.
     */
    public function testLivemodePicksTheKeyOfItsMode(): void
    {
        $this->config = [
            self::STORE . 'mode' => '1',
            self::STORE . 'test_api_key' => 'pk_test_manual',
            self::STORE . 'production_api_key' => 'pk_live_manual',
        ];
        $body = self::body(self::paymentFields('SUCCEEDED', ['livemode' => true]));

        $live = $this->verifier()->verify($body, self::sign($body, 'v1', 'pk_live_manual'));
        $test = $this->verifier()->verify($body, self::sign($body, 'v1', 'pk_test_manual'));

        $this->assertSame([200, 401], [$live['status'], $test['status']]);
    }
}
