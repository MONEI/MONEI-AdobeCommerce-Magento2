<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Connect;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Monei\ApiException;
use Monei\ConnectException;
use Monei\Model\RegisterApplePayDomainRequest;
use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use Throwable;

/**
 * "Connect with MONEI": the start, the single-use state of the callback, the test call of each new key, and the
 * switch of the credentials of one config scope.
 *
 * It writes with WriterInterface, which runs no backend model, so the ApiKey model of the key fields does not clear
 * the new signing secret.
 */
class ConnectService
{
    public const STATE_PREFIX = 'monei_connect_state_';
    public const RESULT_FLAG = 'monei_connect_result';
    /** Seconds: the broker keeps its pending row 15 min, plus 60 s for its code. */
    public const STATE_TTL = 1200;
    public const DASHBOARD_KEYS_URL = 'https://dashboard.monei.com/settings/api';

    /** The config field prefix of each mode that the broker returns. */
    private const MODES = ['live' => 'production', 'test' => 'test'];
    private const FIELDS = ['account_id', 'api_key', 'signing_secret'];
    private const REFUSED = ['missingCode', 'unknownState', 'expiredState'];

    /**
     * @var FlagStore
     */
    private FlagStore $flags;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var WriterInterface
     */
    private WriterInterface $configWriter;

    /**
     * @var TypeListInterface
     */
    private TypeListInterface $cacheTypeList;

    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $apiClient;

