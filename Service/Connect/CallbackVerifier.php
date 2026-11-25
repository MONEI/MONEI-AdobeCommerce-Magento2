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
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\ApiException;
use Monei\Configuration;
use Monei\MoneiClient;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\Data\PaymentInfoInterface;
use Monei\MoneiPayment\Model\Config\Source\Mode;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Api\CreatePayment;
use Monei\MoneiPayment\Service\Api\FetchedPayments;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\ObjectSerializer;
use InvalidArgumentException;
use Throwable;

/**
 * Decides which payment a MONEI callback may process.
 *
 * The order of the body's orderId, in the request store's website, gives the store whose mode, key and secret
 * count. Then:
 * - A v2 signature that verifies with the signing secret of that store's scoped key (mk_): the body's payment.
 * - Else, on a mode with a scoped key: the body gives only the payment id. The payment is fetched with the store's
 *   key and checked against the order. So callbacks of orders made before the connect, after a rotation, or
 *   without v2 still settle, and a forged body changes nothing. Only a forward move from the status stored for the
 *   order passes, so a replay of a settled payment never reopens a canceled order. The payment processor checks
 *   the amount, as of a signed payment. GetPayment answers from the fetched model, so processing does not fetch
 *   again.
 * - Else (a manual pk_ key): v1 with that key.
 * A signed body of the other mode changes nothing.
 */
class CallbackVerifier
{
    public const QUOTA_FLAG = 'monei_callback_fetch_attempts';

