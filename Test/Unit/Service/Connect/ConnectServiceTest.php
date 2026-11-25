<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Connect;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Monei\Api\ApplePayDomainApi;
use Monei\Api\PaymentMethodsApi;
use Monei\ApiException;
use Monei\ConnectException;
use Monei\Model\RegisterApplePayDomainRequest;
use Monei\MoneiClient;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Connect\Broker;
use Monei\MoneiPayment\Service\Connect\ConnectService;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * ConnectService: the single-use state, the test call of each new key, and the switch of one scope's credentials.
 *
 * Every test names the bug it catches.
 */
class ConnectServiceTest extends TestCase
{
    use ConfigFake;

    private const NOW = 1700000000;
    private const STATE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const REDIRECT = 'https://shop.example/monei/payment/connect/';
    private const SCOPE = 'websites/1/payment/monei/';

    /** @var array[] */
    private array $exchanges = [];

    /** @var array|ConnectException */
    private $exchangeAnswer = [];

    /** @var string[] Keys of the fresh clients, in order */
    private array $clients = [];

    /** @var array<string, int> */
    private array $statuses = [];

    /** @var array[] [key, domain] of each Apple Pay registration */
    private array $applePay = [];

    /** @var MoneiApiClient|MockObject */
    private $apiClient;

    /** @var TypeListInterface|MockObject */
    private $cacheTypeList;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(MoneiApiClient::class);
        $this->apiClient->method('createSdkForApiKey')->willReturnCallback(function (string $key) {
            $this->clients[] = $key;
            $client = $this->getMockBuilder(MoneiClient::class)->disableOriginalConstructor()->getMock();
            $client->paymentMethods = $this->createMock(PaymentMethodsApi::class);
            $client->paymentMethods->method('getAllowed')->willReturnCallback(function () use ($key) {
                $status = $this->statuses[$key] ?? 200;
                if ($status !== 200) {
                    throw new ApiException('refused', $status);
                }
                return null;
            });
            $client->applePayDomain = $this->createMock(ApplePayDomainApi::class);
            $client->applePayDomain->method('register')->willReturnCallback(
                function (RegisterApplePayDomainRequest $request) use ($key) {
                    $this->applePay[] = [$key, $request->getDomainName()];
                    if ($key === 'mk_test_new') {
                        throw new ApiException('domain refused', 400);
                    }
                    return null;
                }
            );
            return $client;
        });
        $this->apiClient->expects($this->never())->method('getMoneiSdk');
        $this->cacheTypeList = $this->createMock(TypeListInterface::class);
    }

    private function service(MemoryFlagStore $flags): ConnectService
    {
        $broker = $this->getMockBuilder(Broker::class)->onlyMethods(['exchange'])->getMock();
        $broker->method('exchange')->willReturnCallback(function (...$args) {
            $this->exchanges[] = $args;
            if ($this->exchangeAnswer instanceof ConnectException) {
                throw $this->exchangeAnswer;
            }
            return $this->exchangeAnswer;
        });
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(self::NOW);

        return new ConnectService(
            $flags,
            $this->fakeScopeConfig(),
            $this->fakeConfigWriter(),
            $this->cacheTypeList,
            $this->apiClient,
            $broker,
            $dateTime,
            $this->createMock(Logger::class)
        );
    }

    private static function entry(array $overrides = []): array
    {
        return $overrides + [
            'verifier' => 'the-verifier',
            'redirectUri' => self::REDIRECT,
            'scope' => 'websites',
            'scopeId' => 1,
            'createdAt' => self::NOW - 60,
        ];
    }

    /**
     * Website 1 with manual pk_ credentials in both modes, in production mode, and a fresh state entry.
     */
    private function manualStore(): MemoryFlagStore
    {
        $this->config = [
            self::SCOPE . 'mode' => '2',
            self::SCOPE . 'production_account_id' => 'acc-live',
            self::SCOPE . 'production_api_key' => 'pk_live_old',
            self::SCOPE . 'test_account_id' => 'acc-test-old',
            self::SCOPE . 'test_api_key' => 'pk_test_old',
        ];

        return new MemoryFlagStore([ConnectService::STATE_PREFIX . self::STATE => self::entry()]);
    }

    private static function keys(string $mode, string $account): array
    {
        return ['accountId' => $account, 'apiKey' => "mk_{$mode}_new", 'signingSecret' => "mksec_{$mode}_new"];
    }

    private function credentials(): array
    {
        $credentials = [];
        foreach (['mode', 'production_account_id', 'production_api_key', 'production_signing_secret',
            'test_account_id', 'test_api_key', 'test_signing_secret'] as $field) {
            $credentials[$field] = $this->config[self::SCOPE . $field] ?? null;
        }

        return $credentials;
    }

    private static function codes(array $result): array
    {
        return array_map(function ($problem) {
            return [$problem['mode'], $problem['code']];
        }, $result['problems']);
    }

    /**
     * Bug: a callback code is accepted without the stored state, after 20 min, as a second callback that lost the
     * delete race, or through a state that is not ours (the shape check guards the flag code).
     *
     * @dataProvider refusedStates
     */
    public function testAStateThatIsNotStoredAndFreshReachesNoExchange(
        string $state,
        array $flags,
        array $lostDeletes,
        string $code
    ): void {
        $store = $this->manualStore();
        $store->flags = $flags;
        $store->lostDeletes = $lostDeletes;
        $before = $this->credentials();

        $result = $this->service($store)->complete($state, 'the-code');

        $this->assertSame([], $this->exchanges);
        $this->assertSame([[null, $code]], self::codes($result));
        $this->assertSame($before, $this->credentials());
    }

    public function refusedStates(): array
    {
        $code = ConnectService::STATE_PREFIX . self::STATE;

        return [
            'unknown' => [str_repeat('b', 43), [$code => self::entry()], [], 'unknownState'],
            'not our shape' => ['x', [ConnectService::STATE_PREFIX . 'x' => self::entry()], [], 'unknownState'],
            '20 min old' => [self::STATE, [$code => self::entry(['createdAt' => self::NOW - 1200])], [], 'expiredState'],
            'lost the race' => [self::STATE, [$code => self::entry()], [$code], 'unknownState'],
        ];
    }

    /**
     * Bug: a state is accepted twice, so a replayed callback URL exchanges again. Also pins the exchange arguments:
     * another verifier or redirect URI than the start sent is always invalid_grant.
     */
    public function testAStateIsSingleUse(): void
    {
        $store = $this->manualStore();
        $this->exchangeAnswer = ['test' => self::keys('test', 'acc-test-old')];
        $service = $this->service($store);

        $service->complete(self::STATE, 'the-code');
        $second = $service->complete(self::STATE, 'the-code');

        $this->assertSame([['the-code', 'the-verifier', self::REDIRECT]], $this->exchanges);
        $this->assertSame([[null, 'unknownState']], self::codes($second));
        $this->assertArrayNotHasKey(ConnectService::STATE_PREFIX . self::STATE, $store->flags);
    }

    /**
     * Bug: a request without code spends the merchant's state, so the real callback that follows is refused.
     */
    public function testACallbackWithoutCodeKeepsTheStateAndChangesNothing(): void
    {
        $store = $this->manualStore();
        $before = $store->flags;

        $result = $this->service($store)->complete(self::STATE, '');

        $this->assertSame([], $this->exchanges);
        $this->assertSame([[null, 'missingCode']], self::codes($result));
        $this->assertSame($before, $store->flags);
    }

    /**
     * Bug: abandoned starts leave state rows behind for ever, a start deletes the entry of a connect still in
     * progress in another tab, or the entry misses the scope, so the callback switches the wrong scope.
     */
    public function testAStartDeletesEntriesOlderThan20MinutesAndStoresTheScope(): void
    {
        $old = ConnectService::STATE_PREFIX . str_repeat('o', 43);
        $young = ConnectService::STATE_PREFIX . str_repeat('y', 43);
        $store = new MemoryFlagStore([
            $old => self::entry(['createdAt' => self::NOW - 1200]),
            $young => self::entry(['createdAt' => self::NOW - 1199]),
            'other_module_flag' => ['createdAt' => 0],
        ]);

        $url = $this->service($store)->begin(self::REDIRECT, 'stores', 3);

        $this->assertArrayNotHasKey($old, $store->flags);
        $this->assertArrayHasKey($young, $store->flags);
        $this->assertArrayHasKey('other_module_flag', $store->flags);
        $this->assertStringStartsWith('https://oauth.monei.com/integrations/authorize?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $entry = $store->flags[ConnectService::STATE_PREFIX . $query['state']];
        $this->assertSame(
            [self::REDIRECT, 'stores', 3, self::NOW, 64],
            [$entry['redirectUri'], $entry['scope'], $entry['scopeId'], $entry['createdAt'], strlen($entry['verifier'])]
        );
    }

    /**
     * Bug: a failed exchange wipes or half-writes working manual credentials.
     */
    public function testAFailedExchangeChangesNothing(): void
    {
        $store = $this->manualStore();
        $before = $this->credentials();
        $this->exchangeAnswer = new ConnectException('access_denied', 403);

        $result = $this->service($store)->complete(self::STATE, 'the-code');

        $this->assertSame([[null, 'access_denied']], self::codes($result));
        $this->assertSame($before, $this->credentials());
    }

    /**
     * Bug: a 200 that refuses both modes (cap, not_approved) changes credentials or the mode, or hides a refusal
     * code. The store has no Live key, so a mode switch without a switched key shows here.
     */
    public function testA200WithOnlyErrorsChangesNothingAndReportsEachCode(): void
    {
        $store = $this->manualStore();
        unset($this->config[self::SCOPE . 'production_account_id'], $this->config[self::SCOPE . 'production_api_key']);
        $before = $this->credentials();
        $this->exchangeAnswer = ['errors' => ['live' => 'not_approved', 'test' => 'cap']];

        $result = $this->service($store)->complete(self::STATE, 'the-code');

        $this->assertSame([['live', 'not_approved'], ['test', 'cap']], self::codes($result));
        $this->assertSame([], $result['switched']);
        $this->assertSame($before, $this->credentials());
    }

    /**
     * Bug: the merchant cannot act on cap or not_approved: cap must send them to revoke unused keys, not_approved
     * must say Live keys come after the approval.
     */
    public function testTheCapAndNotApprovedTextsSayWhatToDo(): void
    {
        $this->assertSame(
            [
                'Test: Your MONEI account has too many API keys. Revoke unused keys in the MONEI Dashboard, then connect again.',
                'Live: Live keys come after MONEI approves your account. The store stays in test mode.',
            ],
            [ConnectService::message('test', 'cap'), ConnectService::message('live', 'not_approved')]
        );
    }

    /**
     * Bug: the test call goes out with the stored key or the cached client, so a broken new key replaces a working
     * one. setUp fails the test on any getMoneiSdk() call.
     */
    public function testTheTestCallUsesEachNewKeyOnAFreshClient(): void
    {
        $this->exchangeAnswer = ['live' => self::keys('live', 'acc-live'), 'test' => self::keys('test', 'acc-test-new')];

        $this->service($this->manualStore())->complete(self::STATE, 'the-code');

        $this->assertSame(['mk_live_new', 'mk_test_new'], $this->clients);
    }

    /**
     * Bug: a key the edge refuses, or a key MONEI did not get to check (an outage, a rate limit), replaces working
     * credentials; or a 400 (a mode with no enabled provider) is taken for a refused key and blocks the connect; or
     * an outage tells the merchant the new key is bad.
     *
     * @dataProvider callAnswers
     */
    public function testTheTestCallAnswerDecidesTheSwitch(int $status, array $expectedTest, array $expectedCodes): void
    {
        $store = $this->manualStore();
        $this->exchangeAnswer = ['test' => self::keys('test', 'acc-test-new')];
        $this->statuses = ['mk_test_new' => $status];

        $result = $this->service($store)->complete(self::STATE, 'the-code');

        $credentials = $this->credentials();
        $this->assertSame(
            $expectedTest,
            [$credentials['test_account_id'], $credentials['test_api_key'], $credentials['test_signing_secret']]
        );
        $this->assertSame($expectedCodes, self::codes($result));
    }

    public function callAnswers(): array
    {
        $new = ['acc-test-new', 'mk_test_new', 'mksec_test_new'];
        $kept = ['acc-test-old', 'pk_test_old', null];

        return [
            '400 proves the key' => [400, $new, []],
            '401' => [401, $kept, [['test', 'keyRejected']]],
            '403' => [403, $kept, [['test', 'keyRejected']]],
            '429' => [429, $kept, [['test', 'temporarily_unavailable']]],
            '500' => [500, $kept, [['test', 'temporarily_unavailable']]],
            'no answer' => [0, $kept, [['test', 'temporarily_unavailable']]],
        ];
    }

    /**
     * Bug: one refused mode drops the other, or flips the store's mode.
     */
    public function testTestProvenAndLiveRefusedSwitchesTestOnly(): void
    {
        $store = $this->manualStore();
        $this->exchangeAnswer = ['live' => self::keys('live', 'acc-live'), 'test' => self::keys('test', 'acc-test-new')];
        $this->statuses = ['mk_live_new' => 401];

        $result = $this->service($store)->complete(self::STATE, 'the-code');

        $this->assertSame(
            [
                'mode' => '2',
                'production_account_id' => 'acc-live',
                'production_api_key' => 'pk_live_old',
                'production_signing_secret' => null,
                'test_account_id' => 'acc-test-new',
                'test_api_key' => 'mk_test_new',
                'test_signing_secret' => 'mksec_test_new',
            ],
            $this->credentials()
        );
        $this->assertSame([['live', 'keyRejected']], self::codes($result));
    }

    /**
     * Bug: a live store is switched to test mode, or loses its manual Live keys, when only Test comes back.
     */
    public function testOnlyTestOnAStoreWithManualLiveKeepsLiveAndTheMode(): void
    {
        $store = $this->manualStore();
        $this->exchangeAnswer = ['test' => self::keys('test', 'acc-test-new')];

        $this->service($store)->complete(self::STATE, 'the-code');

        $credentials = $this->credentials();
        $this->assertSame(
            ['2', 'acc-live', 'pk_live_old', 'mk_test_new'],
            [$credentials['mode'], $credentials['production_account_id'], $credentials['production_api_key'],
                $credentials['test_api_key']]
        );
    }

    /**
     * Bug: a store with no Live credentials stays in production mode after it gets only a Test key (not_approved),
     * so every payment fails.
     */
    public function testOnlyTestOnAStoreWithoutLiveTurnsTestModeOn(): void
    {
        $this->config = [self::SCOPE . 'mode' => '2'];
        $store = new MemoryFlagStore([ConnectService::STATE_PREFIX . self::STATE => self::entry()]);
        $this->exchangeAnswer = ['test' => self::keys('test', 'acc-test-new'), 'errors' => ['live' => 'not_approved']];

        $this->service($store)->complete(self::STATE, 'the-code');

        $this->assertSame(['1', 'mk_test_new'], [$this->config[self::SCOPE . 'mode'], $this->config[self::SCOPE . 'test_api_key']]);
    }

    /**
     * Bug: a store is split across two MONEI accounts: open orders of the old Live account lose their key. The Test
     * result is refused too, even proven. A store with manual Live keys has no Disconnect button, so its text must
     * not say "Disconnect first".
     *
     * @dataProvider liveKeysOfAnotherAccount
     */
    public function testAnotherLiveAccountSwitchesNeitherMode(string $liveKey, string $code): void
    {
        $store = $this->manualStore();
        $this->config[self::SCOPE . 'production_api_key'] = $liveKey;
        $before = $this->credentials();
        $this->exchangeAnswer = ['live' => self::keys('live', 'acc-other'), 'test' => self::keys('test', 'acc-test-new')];

        $result = $this->service($store)->complete(self::STATE, 'the-code');

        $this->assertSame($before, $this->credentials());
        $this->assertSame([[null, $code]], self::codes($result));
        $this->assertSame([], $this->clients);
    }

    /**
     * Bug: a Live account id left without its key (a settings page loaded before a Disconnect posts it back) blocks
     * every connect of another account.
     */
    public function testALiveAccountIdWithoutAKeyDoesNotBlockAnotherAccount(): void
    {
        $this->config = [self::SCOPE . 'production_account_id' => 'acc-old', self::SCOPE . 'production_api_key' => ''];
        $store = new MemoryFlagStore([ConnectService::STATE_PREFIX . self::STATE => self::entry()]);
        $this->exchangeAnswer = ['live' => self::keys('live', 'acc-other')];

        $result = $this->service($store)->complete(self::STATE, 'the-code');

        $this->assertSame([[], 'mk_live_new'], [self::codes($result), $this->config[self::SCOPE . 'production_api_key']]);
    }

    public function liveKeysOfAnotherAccount(): array
    {
        return [
            'connected' => ['mk_live_old', 'otherAccount'],
            'manual' => ['pk_live_old', 'otherAccountManual'],
        ];
    }

    /**
     * Bug: a new Test account id is refused. The 2026-10-22 repair gave many live accounts a new test account, and
     * the token answer does not say which live account a test account belongs to.
     */
    public function testOnlyTestWithANewTestAccountSwitchesTest(): void
    {
        $store = $this->manualStore();
        $this->exchangeAnswer = ['test' => self::keys('test', 'acc-test-new')];

        $result = $this->service($store)->complete(self::STATE, 'the-code');

        $this->assertSame(['test'], array_keys($result['switched']));
        $this->assertSame('acc-test-new', $this->config[self::SCOPE . 'test_account_id']);
    }

    /**
     * Bug: the result goes to the scope of the admin page or the default scope, not to the scope that started the
     * connect, so another website gets the keys.
     */
    public function testTheResultIsStoredInTheScopeOfTheStateEntry(): void
    {
        $this->config = ['default/0/payment/monei/test_api_key' => 'pk_test_default'];
        $store = new MemoryFlagStore([
            ConnectService::STATE_PREFIX . self::STATE => self::entry(['scope' => 'stores', 'scopeId' => 4]),
        ]);
        $this->exchangeAnswer = ['test' => self::keys('test', 'acc-test-new')];

        $this->service($store)->complete(self::STATE, 'the-code');

        $this->assertSame('mk_test_new', $this->config['stores/4/payment/monei/test_api_key']);
        $this->assertSame('pk_test_default', $this->config['default/0/payment/monei/test_api_key']);
        $this->assertSame(
            ['scope' => 'stores', 'scopeId' => 4, 'switched' => ['test'], 'problems' => []],
            $store->flags[ConnectService::RESULT_FLAG]
        );
    }

    /**
     * Bug: after a switch the store keeps the old key until someone flushes the config cache, or keeps the cached
     * SDK client of the old key.
     */
    public function testASwitchCleansTheConfigCacheAndResetsTheSdkCache(): void
    {
        $this->cacheTypeList->expects($this->once())->method('cleanType')->with('config');
        $this->apiClient->expects($this->once())->method('resetSdkInstance')->with(null);
        $this->exchangeAnswer = ['test' => self::keys('test', 'acc-test-new')];

        $this->service($this->manualStore())->complete(self::STATE, 'the-code');
    }

    /**
     * Bug: Apple Pay registers through the cached client of the old key (config), so the domain is not registered
     * for the new account; or an Apple Pay error undoes a switch whose key works.
     */
    public function testApplePayRegistersWithTheNewKeyOfEachModeAndItsErrorKeepsTheSwitch(): void
    {
        $store = $this->manualStore();
        $this->config['websites/1/payment/monei_google_apple/active'] = '1';
        $this->exchangeAnswer = ['live' => self::keys('live', 'acc-live'), 'test' => self::keys('test', 'acc-test-new')];

        $result = $this->service($store)->complete(self::STATE, 'the-code');

        $this->assertSame([['mk_live_new', 'shop.example'], ['mk_test_new', 'shop.example']], $this->applePay);
        $this->assertSame(['live', 'test'], array_keys($result['switched']));
        $this->assertSame('mk_test_new', $this->config[self::SCOPE . 'test_api_key']);
    }

    /**
     * Bug: a disconnect at a website deletes its rows, so the website inherits the default scope's credentials,
     * possibly of another account; or leaves them in the config cache; or clears a mode with a manual key, which
     * the merchant typed and cannot get back from a connect; or leaves a pending connect, whose callback writes the
     * credentials back. The fake fails on any delete.
     */
    public function testADisconnectAtAWebsiteWritesEmptyValuesOfTheConnectedModesThere(): void
    {
        $this->config = [
            'default/0/payment/monei/production_api_key' => 'pk_live_default',
            self::SCOPE . 'production_api_key' => 'mk_live_site',
            self::SCOPE . 'production_signing_secret' => 'mksec_live_site',
            self::SCOPE . 'test_account_id' => 'acc-test',
            self::SCOPE . 'test_api_key' => 'pk_test_site',
        ];
        $this->cacheTypeList->expects($this->once())->method('cleanType')->with('config');

        $flags = new MemoryFlagStore([
            ConnectService::STATE_PREFIX . self::STATE => self::entry(),
            ConnectService::STATE_PREFIX . 'other' => self::entry(['scope' => 'default', 'scopeId' => 0]),
        ]);

        $this->service($flags)->disconnect('websites', 1);

        $this->assertSame([], $flags->codesWithPrefix(ConnectService::STATE_PREFIX));

        $this->assertSame(
            ['mode' => null, 'production_account_id' => '', 'production_api_key' => '', 'production_signing_secret' => '',
                'test_account_id' => 'acc-test', 'test_api_key' => 'pk_test_site', 'test_signing_secret' => null],
            $this->credentials()
        );
        $this->assertSame('pk_live_default', $this->config['default/0/payment/monei/production_api_key']);
    }
}
