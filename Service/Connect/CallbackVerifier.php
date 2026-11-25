<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Connect;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\ApiException;
use Monei\Configuration;
use Monei\MoneiClient;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Model\Config\Source\Mode;
use Monei\MoneiPayment\Model\Service\PaymentAmountMatcher;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\ObjectSerializer;
use InvalidArgumentException;
use Throwable;

/**
 * Decides which payment a MONEI callback may process.
 *
 * - A v2 signature that verifies with the stored signing secret: the body's payment.
 * - Else, on a mode with a scoped key (mk_): the body gives only the payment id. The payment is fetched with the
 *   store's key and checked against the order. So callbacks of orders made before the connect, after a rotation,
 *   or without v2 still settle, and a forged body changes nothing.
 * - Else (a manual pk_ key): v1 with that key.
 */
class CallbackVerifier
{
    public const QUOTA_FLAG = 'monei_callback_fetch_attempts';

    private const ORDER_QUOTA = 5;
    private const SHOP_QUOTA = 60;
    private const WINDOW = 60;
    /** The statuses MONEI sends callbacks for (payments-service handleCallback.ts). */
    private const PROCESSED = ['AUTHORIZED', 'SUCCEEDED', 'CANCELED', 'FAILED', 'PAID_OUT'];

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var OrderFactory
     */
    private OrderFactory $orderFactory;

    /**
     * @var PaymentAmountMatcher
     */
    private PaymentAmountMatcher $amountMatcher;

    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $apiClient;

