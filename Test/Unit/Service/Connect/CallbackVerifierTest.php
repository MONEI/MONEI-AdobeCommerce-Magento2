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
     * MONEI does not retry. A status the SDK does not know throws inside payments->get(): a 503 there makes MONEI
     * retry an answer that never changes. The mode is the one set at the order's store.
     *
     * @dataProvider paymentsThatChangeNothing
     */
    public function testAFetchedPaymentThatDoesNotFitChangesNothing(array $remote, array $order = []): void
    {
        $this->remote = $remote;
        $this->orders['000000101'] = $order + $this->orders['000000101'];
        $this->config['store/2/payment/monei/mode'] = '2';
        $this->config['store/2/payment/monei/test_api_key'] = self::KEY;

        $decision = $this->verifier()->verify(self::body(self::paymentFields('SUCCEEDED')), null);

        $this->assertSame([200, null], [$decision['status'], $decision['payment']]);
        $this->assertCount(1, $this->fetches);
    }

    public function paymentsThatChangeNothing(): array
    {
        return [
            'forged SUCCEEDED, MONEI says PENDING' => [self::paymentFields('PENDING')],
            'another orderId' => [self::paymentFields('SUCCEEDED', ['orderId' => '000000102'])],
            'another payment id' => [self::paymentFields('SUCCEEDED', ['id' => str_repeat('b', 32)])],
            'a status the SDK does not know' => [self::paymentFields('SETTLED_LATER')],
            'a Live payment, the store in test mode' => [self::paymentFields('SUCCEEDED', ['livemode' => true])],
            'a test payment, the order store in live mode' => [self::paymentFields('SUCCEEDED'), ['storeId' => 2]],
        ];
    }

    /**
     * Bug: an unsigned body's store metadata picks another store view's order, and an older fetched payment (no
     * store metadata) settles it; or a store metadata that is not a number is read as one.
     *
     * @dataProvider hintedBodies
     */
    public function testAnUnsignedStoreHintNeedsTheFetchedPaymentToAgree($hint, int $status, bool $processed): void
    {
        $this->orders = [
            's1' => ['incrementId' => '000000101', 'storeId' => 1, 'paymentId' => '', 'entityId' => 7],
            's2' => ['incrementId' => '000000101', 'storeId' => 2, 'paymentId' => '', 'entityId' => 8],
        ];
        $this->config['websites/1/payment/monei/test_api_key'] = self::KEY;

        $decision = $this->verifier()->verify(
            self::body(self::paymentFields('SUCCEEDED', ['metadata' => ['magento_store_id' => $hint]])),
            null
        );

        $this->assertSame([$status, $processed], [$decision['status'], $decision['payment'] !== null]);
    }

    public function hintedBodies(): array
    {
        return [
            'a hint the fetched payment does not confirm' => ['2', 200, false],
            'not a number' => ['2abc', 400, false],
        ];
    }

    /**
     * Bug: a payment of another store view (store views share a scoped key and can share an increment ID and an
     * amount) settles this store view's order; or a payment made before the store metadata existed is refused.
     *
     * @dataProvider paymentStores
     */
    public function testAFetchedPaymentOfAnotherStoreViewChangesNothing(?string $metadataStore, bool $processed): void
    {
        $this->remote = self::paymentFields('SUCCEEDED')
            + ($metadataStore === null ? [] : ['metadata' => ['magento_store_id' => $metadataStore]]);

        $decision = $this->verifier()->verify(self::body(self::paymentFields('SUCCEEDED')), null);

        $this->assertSame([200, $processed], [$decision['status'], $decision['payment'] !== null]);
    }

    public function paymentStores(): array
    {
        return [
            'the order\'s store view' => ['1', true],
            'another store view' => ['2', false],
            'no store metadata (an older payment)' => [null, true],
        ];
    }

    /**
     * Bug: the fetch path drops a payment whose amount differs from the order, so the payment processor never holds
     * the order for review, as it does for a signed payment.
     */
    public function testAFetchedPaymentOfAnotherAmountReachesTheProcessor(): void
    {
        $this->remote = self::paymentFields('SUCCEEDED', ['amount' => 1001]);

        $decision = $this->verifier()->verify(self::body(self::paymentFields('SUCCEEDED')), null);

        $this->assertSame([200, 1001], [$decision['status'], $decision['payment']['amount']]);
    }

    /**
     * Bug: an unsigned request changes an order of another payment method, or makes the store call MONEI for an
     * order it does not have. An error answer makes MONEI retry a callback that can never settle.
     *
     * @dataProvider ordersAFetchMustNotChange
     */
    public function testAnOrderAFetchMustNotChangeAnswers200WithNoFetch(string $orderId, array $order): void
    {
        $this->orders['000000101'] = $order + $this->orders['000000101'];

        $decision = $this->verifier()->verify(self::body(self::paymentFields('SUCCEEDED', ['orderId' => $orderId])), null);

        $this->assertSame([200, null], [$decision['status'], $decision['payment']]);
        $this->assertSame([], $this->fetches);
    }

    public function ordersAFetchMustNotChange(): array
    {
        return [
            'no order for the orderId' => ['999', []],
            'another payment method' => ['000000101', ['method' => 'checkmo']],
        ];
    }

    /**
     * Bug: an unsigned replay of a settled payment changes the order again (a SUCCEEDED reopens the order the
     * merchant canceled); or a real later callback (a capture or void after AUTHORIZED, a payment after a canceled
     * order) gets a silent 200, so MONEI never retries it; or a FAILED cancels a paid order; or a SUCCEEDED the
     * processor saved before its invoice failed blocks the retry. With no stored status, the order state the
     * processor set stands for it.
     *
     * @dataProvider transitions
     */
    public function testOnlyAForwardMoveFromTheStoredStatusIsProcessed(
        ?string $stored,
        string $state,
        bool $paid,
        string $fetched,
        bool $processed
    ): void {
        $this->orders['000000101'] = ['paymentId' => self::ID, 'state' => $state, 'paid' => $paid]
            + ($stored === null ? [] : ['storedStatus' => $stored]);
        $this->remote = self::paymentFields($fetched);

        $decision = $this->verifier()->verify(self::body(self::paymentFields($fetched)), null);

        $this->assertSame([200, $processed], [$decision['status'], $decision['payment'] !== null]);
        $this->assertCount(1, $this->fetches);
    }

    public function transitions(): array
    {
        return [
            'SUCCEEDED, canceled order, SUCCEEDED again' => ['SUCCEEDED', 'canceled', true, 'SUCCEEDED', false],
            'none, canceled order, SUCCEEDED' => [null, 'canceled', false, 'SUCCEEDED', true],
            'PENDING, canceled order, SUCCEEDED' => ['PENDING', 'canceled', false, 'SUCCEEDED', true],
            'AUTHORIZED, SUCCEEDED (capture)' => ['AUTHORIZED', 'processing', false, 'SUCCEEDED', true],
            'AUTHORIZED, CANCELED (void)' => ['AUTHORIZED', 'processing', false, 'CANCELED', true],
            'AUTHORIZED again' => ['AUTHORIZED', 'processing', false, 'AUTHORIZED', false],
            'SUCCEEDED, AUTHORIZED' => ['SUCCEEDED', 'processing', true, 'AUTHORIZED', false],
            'SUCCEEDED, PAID_OUT' => ['SUCCEEDED', 'processing', true, 'PAID_OUT', true],
            'SUCCEEDED saved before its invoice failed, SUCCEEDED again' => ['SUCCEEDED', 'new', false, 'SUCCEEDED', true],
            'the same, the order canceled since, SUCCEEDED again' => ['SUCCEEDED', 'canceled', false, 'SUCCEEDED', false],
            'REFUNDED, SUCCEEDED' => ['REFUNDED', 'closed', true, 'SUCCEEDED', false],
            'FAILED, AUTHORIZED (retry)' => ['FAILED', 'canceled', false, 'AUTHORIZED', true],
            'EXPIRED, SUCCEEDED' => ['EXPIRED', 'canceled', false, 'SUCCEEDED', true],
            'CANCELED, SUCCEEDED' => ['CANCELED', 'canceled', false, 'SUCCEEDED', true],
            'FAILED again' => ['FAILED', 'canceled', false, 'FAILED', false],
            'CANCELED again' => ['CANCELED', 'canceled', false, 'CANCELED', false],
            'none, processing without invoice (AUTHORIZED), AUTHORIZED' => [null, 'processing', false, 'AUTHORIZED', false],
            'none, processing without invoice (AUTHORIZED), SUCCEEDED' => [null, 'processing', false, 'SUCCEEDED', true],
            'none, complete invoiced order (SUCCEEDED), SUCCEEDED again' => [null, 'complete', true, 'SUCCEEDED', false],
            'none, new order, AUTHORIZED' => [null, 'new', false, 'AUTHORIZED', true],
            'PENDING, invoiced order, FAILED' => ['PENDING', 'holded', true, 'FAILED', false],
        ];
    }

    /**
     * Bug: the payment of a retry is refused for good, so the paid order stays open; or a second payment is fetched
     * for an order that a stored payment already paid or holds. An unknown stored status lets the fetch decide.
     *
     * @dataProvider storedPaymentStatuses
     */
    public function testABodyIdOtherThanTheStoredOneIsFetchedUnlessTheStoredPaymentSettled(
        string $storedStatus,
        string $storedStatusOf,
        int $status,
        int $fetches
    ): void {
        $this->orders['000000101'] += ['storedStatus' => $storedStatus, 'storedStatusOf' => $storedStatusOf];
        $this->orders['000000101']['paymentId'] = str_repeat('a', 32);

        $decision = $this->verifier()->verify(self::body($this->remote), null);

        $this->assertSame([$status, $fetches], [$decision['status'], count($this->fetches)]);
    }

    public function storedPaymentStatuses(): array
    {
        return [
            'PENDING' => ['PENDING', str_repeat('a', 32), 200, 1],
            'AUTHORIZED' => ['AUTHORIZED', str_repeat('a', 32), 400, 0],
            'SUCCEEDED' => ['SUCCEEDED', str_repeat('a', 32), 400, 0],
            'PAID_OUT' => ['PAID_OUT', str_repeat('a', 32), 400, 0],
            'SUCCEEDED, but of an older payment' => ['SUCCEEDED', str_repeat('c', 32), 200, 1],
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
    public function testAMalformedBodyAnswers400WithNoFetch(array $fields, ?string $signature): void
    {
        $decision = $this->verifier()->verify(self::body($fields), $signature);

        $this->assertSame(400, $decision['status']);
        $this->assertSame([], $this->fetches);
    }

    public function bodiesRefusedBeforeAFetch(): array
    {
        return [
            'malformed id' => [self::paymentFields('SUCCEEDED', ['id' => '../payments']), null],
            'a header that does not parse' => [self::paymentFields('SUCCEEDED'), 'v1=abc'],
        ];
    }

    /**
     * Bug: one order makes unbounded fetches, or the quota never frees up, or a refusal answers 4xx, which MONEI does
     * not retry, so the order stays open.
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

        $this->assertSame([200, 200, 200, 200, 200, 503, 200], $statuses);
        $this->assertCount(6, $this->fetches);
    }

    /**
     * Bug: the order quota counts by increment ID, which repeats across stores: callbacks for one store's order
     * block another store's order with the same number.
     */
    public function testTheOrderQuotaCountsTheOrderNotItsIncrementId(): void
    {
        $this->orders = [
            'website 1' => ['incrementId' => '000000101', 'storeId' => 1, 'paymentId' => '', 'entityId' => 7],
            'website 2' => ['incrementId' => '000000101', 'storeId' => 3, 'paymentId' => '', 'entityId' => 8],
        ];
        $this->config['store/3/payment/monei/test_api_key'] = self::KEY;
        $body = self::body($this->remote);
        for ($i = 0; $i < 5; $i++) {
            $this->verifier()->verify($body, null);
        }
        $this->requestStore = 3;

        $this->assertSame(200, $this->verifier()->verify($body, null)['status']);
    }

    /**
     * Bug: many orders together make unbounded fetches.
     */
    public function testTheStoreQuotaRefusesThe61stAttemptInAMinute(): void
    {
        for ($order = 1; $order <= 13; $order++) {
            $this->orders["o$order"] = ['paymentId' => '', 'entityId' => 100 + $order];
        }
        $last = 0;
        for ($attempt = 0; $attempt < 61; $attempt++) {
            $order = 'o' . (intdiv($attempt, 5) + 1);
            $body = self::body(self::paymentFields('SUCCEEDED', ['orderId' => $order]));
            $last = $this->verifier()->verify($body, null)['status'];
        }

        $this->assertSame(503, $last);
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
     * Bug: a signed callback of the other mode changes the order: a test payment (the merchant's own test key, the
     * live order's increment id and amount) settles a live order. Each mode still verifies with its own key or
     * secret, so the refusal answers 200 and MONEI does not retry.
     *
     * @dataProvider signaturesOfTheOtherMode
     */
    public function testASignedCallbackOfTheOtherModeChangesNothing(string $element, string $key): void
    {
        $this->config = [
            self::STORE . 'mode' => '2',
            self::STORE . 'production_api_key' => 'mk_live_key',
            self::STORE . 'production_signing_secret' => 'mksec_live_secret',
            self::STORE . 'test_api_key' => strpos($key, 'mksec_') === 0 ? self::KEY : $key,
            self::STORE . 'test_signing_secret' => self::SECRET,
        ];
        $body = self::body(self::paymentFields('SUCCEEDED', ['livemode' => false]));

        $decision = $this->verifier()->verify($body, self::sign($body, $element, $key));

        $this->assertSame([200, null, 'livemode'], [$decision['status'], $decision['payment'], $decision['reason']]);
        $this->assertSame([], $this->fetches);
    }

    public function signaturesOfTheOtherMode(): array
    {
        return [
            'v2 with the test secret' => ['v2', self::SECRET],
            'v1 with a manual test key' => ['v1', 'pk_test_manual'],
        ];
    }

    /**
     * Bug: the callback is matched to an order of another store with the same increment id (increment ids repeat
     * across stores), or checked with the request store's credentials, so a payment of website A settles an order
     * of website B, or a store with its own key fetches with another store's key and answers 503 for days.
     */
    public function testTheOrderOfTheRequestWebsiteGivesTheCredentials(): void
    {
        $this->orders = [
            'store 2' => ['incrementId' => '000000101', 'storeId' => 2, 'paymentId' => ''],
            'store 3, website 2' => ['incrementId' => '000000101', 'storeId' => 3, 'paymentId' => ''],
            'only in store 2' => ['incrementId' => '000000201', 'storeId' => 2, 'paymentId' => ''],
            'only in website 2' => ['incrementId' => '000000301', 'storeId' => 3, 'paymentId' => ''],
        ];
        $this->config['store/2/payment/monei/test_api_key'] = 'mk_test_store2';
        $this->config['store/3/payment/monei/test_api_key'] = 'mk_test_store3';

        $this->remote = self::paymentFields('SUCCEEDED', ['orderId' => '000000201']);
        $store2 = $this->verifier()->verify(self::body($this->remote), null);
        $otherWebsite = $this->verifier()->verify(self::body(self::paymentFields('SUCCEEDED', ['orderId' => '000000301'])), null);

        $this->assertSame([200, 'SUCCEEDED'], [$store2['status'], $store2['payment']['status']]);
        $this->assertSame([200, null, 'order'], [$otherWebsite['status'], $otherWebsite['payment'], $otherWebsite['reason']]);
        $this->assertSame([['mk_test_store2', self::ID]], $this->fetches);
    }

    /**
     * Bug: two orders with one increment id in the request store's website: the other store's order is changed.
     */
    public function testTheRequestStoresOrderComesFirst(): void
    {
        $this->orders = [
            'store 2' => ['incrementId' => '000000101', 'storeId' => 2, 'paymentId' => '', 'method' => 'checkmo'],
            'store 1' => ['incrementId' => '000000101', 'storeId' => 1, 'paymentId' => ''],
        ];

        $decision = $this->verifier()->verify(self::body($this->remote), null);

        $this->assertSame([200, 'SUCCEEDED'], [$decision['status'], $decision['payment']['status'] ?? null]);
    }

    /**
     * Bug: store views share a callback URL (one base URL, no store code), so a signed callback of store view 2
     * reaches store view 1 and settles store view 1's order with the same increment ID; or a payment of a store
     * view without that order settles another store view's order.
     *
     * @dataProvider signedPaymentStores
     */
    public function testASignedPaymentSettlesTheOrderOfItsStoreView(array $orders, ?int $expectedOrder): void
    {
        $this->orders = $orders;
        // A website connect: both store views inherit its key and secret.
        $this->config['websites/1/payment/monei/test_api_key'] = self::KEY;
        $this->config['websites/1/payment/monei/test_signing_secret'] = self::SECRET;
        $body = self::body(self::paymentFields('SUCCEEDED', ['metadata' => ['magento_store_id' => '2']]));

        $decision = $this->verifier()->verify($body, self::sign($body, 'v2', self::SECRET));

        $this->assertSame($expectedOrder, $decision['order']);
    }

    public function signedPaymentStores(): array
    {
        $store1 = ['incrementId' => '000000101', 'storeId' => 1, 'paymentId' => '', 'entityId' => 7];
        $store2 = ['incrementId' => '000000101', 'storeId' => 2, 'paymentId' => '', 'entityId' => 8];

        return [
            'both store views have the number' => [['s1' => $store1, 's2' => $store2], 8],
            'only the request store view has it' => [['s1' => $store1], null],
        ];
    }

    /**
     * Bug: a scope with its own manual key inherits the signing secret of another scope's connect (another
     * account): a v2 signed with that secret is accepted for this scope.
     */
    public function testASecretNextToAManualKeyIsNotUsed(): void
    {
        $this->config = [
            self::STORE . 'mode' => '1',
            self::STORE . 'test_api_key' => 'pk_test_manual',
            'default/0/payment/monei/test_signing_secret' => self::SECRET,
        ];
        $body = self::body($this->remote);

        $decision = $this->verifier()->verify($body, self::sign($body, 'v2', self::SECRET));

        $this->assertSame(401, $decision['status']);
    }
}