    private const ORDER_QUOTA = 5;
    private const SHOP_QUOTA = 60;
    private const WINDOW = 60;
    /** The stored statuses of a payment that paid or holds the order: another payment id is not fetched. */
    private const SETTLED = ['AUTHORIZED', 'SUCCEEDED', 'PAID_OUT'];
    /** The fetched statuses that move each stored status forward ('' = none stored). Any other pair, the same
     * status included, changes nothing. */
    private const FORWARD = [
        '' => ['AUTHORIZED', 'SUCCEEDED', 'FAILED', 'CANCELED'],
        'PENDING' => ['AUTHORIZED', 'SUCCEEDED', 'FAILED', 'CANCELED'],
        'FAILED' => ['AUTHORIZED', 'SUCCEEDED', 'CANCELED'],
        'CANCELED' => ['AUTHORIZED', 'SUCCEEDED', 'FAILED'],
        'EXPIRED' => ['AUTHORIZED', 'SUCCEEDED', 'FAILED', 'CANCELED'],
        'AUTHORIZED' => ['SUCCEEDED', 'CANCELED'],
        'SUCCEEDED' => ['PAID_OUT'],
    ];

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var OrderCollectionFactory
     */
    private OrderCollectionFactory $orderCollectionFactory;

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
     * @var FetchedPayments
     */
    private FetchedPayments $fetchedPayments;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param MoneiApiClient $apiClient
     * @param FlagStore $flags
     * @param DateTime $dateTime
     * @param FetchedPayments $fetchedPayments
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        OrderCollectionFactory $orderCollectionFactory,
        MoneiApiClient $apiClient,
        FlagStore $flags,
        DateTime $dateTime,
        FetchedPayments $fetchedPayments
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->apiClient = $apiClient;
        $this->flags = $flags;
        $this->dateTime = $dateTime;
        $this->fetchedPayments = $fetchedPayments;
    }

    /**
     * Decide which payment a callback may process
     *
     * @param string $body Raw request body
     * @param string|null $signature MONEI-Signature header, null when absent
     * The order comes first: its store gives the mode, key and secret. Increment ids repeat across stores, so only
     * the stores of the request store's website count, the request store first.
     *
     * @param string $body Raw request body
     * @param string|null $signature MONEI-Signature header, null when absent
     * @return array{status: int, payment: array|null, reason: string, order: int|null} The payment to process, in
     *     the body's camelCase shape, with the entity ID of the order it may change, or null with the HTTP status to
     *     answer: 400 a malformed body or another payment of a
     *     settled order, 401 a bad signature, 503 a failed fetch or no fetch quota (MONEI retries), 200 a payment
     *     that must not change the order.
     */
    public function verify(string $body, ?string $signature): array
    {
        $data = json_decode($body, true);
        $orderId = is_array($data) ? ($data['orderId'] ?? null) : null;
        if (!(is_string($orderId) || is_int($orderId)) || (string) $orderId === '') {
            return self::refused(400, 'format');
        }
        $order = $this->findOrder((string) $orderId);
        // No retry changes this answer.
        if ($order === null) {
            return self::refused(200, 'order');
        }
        $storeId = (int) $order->getStoreId();
        $storeMode = (int) $this->config('payment/monei/mode', $storeId) === Mode::MODE_TEST ? 'test' : 'live';
        $mode = $storeMode;
        if (isset($data['livemode']) && is_bool($data['livemode'])) {
            $mode = $data['livemode'] ? 'live' : 'test';
        }
        $key = $this->config(ConnectService::path($mode, 'api_key'), $storeId);
        // A secret belongs to the scoped key of its connect: one inherited next to a manual key is another account's.
        $secret = strpos($key, 'mk_') === 0
            ? $this->config(ConnectService::path($mode, 'signing_secret'), $storeId)
            : '';

        if ($secret !== '' && $signature !== null && self::signatureValid($body, $signature, '', $secret)) {
            return $this->signed($body, $mode, $storeMode, $order);
        }
        if (strpos($key, 'mk_') === 0) {
            return $this->fetched($data, $signature, $key, $order, $storeMode);
        }
        // An empty key gives an HMAC that anyone can make.
        if ($key === '' || $signature === null || !self::signatureValid($body, $signature, $key, null)) {
            return self::refused(401, 'signature');
        }

        return $this->signed($body, $mode, $storeMode, $order);
    }

    /**
     * Accept a signed body of the order store's mode. A signature of the other mode proves the body, not that it
     * may change this order: a test payment must not settle a live order.
     *
     * @param string $body
     * @param string $mode The mode whose key or secret proved the body
     * @param string $storeMode The mode of the order's store
     * @param Order $order
     * @return array{status: int, payment: array|null, reason: string, order: int|null}
     */
    private function signed(string $body, string $mode, string $storeMode, Order $order): array
    {
        if ($mode !== $storeMode) {
            return self::refused(200, 'livemode');
        }

        return self::accepted((array) json_decode($body), $order);
    }

    /**
     * Find the order of an increment id in the stores of the request store's website, the request store first
     *
     * @param string $incrementId
     * @return Order|null
     */
    private function findOrder(string $incrementId): ?Order
    {
        $store = $this->storeManager->getStore();
        $storeIds = array_map('intval', $this->storeManager->getWebsite($store->getWebsiteId())->getStoreIds());
        $orders = $this->orderCollectionFactory->create()
            ->addFieldToFilter('increment_id', $incrementId)
            ->addFieldToFilter('store_id', ['in' => $storeIds])
            ->getItems();
        $found = null;
        foreach ($orders as $order) {
            if ((int) $order->getStoreId() === (int) $store->getId()) {
                return $order;
            }
            $found = $found ?? $order;
        }

        return $found;
    }

    /**
     * Fetch the payment of the body's id and check it against the order
     *
     * @param array $data
     * @param string|null $signature
     * @param string $key
     * @param Order $order
     * @param string $storeMode The mode of the order's store
     * @return array{status: int, payment: array|null, reason: string, order: int|null}
     */
    private function fetched(array $data, ?string $signature, string $key, Order $order, string $storeMode): array
    {
        $id = $data['id'] ?? null;
        if (($signature !== null && !self::headerParses($signature))
            || !is_string($id) || !preg_match('/^[0-9a-f]{32,40}\z/i', $id)) {
            return self::refused(400, 'format');
        }
        $orderId = (string) $data['orderId'];
        // No retry changes this answer.
        if (!$order->getPayment() || !in_array($order->getPayment()->getMethod(), Monei::PAYMENT_METHODS_MONEI, true)) {
            return self::refused(200, 'method');
        }
        $storedId = (string) $order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID);
        if ($storedId !== '' && $storedId !== $id
            && in_array($this->storedStatus($order, $storedId), self::SETTLED, true)) {
            return self::refused(400, 'paymentId');
        }
        // Increment IDs repeat across stores: the order quota counts the order itself.
        if (!$this->takeQuota((string) $order->getId())) {
            return self::refused(503, 'quota');
        }
        try {
            $payment = $this->apiClient->createSdkForApiKey($key)->payments->get($id);
        } catch (InvalidArgumentException $e) {
            // The SDK's deserialize refuses a value it does not know (a new status). A retry gets the same answer.
            return self::refused(200, 'unknown value: ' . $e->getMessage());
        } catch (Throwable $e) {
            return self::refused(503, 'fetch');
        }
        // The model keeps its data in a protected snake_case container; this gives the camelCase body shape.
        $fetched = (array) ObjectSerializer::sanitizeForSerialization($payment);
        if (($fetched['id'] ?? null) !== $id) {
            return self::refused(200, 'id');
        }
        if ((string) ($fetched['orderId'] ?? '') !== $orderId) {
            return self::refused(200, 'orderId');
        }
        // Store views can share a scoped key and an increment ID: a payment of another store view (its metadata
        // says which; payments made before this check carry none) must not settle this order.
        $metadata = (array) ($fetched['metadata'] ?? []);
        if (isset($metadata[CreatePayment::STORE_METADATA])
            && (string) $metadata[CreatePayment::STORE_METADATA] !== (string) $order->getStoreId()) {
            return self::refused(200, 'store');
        }
        if (($fetched['livemode'] ?? null) !== ($storeMode === 'live')) {
            return self::refused(200, 'livemode');
        }
        $status = (string) ($fetched['status'] ?? '');
        $stored = $this->effectiveStatus($order, $storedId);
        if (!in_array($status, self::FORWARD[$stored] ?? [], true)) {
            return self::refused(200, 'notForward');
        }
        if ($order->hasInvoices() && in_array($status, ['FAILED', 'CANCELED'], true)) {
            return self::refused(200, 'paid');
        }

        $this->fetchedPayments->add($payment);

        return self::accepted($fetched, $order);
    }

    /**
     * Get the status stored for the order. With none stored: SUCCEEDED for an invoiced order in processing, complete
     * or closed, AUTHORIZED for one without invoice (the processor sets processing on AUTHORIZED), else none ('').
     *
     * @param Order $order
     * @param string $storedId
     * @return string
     */
    private function effectiveStatus(Order $order, string $storedId): string
    {
        $stored = $this->storedStatus($order, $storedId);
        // The processor can save SUCCEEDED before its invoice: without one, a retry must still settle the order, while
        // it is open. A canceled or closed order stays protected from a replay.
        $open = [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT, Order::STATE_PAYMENT_REVIEW];
        if ($stored === 'SUCCEEDED' && !$order->hasInvoices() && in_array($order->getState(), $open, true)) {
            return '';
        }
        if (is_string($stored) && $stored !== '') {
            return $stored;
        }
        $settledStates = [Order::STATE_PROCESSING, Order::STATE_COMPLETE, Order::STATE_CLOSED];
        if (!in_array($order->getState(), $settledStates, true)) {
            return '';
        }

        return $order->hasInvoices() ? 'SUCCEEDED' : 'AUTHORIZED';
    }

    /**
     * Get the status of the order's stored payment that the processor last stored, or null when none is known
     *
     * @param Order $order
     * @param string $storedId
     * @return mixed
     */
    private function storedStatus(Order $order, string $storedId)
    {
        $payment = $order->getPayment();
        if ((string) $payment->getAdditionalInformation(PaymentInfoInterface::PAYMENT_ID) !== $storedId) {
            return null;
        }

        return $payment->getAdditionalInformation(PaymentInfoInterface::PAYMENT_STATUS);
    }

    /**
     * Count a fetch attempt: at most 5 a minute per order and 60 a minute for the installation
     *
     * @param string $orderId The order's entity ID
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
     * @param Order $order
     * @return array{status: int, payment: array, reason: string, order: int}
     */
    private static function accepted(array $payment, Order $order): array
    {
        return ['status' => 200, 'payment' => $payment, 'reason' => 'ok', 'order' => (int) $order->getId()];
    }

    /**
     * Get a refused decision
     *
     * @param int $status
     * @param string $reason
     * @return array{status: int, payment: null, reason: string, order: null}
     */
    private static function refused(int $status, string $reason): array
    {
        return ['status' => $status, 'payment' => null, 'reason' => $reason, 'order' => null];
    }
}