    /**
     * @var FlagStore
     */
    private FlagStore $flags;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param OrderFactory $orderFactory
     * @param PaymentAmountMatcher $amountMatcher
     * @param MoneiApiClient $apiClient
     * @param FlagStore $flags
     * @param DateTime $dateTime
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        OrderFactory $orderFactory,
        PaymentAmountMatcher $amountMatcher,
        MoneiApiClient $apiClient,
        FlagStore $flags,
        DateTime $dateTime
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->orderFactory = $orderFactory;
        $this->amountMatcher = $amountMatcher;
        $this->apiClient = $apiClient;
        $this->flags = $flags;
        $this->dateTime = $dateTime;
    }

    /**
     * Decide which payment a callback may process
     *
     * @param string $body Raw request body
     * @param string|null $signature MONEI-Signature header, null when absent
     * @return array{status: int, payment: array|null, reason: string} The payment to process, in the body's
     *     camelCase shape, or null with the HTTP status to answer: 400 a failed pre-check, 401 a bad signature,
     *     503 a failed fetch, 200 a payment that must not change the order.
     */
    public function verify(string $body, ?string $signature): array
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return self::refused(400, 'body');
        }
        $storeId = (int) $this->storeManager->getStore()->getId();
        $mode = $this->modeOf($data, $storeId);
        $key = $this->config(ConnectService::path($mode, 'api_key'), $storeId);
        $secret = $this->config(ConnectService::path($mode, 'signing_secret'), $storeId);

        if ($secret !== '' && $signature !== null && self::signatureValid($body, $signature, '', $secret)) {
            return self::accepted((array) json_decode($body));
        }
        if (strpos($key, 'mk_') === 0) {
            return $this->fetched($data, $signature, $key);
        }
        // An empty key gives an HMAC that anyone can make.
        if ($key === '' || $signature === null || !self::signatureValid($body, $signature, $key, null)) {
            return self::refused(401, 'signature');
        }

        return self::accepted((array) json_decode($body));
    }

    /**
     * Fetch the payment of the body's id and check it against the order
     *
     * @param array $data
     * @param string|null $signature
     * @param string $key
     * @return array{status: int, payment: array|null, reason: string}
     */
    private function fetched(array $data, ?string $signature, string $key): array
    {
        $id = $data['id'] ?? null;
        $orderId = $data['orderId'] ?? null;
        if (($signature !== null && !self::headerParses($signature))
            || !is_string($id) || !preg_match('/^[0-9a-f]{32,40}\z/i', $id)
            || !(is_string($orderId) || is_int($orderId))) {
            return self::refused(400, 'format');
        }
        $orderId = (string) $orderId;
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (!$order->getId()) {
            return self::refused(400, 'order');
        }
        $storedId = (string) $order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID);
        if ($storedId !== '' && $storedId !== $id) {
            return self::refused(400, 'paymentId');
        }
        if (!$this->takeQuota($orderId)) {
            return self::refused(400, 'quota');
        }
        try {
            $payment = $this->apiClient->createSdkForApiKey($key)->payments->get($id);
        } catch (Throwable $e) {
            return self::refused(503, 'fetch');
        }
        try {
            // The model keeps its data in a protected snake_case container; this gives the camelCase body shape.
            $fetched = (array) ObjectSerializer::sanitizeForSerialization($payment);
        } catch (InvalidArgumentException $e) {
            // A status this SDK does not know.
            return self::refused(200, 'status');
        }
        if (($fetched['id'] ?? null) !== $id) {
            return self::refused(200, 'id');
        }
        if ((string) ($fetched['orderId'] ?? '') !== $orderId) {
            return self::refused(200, 'orderId');
        }
        if (!$this->amountMatcher->matches(
            (int) ($fetched['amount'] ?? 0),
            (string) ($fetched['currency'] ?? ''),
            (float) $order->getBaseGrandTotal(),
            (string) $order->getBaseCurrencyCode()
        )) {
            return self::refused(200, 'amount');
        }
        $status = $fetched['status'] ?? null;
        if (!in_array($status, self::PROCESSED, true)) {
            return self::refused(200, 'status');
        }
        if ($order->hasInvoices() && in_array($status, ['FAILED', 'CANCELED'], true)) {
            return self::refused(200, 'paid');
        }

        return self::accepted($fetched);
    }

    /**
     * Get the mode the callback names. Each mode verifies with its own key or secret.
     *
     * @param array $data
     * @param int $storeId
     * @return string live or test
     */
    private function modeOf(array $data, int $storeId): string
    {
        if (isset($data['livemode']) && is_bool($data['livemode'])) {
            return $data['livemode'] ? 'live' : 'test';
        }

        return (int) $this->config('payment/monei/mode', $storeId) === Mode::MODE_TEST ? 'test' : 'live';
    }

    /**
     * Count a fetch attempt: at most 5 a minute per order and 60 a minute per store
     *
     * @param string $orderId
     * @return bool
     */
    private function takeQuota(string $orderId): bool
    {
        $now = (int) $this->dateTime->gmtTimestamp();
        $recent = function ($times) use ($now): array {
            return array_values(array_filter((array) $times, function ($time) use ($now) {
                return is_int($time) && $now - $time < self::WINDOW;
            }));
        };
        $attempts = (array) $this->flags->get(self::QUOTA_FLAG);
        $shop = $recent($attempts['shop'] ?? []);
        $orders = [];
        foreach ((array) ($attempts['orders'] ?? []) as $id => $times) {
            $times = $recent($times);
            if ($times) {
                $orders[$id] = $times;
            }
        }
        $order = $orders[$orderId] ?? [];
        if (count($order) >= self::ORDER_QUOTA || count($shop) >= self::SHOP_QUOTA) {
            return false;
        }
        $shop[] = $now;
        $order[] = $now;
        $orders[$orderId] = $order;
        $this->flags->set(self::QUOTA_FLAG, ['shop' => $shop, 'orders' => $orders]);

        return true;
    }

    /**
     * Read a config value of a store
     *
     * @param string $path
     * @param int $storeId
     * @return string
     */
    private function config(string $path, int $storeId): string
    {
        return (string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Check a signature with a key (v1) or a secret (v2)
     *
     * @param string $body
     * @param string $signature
     * @param string $key
     * @param string|null $secret
     * @return bool
     */
    private static function signatureValid(string $body, string $signature, string $key, ?string $secret): bool
    {
        try {
            (new MoneiClient($key, new Configuration()))->verifySignature($body, $signature, $secret);

            return true;
        } catch (ApiException $e) {
            return false;
        }
    }

    /**
     * Check the SDK's reading of the header: comma-separated key=value elements, with a t
     *
     * @param string $signature
     * @return bool
     */
    private static function headerParses(string $signature): bool
    {
        $hasTimestamp = false;
        foreach (explode(',', $signature) as $element) {
            $pair = explode('=', $element, 2);
            if (count($pair) !== 2) {
                return false;
            }
            $hasTimestamp = $hasTimestamp || ($pair[0] === 't' && $pair[1] !== '');
        }

        return $hasTimestamp;
    }

    /**
     * Get an accepted decision
     *
     * @param array $payment
     * @return array{status: int, payment: array, reason: string}
     */
    private static function accepted(array $payment): array
    {
        return ['status' => 200, 'payment' => $payment, 'reason' => 'ok'];
    }

    /**
     * Get a refused decision
     *
     * @param int $status
     * @param string $reason
     * @return array{status: int, payment: null, reason: string}
     */
    private static function refused(int $status, string $reason): array
    {
        return ['status' => $status, 'payment' => null, 'reason' => $reason];
    }
}