    /**
     * @var Broker
     */
    private Broker $broker;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param FlagStore $flags
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param TypeListInterface $cacheTypeList
     * @param MoneiApiClient $apiClient
     * @param Broker $broker
     * @param DateTime $dateTime
     * @param Logger $logger
     */
    public function __construct(
        FlagStore $flags,
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        MoneiApiClient $apiClient,
        Broker $broker,
        DateTime $dateTime,
        Logger $logger
    ) {
        $this->flags = $flags;
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->apiClient = $apiClient;
        $this->broker = $broker;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    /**
     * Get the config path of a credential
     *
     * @param string $mode live or test
     * @param string $field account_id, api_key or signing_secret
     * @return string
     */
    public static function path(string $mode, string $field): string
    {
        return 'payment/monei/' . self::MODES[$mode] . '_' . $field;
    }

    /**
     * Store a new state for a scope and return the broker URL. Entries of abandoned starts are deleted first.
     *
     * @param string $redirectUri The output of CallbackUrl::redirectUri()
     * @param string $scope
     * @param int $scopeId
     * @return string
     */
    public function begin(string $redirectUri, string $scope, int $scopeId): string
    {
        $now = $this->now();
        foreach ($this->flags->codesWithPrefix(self::STATE_PREFIX) as $code) {
            if (!$this->isFresh($this->flags->get($code), $now)) {
                $this->flags->delete($code);
            }
        }
        $start = $this->broker->start($redirectUri);
        $this->flags->set(self::STATE_PREFIX . $start['state'], [
            'verifier' => $start['verifier'],
            'redirectUri' => $redirectUri,
            'scope' => $scope,
            'scopeId' => $scopeId,
            'createdAt' => $now,
        ]);

        return $start['url'];
    }

    /**
     * Handle the callback: check the state, exchange the code and switch the credentials of the state's scope
     *
     * @param string $state
     * @param string $code
     * @return array{switched: array<string, array>, problems: list<array{mode: string|null, code: string}>}
     */
    public function complete(string $state, string $code): array
    {
        if ($code === '') {
            return self::failed('missingCode');
        }
        $name = self::STATE_PREFIX . $state;
        $entry = preg_match('/^[A-Za-z0-9_-]{43}\z/', $state) ? $this->flags->get($name) : null;
        // Of two callbacks with one state, only the one whose delete removed the entry goes on.
        if (!is_array($entry) || !$this->flags->delete($name)) {
            return self::failed('unknownState');
        }
        if (!$this->isFresh($entry, $this->now())) {
            return self::failed('expiredState');
        }
        $scope = (string) $entry['scope'];
        $scopeId = (int) $entry['scopeId'];
        try {
            $result = $this->broker->exchange($code, (string) $entry['verifier'], (string) $entry['redirectUri']);
            $outcome = $this->swap($result, $scope, $scopeId);
        } catch (ConnectException $e) {
            $outcome = self::failed($e->getError());
        }
        if ($outcome['switched']) {
            $this->afterSwitch($outcome['switched'], $scope, $scopeId, (string) $entry['redirectUri']);
        }
        // A front route cannot make a valid admin link: the settings page shows the result on its next load.
        $this->flags->set(self::RESULT_FLAG, [
            'scope' => $scope,
            'scopeId' => $scopeId,
            'switched' => array_keys($outcome['switched']),
            'problems' => $outcome['problems'],
        ]);

        return $outcome;
    }

    /**
     * Clear the credentials of each connected mode at a scope. A mode with a manual key keeps it. The keys stay
     * active at MONEI until the merchant revokes them.
     *
     * Empty values, never a delete: a delete lets the scope inherit the credentials of a parent scope, possibly of
     * another account.
     *
     * @param string $scope
     * @param int $scopeId
     * @return void
     */
    public function disconnect(string $scope, int $scopeId): void
    {
        // A pending connect would write the credentials back.
        foreach ($this->flags->codesWithPrefix(self::STATE_PREFIX) as $code) {
            $this->flags->delete($code);
        }
        foreach ($this->connectedModes($scope, $scopeId) as $mode) {
            foreach (self::FIELDS as $field) {
                $this->configWriter->save(self::path($mode, $field), '', $scope, $scopeId);
            }
        }
        $this->flush();
    }

    /**
     * Get the API key in use of each mode at a scope
     *
     * @param string $scope
     * @param int $scopeId
     * @return array{live: string, test: string}
     */
    public function apiKeys(string $scope, int $scopeId): array
    {
        return [
            'live' => $this->read(self::path('live', 'api_key'), $scope, $scopeId),
            'test' => $this->read(self::path('test', 'api_key'), $scope, $scopeId),
        ];
    }

    /**
     * Get the modes whose key in use at a scope is a scoped key (mk_) from a connect
     *
     * @param string $scope
     * @param int $scopeId
     * @return string[] live and/or test
     */
    public function connectedModes(string $scope, int $scopeId): array
    {
        return array_keys(array_filter($this->apiKeys($scope, $scopeId), function (string $key) {
            return strpos($key, 'mk_') === 0;
        }));
    }

    /**
     * Take the result of the last connect when it was for this scope: the settings page shows it once
     *
     * @param string $scope
     * @param int $scopeId
     * @return array{switched: string[], problems: list<array{mode: string|null, code: string}>}|null
     */
    public function takeResult(string $scope, int $scopeId): ?array
    {
        $result = $this->flags->get(self::RESULT_FLAG);
        if (!is_array($result) || ($result['scope'] ?? null) !== $scope || ($result['scopeId'] ?? null) !== $scopeId) {
            return null;
        }
        $this->flags->delete(self::RESULT_FLAG);

        return ['switched' => (array) $result['switched'], 'problems' => (array) $result['problems']];
    }

    /**
     * Check whether a callback refusal left the state untouched or unknown, so the result belongs to no scope
     *
     * @param array $outcome The result of complete()
     * @return bool
     */
    public static function isRefused(array $outcome): bool
    {
        return !$outcome['switched'] && in_array($outcome['problems'][0]['code'] ?? '', self::REFUSED, true);
    }

    /**
     * Get the text of a connect problem
     *
     * @param string|null $mode live, test, or null for the whole connect
     * @param string $code A ConnectService problem, an OAuth error code, or a broker code of the mode
     * @return string
     */
    public static function message(?string $mode, string $code): string
    {
        $messages = [
            'missingCode' => __('MONEI did not send a code. Nothing was changed.'),
            'unknownState' => __('This connect link is not valid or was already used. Start again from the MONEI settings.'),
            'expiredState' => __('This connect link expired. Start again from the MONEI settings.'),
            'otherAccount' => __('This scope uses another MONEI account. Disconnect first.'),
            'otherAccountManual' => __(
                'The Production keys of this scope belong to another MONEI account. To connect this account, enter its Production Account Id and Api Key, save, and connect again.'
            ),
            'access_denied' => __('MONEI refused the connect. Nothing was changed.'),
            'invalid_grant' => __('The connect code is not valid or expired. Start again from the MONEI settings.'),
            'temporarily_unavailable' => __('MONEI is not available now. Try again later.'),
            'keyRejected' => __('The new key did not work, so the current credentials stay in use.'),
            'cap' => __('Your MONEI account has too many API keys. Revoke unused keys in the MONEI Dashboard, then connect again.'),
            'not_approved' => __('Live keys come after MONEI approves your account. The store stays in test mode.'),
            'blocked' => __('MONEI refused a key for this account.'),
            'ineligible' => __('This MONEI account cannot get a key for this mode.'),
        ];
        $message = (string) ($messages[$code] ?? __('Connect with MONEI failed. Nothing was changed.'));
        if ($mode === 'live') {
            return (string) __('Live: %1', $message);
        }
        if ($mode === 'test') {
            return (string) __('Test: %1', $message);
        }

        return $message;
    }

    /**
     * Switch each returned mode whose new key passes the test call
     *
     * @param array $result The result of Broker::exchange()
     * @param string $scope
     * @param int $scopeId
     * @return array{switched: array<string, array>, problems: list<array{mode: string|null, code: string}>}
     */
    private function swap(array $result, string $scope, int $scopeId): array
    {
        $problems = [];
        foreach ($result['errors'] ?? [] as $mode => $error) {
            $problems[] = ['mode' => $mode, 'code' => $error];
        }
        $liveAccount = $this->read(self::path('live', 'account_id'), $scope, $scopeId);
        // Open orders of the old account need its key, and a store split across two merchants is wrong. An account
        // id without a key (a settings page saved after a Disconnect) protects nothing.
        if (isset($result['live']) && $liveAccount !== '' && $result['live']['accountId'] !== $liveAccount
            && $this->read(self::path('live', 'api_key'), $scope, $scopeId) !== '') {
            // Only a connected mode has a Disconnect button; manual keys must be replaced by hand.
            $connected = in_array('live', $this->connectedModes($scope, $scopeId), true);
            $problems[] = ['mode' => null, 'code' => $connected ? 'otherAccount' : 'otherAccountManual'];

            return ['switched' => [], 'problems' => $problems];
        }
        $switched = [];
        foreach (array_keys(self::MODES) as $mode) {
            if (!isset($result[$mode])) {
                continue;
            }
            $keys = $result[$mode];
            $status = $this->probe($keys['apiKey']);
            // /allowed-payment-methods answers 400 for a mode without an enabled provider: a 200 or a 400 proves the
            // key. Any other answer proves nothing, so the current credentials stay.
            if ($status !== 200 && $status !== 400) {
                $code = in_array($status, [401, 403], true) ? 'keyRejected' : 'temporarily_unavailable';
                $problems[] = ['mode' => $mode, 'code' => $code];
                continue;
            }
            $this->configWriter->save(self::path($mode, 'account_id'), $keys['accountId'], $scope, $scopeId);
            $this->configWriter->save(self::path($mode, 'api_key'), $keys['apiKey'], $scope, $scopeId);
            $this->configWriter->save(self::path($mode, 'signing_secret'), $keys['signingSecret'], $scope, $scopeId);
            $switched[$mode] = $keys;
        }

        return ['switched' => $switched, 'problems' => $problems];
    }

    /**
     * Get the HTTP status of a test call with a new key, on a fresh client: 0 when no answer came
     *
     * @param string $apiKey
     * @return int
     */
    private function probe(string $apiKey): int
    {
        try {
            $this->apiClient->createSdkForApiKey($apiKey)->paymentMethods->getAllowed();

            return 200;
        } catch (ApiException $e) {
            return (int) $e->getCode();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Clean the config cache, reset the SDK cache and register the Apple Pay domain with each new key
     *
     * @param array<string, array> $switched
     * @param string $scope
     * @param int $scopeId
     * @param string $redirectUri
     * @return void
     */
    private function afterSwitch(array $switched, string $scope, int $scopeId, string $redirectUri): void
    {
        $this->flush();
        if (!$this->read(MoneiGoogleApplePaymentModuleConfigInterface::IS_PAYMENT_ENABLED, $scope, $scopeId)) {
            return;
        }
        // The callback URL is on the scope's storefront.
        $domain = (string) parse_url($redirectUri, PHP_URL_HOST);
        foreach ($switched as $mode => $keys) {
            try {
                $this->apiClient->createSdkForApiKey($keys['apiKey'])->applePayDomain->register(
                    new RegisterApplePayDomainRequest(['domain_name' => $domain])
                );
            } catch (Throwable $e) {
                // The new key works: an Apple Pay error keeps the switch.
                $this->logger->error(
                    '[Connect] Apple Pay domain registration after connect failed (' . $mode . '): ' . get_class($e)
                );
            }
        }
    }

    /**
     * Make the next requests read the new credentials
     *
     * @return void
     */
    private function flush(): void
    {
        $this->cacheTypeList->cleanType('config');
        $this->apiClient->resetSdkInstance();
    }

    /**
     * Read a config value in effect at a scope
     *
     * @param string $path
     * @param string $scope
     * @param int $scopeId
     * @return string
     */
    private function read(string $path, string $scope, int $scopeId): string
    {
        $scopeCode = $scope === ScopeConfigInterface::SCOPE_TYPE_DEFAULT ? null : $scopeId;

        return (string) $this->scopeConfig->getValue($path, $scope, $scopeCode);
    }

    /**
     * Get the current Unix time
     *
     * @return int
     */
    private function now(): int
    {
        return (int) $this->dateTime->gmtTimestamp();
    }

    /**
     * Check whether a stored state entry is younger than 20 min
     *
     * @param mixed $entry
     * @param int $now
     * @return bool
     */
    private function isFresh($entry, int $now): bool
    {
        return is_array($entry) && isset($entry['createdAt']) && is_int($entry['createdAt'])
            && $now - $entry['createdAt'] < self::STATE_TTL;
    }

    /**
     * Get a result with one problem of the whole connect
     *
     * @param string $code
     * @return array{switched: array, problems: list<array{mode: null, code: string}>}
     */
    private static function failed(string $code): array
    {
        return ['switched' => [], 'problems' => [['mode' => null, 'code' => $code]]];
    }
}
