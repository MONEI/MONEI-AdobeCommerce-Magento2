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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Url;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\Connect;
use InvalidArgumentException;

/**
 * The callback URL of "Connect with MONEI" for a config scope, and the redirect URI that the start sends.
 *
 * The settings button and the start route both use it, so the button shows only for a URI the start sends.
 * It builds the URL with the frontend URL model: the backend one adds the secret key/… of the admin session.
 */
class CallbackUrl
{
    public const ROUTE = 'monei/payment/connect';

    /**
     * @var Url
     */
    private Url $url;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param Url $url
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(Url $url, StoreManagerInterface $storeManager)
    {
        $this->url = $url;
        $this->storeManager = $storeManager;
    }

    /**
     * Get the config scope of the website and store parameters of the admin config page, which Magento accepts as
     * a code or an ID
     *
     * @param mixed $website
     * @param mixed $store
     * @return array{0: string, 1: int}|null The scope type and ID; null for an unknown scope, the admin scope, or
     *     a store of another website
     */
    public function scope($website, $store): ?array
    {
        try {
            $websiteId = null;
            if ($website !== null && $website !== '') {
                $websiteId = is_scalar($website) ? (int) $this->storeManager->getWebsite($website)->getId() : 0;
                if ($websiteId === 0) {
                    return null;
                }
            }
            if ($store === null || $store === '') {
                return $websiteId === null
                    ? [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0]
                    : [ScopeInterface::SCOPE_WEBSITES, $websiteId];
            }
            if (!is_scalar($store)) {
                return null;
            }
            $storeModel = $this->storeManager->getStore($store);
            $storeId = (int) $storeModel->getId();
            if ($storeId === 0 || ($websiteId !== null && (int) $storeModel->getWebsiteId() !== $websiteId)) {
                return null;
            }

            return [ScopeInterface::SCOPE_STORES, $storeId];
        } catch (LocalizedException $e) {
            return null;
        }
    }

    /**
     * Get the frontend callback URL of a scope: the one of its store view, or of the default store view
     *
     * @param string $scope
     * @param int $scopeId
     * @return string
     * @throws LocalizedException When the scope has no store view
     */
    public function build(string $scope, int $scopeId): string
    {
        if ($scope === ScopeInterface::SCOPE_STORES) {
            $store = $this->storeManager->getStore($scopeId);
        } elseif ($scope === ScopeInterface::SCOPE_WEBSITES) {
            $store = $this->storeManager->getWebsite($scopeId)->getDefaultStore();
        } else {
            $store = $this->storeManager->getDefaultStoreView();
        }
        if (!$store) {
            throw new LocalizedException(__('This scope has no store view.'));
        }

        // Forced: plain _secure follows "Use Secure URLs on Storefront"; the broker takes only https.
        return $this->url->getUrl(
            self::ROUTE,
            ['_scope' => (int) $store->getId(), '_nosid' => true, '_forced_secure' => true]
        );
    }

    /**
     * Get the redirect URI of a scope, or null when the broker would refuse its callback URL
     *
     * @param string $scope
     * @param int $scopeId
     * @return string|null
     */
    public function redirectUri(string $scope, int $scopeId): ?string
    {
        try {
            $uri = Connect::canonicalRedirectUri($this->build($scope, $scopeId));
        } catch (InvalidArgumentException|LocalizedException $e) {
            return null;
        }

        return Connect::callbackProblem(Broker::CLIENT, $uri) === null ? $uri : null;
    }
}
