<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Customer;

use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Store\Model\StoreManagerInterface;
use Monei\ApiException;
use Monei\Model\CreateCustomerRequest;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\Customer\CustomerServiceInterface;
use Monei\MoneiPayment\Model\Config\Source\Mode;
use Monei\MoneiPayment\Model\ResourceModel\MoneiCustomer;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;

/**
 * Fail-open link between Magento customers and MONEI customers.
 */
class CustomerService implements CustomerServiceInterface
{
    private const LOG_PREFIX = '[MONEI Customers] ';

    /**
     * @var MoneiCustomer
     */
    private MoneiCustomer $resource;

    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $apiClient;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param MoneiCustomer $resource
     * @param MoneiApiClient $apiClient
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        MoneiCustomer $resource,
        MoneiApiClient $apiClient,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        Logger $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->resource = $resource;
        $this->apiClient = $apiClient;
        $this->moduleConfig = $moduleConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritdoc
     */
    public function getOrCreateCustomerId(
        int $customerId,
        int $storeId,
        string $email,
        string $name,
        string $phone
    ): ?string {
        try {
            $scope = $this->resolveScope($customerId, $storeId);
            if ($scope === null) {
                return null;
            }
            [$accountId, $mode] = $scope;

            $storedId = $this->resource->getMoneiCustomerId($customerId, $accountId, $mode);
            if ($storedId !== null) {
                return $storedId;
            }

            $request = $this->buildCreateRequest($customerId, $email, $name, $phone);
            $moneiCustomerId = (string) $this->apiClient->getMoneiSdk($storeId)
                ->customers->create($request)->getId();

            try {
                $storedId = $this->saveMapping($customerId, $accountId, $mode, $moneiCustomerId);
            } catch (\Throwable $e) {
                $this->logger->error(self::LOG_PREFIX . 'Mapping save failed', [
                    'customer_id' => $customerId,
                    'monei_customer_id' => $moneiCustomerId,
                    'error' => $e->getMessage(),
                ]);
                $storedId = null;
            }
            if ($storedId !== $moneiCustomerId) {
                $this->deleteUnmappedCustomer($customerId, $storeId, $moneiCustomerId);
            }

            return $storedId;
        } catch (\Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'Customer create failed', [
                'customer_id' => $customerId,
                'store_id' => $storeId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function forgetCustomerId(int $customerId, int $storeId, string $staleCustomerId): void
    {
        try {
            $scope = $this->resolveScope($customerId, $storeId);
            if ($scope === null) {
                return;
            }
            [$accountId, $mode] = $scope;

            $this->resource->deleteRow($customerId, $accountId, $mode, $staleCustomerId);
        } catch (\Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'Forget customer failed', [
                'customer_id' => $customerId,
                'store_id' => $storeId,
                'monei_customer_id' => $staleCustomerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteCustomer(int $customerId): void
    {
        try {
            $rows = $this->resource->getRowsByCustomer($customerId);
            if ($rows === []) {
                return;
            }
            $keyPairs = $this->getConfiguredKeyPairs();
        } catch (\Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'Customer delete failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($rows as $row) {
            try {
                $this->deleteMappedCustomer($customerId, $row, $keyPairs);
            } catch (\Throwable $e) {
                $this->logger->error(self::LOG_PREFIX . 'Customer delete failed, row kept', [
                    'customer_id' => $customerId,
                    'monei_customer_id' => (string) ($row['monei_customer_id'] ?? ''),
                    'account_id' => (string) ($row['account_id'] ?? ''),
                    'mode' => (string) ($row['mode'] ?? ''),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Collect the configured (account ID, mode, API key) of every scope, admin store included.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function getConfiguredKeyPairs(): array
    {
        $pairs = [];
        foreach ($this->storeManager->getStores(true) as $store) {
            $storeId = (int) $store->getId();
            $pairs[] = [
                $this->moduleConfig->getTestAccountId($storeId),
                'test',
                $this->moduleConfig->getTestApiKey($storeId),
            ];
            $pairs[] = [
                $this->moduleConfig->getProductionAccountId($storeId),
                'live',
                $this->moduleConfig->getProductionApiKey($storeId),
            ];
        }

        return array_values(array_filter(
            $pairs,
            static fn (array $pair): bool => $pair[0] !== '' && $pair[2] !== ''
        ));
    }

    /**
     * Delete one mapped MONEI customer with the key of the row's own mode; remove the row on success or 404.
     *
     * @param int $customerId
     * @param array $row
     * @param array $keyPairs List of [account ID, mode, API key]
     * @return void
     */
    private function deleteMappedCustomer(int $customerId, array $row, array $keyPairs): void
    {
        $accountId = (string) $row['account_id'];
        $mode = (string) $row['mode'];
        $context = [
            'customer_id' => $customerId,
            'monei_customer_id' => (string) $row['monei_customer_id'],
            'account_id' => $accountId,
            'mode' => $mode,
        ];

        // Test and live can share one account ID, so the mode must match too.
        $apiKey = null;
        foreach ($keyPairs as [$pairAccountId, $pairMode, $pairApiKey]) {
            if ($pairAccountId === $accountId && $pairMode === $mode) {
                $apiKey = $pairApiKey;
                break;
            }
        }
        if ($apiKey === null) {
            $this->logger->error(self::LOG_PREFIX . 'No API key for mapped customer, row kept', $context);

            return;
        }

        try {
            $this->apiClient->createSdkForApiKey($apiKey)->customers->delete($context['monei_customer_id']);
        } catch (\Throwable $e) {
            $context['error'] = $e->getMessage();
            if (!$e instanceof ApiException || $e->getCode() !== 404) {
                $this->logger->error(self::LOG_PREFIX . 'Customer delete failed, row kept', $context);

                return;
            }
            $this->logger->debug(self::LOG_PREFIX . 'Customer already deleted at MONEI', $context);
        }

        $this->resource->deleteRow($customerId, $accountId, $mode, $context['monei_customer_id']);
    }

    /**
     * Resolve the MONEI account ID and mode of a store.
     *
     * @param int $customerId
     * @param int $storeId
     * @return array{0: string, 1: string}|null Null when the customer must not be linked
     */
    private function resolveScope(int $customerId, int $storeId): ?array
    {
        // Express checkout turns a guest's null customer ID into 0.
        if ($customerId < 1) {
            $this->logger->debug(self::LOG_PREFIX . 'No customer ID, not linked', ['store_id' => $storeId]);

            return null;
        }

        $accountId = $this->moduleConfig->getAccountId($storeId);
        if ($accountId === '') {
            $this->logger->debug(self::LOG_PREFIX . 'No account ID configured, not linked', [
                'customer_id' => $customerId,
                'store_id' => $storeId,
            ]);

            return null;
        }

        $mode = $this->moduleConfig->getMode($storeId) === Mode::MODE_TEST ? 'test' : 'live';

        return [$accountId, $mode];
    }

    /**
     * Build the create request; the SDK setters throw on null, so empty values are left out.
     *
     * @param int $customerId
     * @param string $email
     * @param string $name
     * @param string $phone
     * @return CreateCustomerRequest
     */
    private function buildCreateRequest(
        int $customerId,
        string $email,
        string $name,
        string $phone
    ): CreateCustomerRequest {
        $request = new CreateCustomerRequest();
        if (trim($email) !== '') {
            $request->setEmail(trim($email));
        }
        if (trim($name) !== '') {
            $request->setName(trim($name));
        }
        if (trim($phone) !== '') {
            $request->setPhone(trim($phone));
        }
        $request->setMetadata(['platform' => 'magento', 'platformCustomerId' => (string) $customerId]);

        return $request;
    }

    /**
     * Insert the mapping and return the MONEI customer ID now stored for the scope.
     *
     * @param int $customerId
     * @param string $accountId
     * @param string $mode
     * @param string $moneiCustomerId
     * @return string|null Another request's ID after a lost race; null when nothing is stored
     */
    private function saveMapping(int $customerId, string $accountId, string $mode, string $moneiCustomerId): ?string
    {
        try {
            $this->resource->saveRow($customerId, $accountId, $mode, $moneiCustomerId);

            return $moneiCustomerId;
        } catch (DuplicateException $e) {
            return $this->resource->getMoneiCustomerId($customerId, $accountId, $mode);
        }
    }

    /**
     * Best-effort delete of a MONEI customer that has no mapping, so no PII stays out of erasure's reach.
     *
     * @param int $customerId
     * @param int $storeId
     * @param string $moneiCustomerId
     * @return void
     */
    private function deleteUnmappedCustomer(int $customerId, int $storeId, string $moneiCustomerId): void
    {
        try {
            $this->apiClient->getMoneiSdk($storeId)->customers->delete($moneiCustomerId);
        } catch (\Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'Delete of unmapped customer failed', [
                'customer_id' => $customerId,
                'monei_customer_id' => $moneiCustomerId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
